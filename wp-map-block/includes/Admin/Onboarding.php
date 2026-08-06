<?php

namespace WPMapBlock\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * First-run setup + post-update "What's New" flow.
 *
 * Tracks the version we last welcomed the user for. When the running plugin
 * version differs (fresh install → Setup, in-place update → What's New) we queue
 * a one-time redirect into the SPA, which opens the matching route. The SPA
 * clears the pending flag via /onboarding/seen so it never nags twice.
 */
class Onboarding
{
    const OPT_WELCOME_VERSION  = 'wpmb_welcome_version';   // version we last surfaced a welcome for
    const OPT_WELCOME_PREVIOUS = 'wpmb_welcome_previous';  // version upgraded *from* (blank on first install)
    const OPT_WELCOME_MODE     = 'wpmb_welcome_mode';      // '', 'setup', or 'whatsnew'
    const TRANSIENT_REDIRECT   = 'wpmb_welcome_redirect';

    // 3-day "upgrade to Pro" countdown. Anchored to the first time the user
    // OPENS the plugin admin for a given version — so a silent auto-update never
    // wastes the window; it only starts once the user actually shows up.
    const OPT_TIMER_VERSION = 'wpmb_promo_timer_version';
    const OPT_TIMER_START   = 'wpmb_promo_timer_start';
    const PROMO_DURATION    = 3 * DAY_IN_SECONDS;

    public static function init()
    {
        add_action('admin_init', [self::class, 'detect_version_change'], 5);
        add_action('admin_init', [self::class, 'maybe_redirect'], 20);
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Queue a "What's New" welcome when the plugin files were updated in place
     * (no activation hook fires on a WP.org / auto update). A genuine first
     * install is owned by flag_first_install() from the activation hook, which
     * already baselines OPT_WELCOME_VERSION to the current version, so we bail
     * here before mislabelling it as an update.
     */
    public static function detect_version_change()
    {
        $seen    = get_option(self::OPT_WELCOME_VERSION, '');
        $current = WPMAPBLOCK_VERSION;

        if ($seen === $current) {
            return;
        }

        // Reaching here with no seen version but no prior install markers means a
        // fresh install whose activation hook hasn't run yet — treat as setup.
        $is_first = ('' === $seen)
            && ! get_option('wpmb_version')
            && ! get_option('wpmb_first_install_time');

        update_option(self::OPT_WELCOME_PREVIOUS, $seen);
        update_option(self::OPT_WELCOME_VERSION, $current);
        update_option(self::OPT_WELCOME_MODE, $is_first ? 'setup' : 'whatsnew');
        set_transient(self::TRANSIENT_REDIRECT, 1, MINUTE_IN_SECONDS);
    }

    /**
     * One-time redirect into the SPA after install/update. Deliberately gentle:
     * never during AJAX/cron, never on network admin or bulk activation, and
     * never if the admin is already headed for one of our own screens.
     */
    public static function maybe_redirect()
    {
        if (! get_transient(self::TRANSIENT_REDIRECT)) {
            return;
        }
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (is_network_admin() || isset($_GET['activate-multi'])) {
            return;
        }
        if (! current_user_can('edit_posts')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (0 === strpos($page, WPMAPBLOCK_PLUGIN_SLUG)) {
            return; // deliberate navigation to our screens — don't hijack it
        }

        delete_transient(self::TRANSIENT_REDIRECT);
        wp_safe_redirect(admin_url('admin.php?page=' . WPMAPBLOCK_PLUGIN_SLUG . '&wpmb_welcome=1'));
        exit;
    }

    /**
     * Payload the SPA reads to open the correct welcome route. `requested` is the
     * one-time redirect marker; `mode` decides Setup vs What's New.
     *
     * @return array
     */
    public static function localized_data(): array
    {
        // This runs on our admin screens, i.e. "the user came to the dashboard" —
        // the moment we anchor the 3-day timer from.
        self::maybe_start_promo_timer();

        return [
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'requested'       => isset($_GET['wpmb_welcome']),
            'mode'            => (string) get_option(self::OPT_WELCOME_MODE, ''),
            'version'         => WPMAPBLOCK_VERSION,
            'previousVersion' => (string) get_option(self::OPT_WELCOME_PREVIOUS, ''),
            // Prefill for the "clever" (low-friction) info collection: the admin's
            // real name + email are already known to WordPress, so the wizard just
            // confirms them rather than asking the user to type from scratch.
            'subscriber'      => self::subscriber_prefill(),
            'links'           => [
                'terms'   => 'https://kodezen.com/terms-conditions/',
                'privacy' => 'https://kodezen.com/privacy-policy/',
            ],
            // 3-day upgrade countdown (null when expired / already Pro).
            'promoTimer'      => self::promo_timer(),
        ];
    }

    /**
     * Start the 3-day Pro-upgrade timer the first time the plugin admin is
     * opened for the current version. A new version restarts the window; an
     * already-running window for this version is left untouched. Never runs for
     * a licensed user (nothing to upsell).
     */
    public static function maybe_start_promo_timer(): void
    {
        if (class_exists('\WPMapBlock\Pro') && !\WPMapBlock\Pro::should_upsell()) {
            return; // fully licensed — no countdown
        }
        if (get_option(self::OPT_TIMER_VERSION) !== WPMAPBLOCK_VERSION) {
            update_option(self::OPT_TIMER_VERSION, WPMAPBLOCK_VERSION);
            update_option(self::OPT_TIMER_START, time());
        }
    }

    /**
     * Countdown state for the topbar. Null when the timer hasn't started, has
     * expired, or there's nothing left to upsell.
     *
     * @return array{endsAt:int,remaining:int,now:int}|null
     */
    public static function promo_timer(): ?array
    {
        if (class_exists('\WPMapBlock\Pro') && !\WPMapBlock\Pro::should_upsell()) {
            return null;
        }
        $start = (int) get_option(self::OPT_TIMER_START, 0);
        if (!$start || get_option(self::OPT_TIMER_VERSION) !== WPMAPBLOCK_VERSION) {
            return null;
        }
        $now       = time();
        $ends_at   = $start + self::PROMO_DURATION;
        $remaining = $ends_at - $now;
        if ($remaining <= 0) {
            return null;
        }
        return [
            'endsAt'    => $ends_at,   // unix seconds (server clock)
            'remaining' => $remaining, // seconds left at page load
            'now'       => $now,       // server "now" so the client avoids clock drift
        ];
    }

    /** Name/email prefill (from the current admin) + whether they already opted in. */
    protected static function subscriber_prefill(): array
    {
        $user = wp_get_current_user();
        $name = $user->exists() ? trim($user->first_name . ' ' . $user->last_name) : '';
        if (! $name && $user->exists()) {
            $name = $user->display_name;
        }
        $email = $user->exists() && $user->user_email ? $user->user_email : get_option('admin_email');

        return [
            // Prefill straight from the WordPress account — we no longer store a
            // separate name/email copy; the wizard/gift just confirm them at
            // opt-in time. Consent state is read from the SDK insights store.
            'name'       => (string) $name,
            'email'      => (string) $email,
            'subscribed' => Sdk::has_opted_in(),
            'site'       => home_url(),
        ];
    }

    public static function register_routes()
    {
        register_rest_route(WPMAPBLOCK_REST_NAMESPACE, '/onboarding/seen', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'mark_seen'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(WPMAPBLOCK_REST_NAMESPACE, '/onboarding/subscribe', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'subscribe'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    /**
     * Persist the collected contact + tracking consent, then route it to the
     * StoreEngine SDK's insights (usage) channel when a client is wired up. See
     * WPMapBlock\Admin\Sdk — the seam keeps this decoupled from the SDK's global
     * singleton so it never clashes with a host plugin that also loads the SDK.
     */
    public static function subscribe($request)
    {
        $p     = $request->get_json_params();
        $name  = isset($p['name']) ? sanitize_text_field($p['name']) : '';
        $email = isset($p['email']) ? sanitize_email($p['email']) : '';
        $optin = ! empty($p['optIn']);

        // Consent lives entirely in the SDK's insights store (allow_tracking),
        // the single shared option across every Kodezen product — we no longer
        // keep a per-plugin copy in wp_options. record_consent() forwards to the
        // SDK opt-in/opt-out and broadcasts wpmb/subscriber/consent for bridges.
        Sdk::record_consent($optin, $name, $email);

        return rest_ensure_response(['ok' => true, 'optIn' => $optin]);
    }

    /** Clear the pending-welcome flag so the screen doesn't auto-open again. */
    public static function mark_seen()
    {
        update_option(self::OPT_WELCOME_MODE, '');
        return rest_ensure_response(['ok' => true]);
    }

    /** Queue the Setup screen for a genuine first install (called from activation). */
    public static function flag_first_install()
    {
        update_option(self::OPT_WELCOME_PREVIOUS, '');
        update_option(self::OPT_WELCOME_VERSION, WPMAPBLOCK_VERSION);
        update_option(self::OPT_WELCOME_MODE, 'setup');
        set_transient(self::TRANSIENT_REDIRECT, 1, MINUTE_IN_SECONDS);
    }
}

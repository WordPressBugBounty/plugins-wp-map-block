<?php

namespace WPMapBlock\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration seam for the StoreEngine WordPress SDK (licensing / updates /
 * usage-insights).
 *
 * We deliberately do NOT call se_license_init() directly here. The SDK's client
 * (SE_License_SDK_Client) is a global, non-namespaced singleton — on a site
 * where a host plugin (e.g. StoreEngine itself) already booted it, a second
 * se_license_init() would either fatally re-declare the class or hand back the
 * host's client, so our opt-in would send the wrong product's data.
 *
 * Instead the SDK client is supplied through a filter. The plugin's own SDK
 * bootstrap (once the product is registered on the license server) resolves the
 * filter to its wp-map-block client:
 *
 *     add_filter('wpmb/sdk/client', fn() => se_license_init([
 *         'package_file' => WPMAPBLOCK_PLUGIN_FILE,
 *         'package_name' => 'wp-map-block',
 *         'product_id'   => WPMB_SDK_PRODUCT_ID,   // from the Kodezen store
 *         'is_free'      => true,
 *         'init_insights'=> true,
 *         'license_server' => WPMB_SDK_LICENSE_SERVER,
 *     ]));
 *
 * Until that's wired, consent is still persisted locally and broadcast via the
 * `wpmb/subscriber/consent` action, so a CRM/analytics bridge can pick it up.
 */
class Sdk
{
    /** The wp-map-block SDK client, or null when the SDK isn't wired up. */
    public static function client()
    {
        return apply_filters('wpmb/sdk/client', null);
    }

    /**
     * Has the user already opted in to usage tracking — through the SDK's own
     * opt-in notice, the Setup wizard, or the founding-member gift? Reads the
     * SDK insights flag (allow_tracking === 'yes'), the single source of truth
     * shared by all those entry points. Lets the gift skip the email layer when
     * consent already exists. Degrades to false when the SDK isn't wired.
     */
    public static function has_opted_in(): bool
    {
        $client = self::client();
        if (! is_object($client) || ! method_exists($client, 'insights')) {
            return false;
        }
        try {
            $insights = $client->insights();
            return is_object($insights)
                && method_exists($insights, 'is_tracking_allowed')
                && $insights->is_tracking_allowed();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Record tracking consent and forward it to the SDK insights channel.
     *
     * @param bool   $optin Whether the user agreed to share usage data.
     * @param string $name  Collected contact name.
     * @param string $email Collected contact email.
     */
    public static function record_consent(bool $optin, string $name = '', string $email = '')
    {
        // Let any listener (CRM sync, analytics bridge, the SDK wiring) react.
        do_action('wpmb/subscriber/consent', $optin, $name, $email);

        $client = self::client();
        if (! is_object($client) || ! method_exists($client, 'insights')) {
            return;
        }

        try {
            if ($optin) {
                $client->insights()->optIn();   // records + sends usage data
            } else {
                $client->insights()->optOut();  // marks declined, stops tracking
            }
        } catch (\Throwable $e) {
            // Never let telemetry break onboarding.
        }
    }
}

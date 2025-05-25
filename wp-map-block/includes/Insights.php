<?php
namespace WPMapBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignoreFile

class Insights {
	private static $instances = [];

	private $slug;
	private $type;
	private $version;
	private $api_site_url;
	private $config = [];

	private function __construct( $api_site, $slug, $type = 'plugin', $version = null, $config = [] ) {
		$this->api_site_url = untrailingslashit( $api_site );
		$this->slug         = sanitize_title( $slug );
		$this->type         = $type === 'theme' ? 'theme' : 'plugin';
		$this->version      = $version;
		$default_config = [
			'logo' => '', // default logo URL
			'optin_message' => 'Help improve ' . esc_html( $this->slug ) . '! Allow anonymous usage tracking?',
			'deactivation_message' => 'If you have a moment, please share why you are deactivating:',
			'deactivation_reasons' => [
				'no_longer_needed' => [
					'label' => 'I no longer need the plugin',
				],
				'found_a_better_plugin' => [
					'label' => 'I found a better plugin',
					'has_custom_reason' => true,
					'custom_reason_placeholder' => 'Please share which plugin',
				],
				'couldnt_get_the_plugin_to_work' => [
					'label' => 'I couldn\'t get the plugin to work',
				],
				'temporary_deactivation' => [
					'label' => 'It\'s a temporary deactivation',
				],
				'other' => [
					'label' => 'Other',
					'has_custom_reason' => true,
					'custom_reason_placeholder' => 'Please share the reason',
				],
			],
		];

		$this->config = wp_parse_args( $config, $default_config );

		add_action( 'admin_notices', [ $this, 'maybe_show_optin' ] );
		add_action( 'admin_init', [ $this, 'handle_optin_click' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'inject_deactivate_feedback' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_inline_css' ] );

		add_action( 'wp_ajax_insights_deactivate_send', function () {
			if ( ! wp_verify_nonce( sanitize_text_field( $_POST['security'] ), $this->slug . '-insights-deactivate-nonce' ) ) {
				wp_send_json_error( 'Invalid nonce.' );
			}

			$slug = sanitize_text_field( $_POST['slug'] );
			$reason_key = sanitize_text_field( $_POST['reason_key'] );
			$reason = sanitize_text_field( $_POST['reason'] );
			$instance = self::$instances[ $slug ] ?? null;

			if ( ! $instance || empty( $slug ) ) {
				wp_send_json_error();
			}

			$instance->send_to_server( 'deactivated', [
				'deactivation_reason_type' => $reason_key,
				'deactivation_reason' => $reason,
				'deactivation_time'   => current_time( 'mysql' ),
			] );
			$instance->mark_sent( 'deactivated' );

			wp_send_json_success();
		} );
	}

	public static function init( $api_site, $slug, $type = 'plugin', $version = null, $config = [] ) {
		if ( ! isset( self::$instances[ $slug ] ) ) {
			self::$instances[ $slug ] = new self( $api_site, $slug, $type, $version, $config );
		}
	}

	public function maybe_show_optin() {
		$screen = get_current_screen();
		if ( $screen && (in_array( $screen->base, [ 'post', 'post-new' ], true ) || $screen->is_block_editor()) ) {
			return;
		}

		if ( $this->is_already_sent( 'optin' ) ) {
			return;
		}

		$install_time = get_option('wp_map_block_first_install_time');
		$current_time = time() + (get_option('gmt_offset') * HOUR_IN_SECONDS);
		$three_days   = 3 * DAY_IN_SECONDS;

		if ( ! $install_time || ! is_numeric( $install_time ) ) {
			return;
		}

		if ( ( $current_time - $install_time ) < $three_days ) {
			return;
		}

		$optin_key = 'insights_optin_' . $this->slug;
		$nonce = wp_create_nonce( 'insights_optin_' . $this->slug );
		$optin_url = esc_url( add_query_arg( [ $optin_key => 'yes', 'security' => $nonce ] ) );
		$nooptin_url = esc_url( add_query_arg( [ $optin_key => 'no', 'security' => $nonce ] ) );

		echo '<div class="notice notice-info" style="padding:0;border-top:0;border-right:0;border-bottom:0;margin:1rem; display: flex; border-left-color: #008dff;">
            <div style="padding: 24px; background: #E0F1FF; display:flex; flex-direction: column; justify-content:center; align-item: center;">
				<img src="' . esc_url( WPMAPBLOCK_ASSETS_URI . 'images/logo.png' ) . '" width="100" alt="logo" style="margin-bottom: 20px;" />
				<p>Build connections with <br />2000+ Website experts.</p>
				<a href="https://www.facebook.com/groups/386840187678401" target="_blank" style="color: #008dff;">Join the community</a>
			</div>
            <div style="padding: 24px;">
				<h2 style="margin-top: 0; font-size: 20x; font-weight:bold; color: #1E1E1E;"> 💌 Help Us Improve & Get Exclusive Perks!</h2>
				<p>
					We’d love to stay in touch and share helpful updates, tips, and special offers to make the most of <strong>WP Map Block</strong>. Your privacy is our priority. No spam — ever.
				</p>
				<ul>
					<li>✅ Early access to new features</li>
					<li>✅ Helpful guides & tutorials</li>
					<li>✅ Occasional discounts</li>
				</ul>
				<details style="margin: 10px 0;">
					<summary style="cursor: pointer; color: #008dff; text-decoration: underline;">What we collect if you opt in?</summary>
					<div style="margin-top: 8px;">
					<p>We collect your <strong>site URL, plugin version, WordPress version, PHP version</strong>, and <strong>admin email</strong> to better tailor our updates and improve plugin performance.</p>
					<p><em>No sensitive or personal data is collected. You can unsubscribe at any time.</em></p>
					</div>
				</details>
				<p style="display: flex;column-gap: 15px; margin-bottom: 0;">
					<a href="' . esc_url( $optin_url ) . '" class="button-primary" style="background: #008dff; border-color: #008dff;">Yes, keep me updated</a>
					<a href="' . esc_url( $nooptin_url ) . '" class="button-secondary" style="background: #E0F1FF; color: #008dff; border-color: #E0F1FF;">No thanks</a>
				</p>
			</div>
        </div>';
	}

	public function handle_optin_click() {
		$key = 'insights_optin_' . $this->slug;
		if ( isset( $_GET[ $key ] ) && wp_verify_nonce( sanitize_text_field( $_GET['security'] ), 'insights_optin_' . $this->slug ) ) {
			$optin = $_GET[ $key ] === 'yes' ? 1 : 0;
			if($optin){
				$this->send_to_server( 'optin', [
					'optin_status' => (int) $optin,
					'optin_time'   => current_time( 'mysql' ),
				] );
			}
			$this->mark_sent( 'optin' );
			wp_safe_redirect( remove_query_arg( $key ) );
			exit;
		}
	}

	public function inject_deactivate_feedback( $hook ) {
		if ( $hook !== 'plugins.php' || $this->is_already_sent( 'deactivated' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'jquery-ui-dialog' );

		add_action( 'admin_footer', function () {
			$slug = esc_js( $this->slug );
			$ajax_url = esc_url( admin_url( 'admin-ajax.php?action=insights_deactivate_send' ) );
			?>
			<script>
			jQuery(function ($) {
				const $link = $('tr[data-slug="<?php echo esc_attr( $slug ); ?>"] .deactivate a');
				const dialogId = 'insights-deactivate-dialog-<?php echo esc_attr( $slug ); ?>';
				const formId = 'insights-deactivate-form-<?php echo esc_attr( $slug ); ?>';

				const $dialog = $(`
					<div id="${dialogId}">
						<div class="insights-deactivate-dialog__header">
							<img src="<?php echo esc_url( $this->config['logo'] ); ?>" width="20" />
							<span>Quick Feedback</span>
						</div>
						<form id="${formId}" class="insights-deactivate-form">
							<input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( $this->slug . '-insights-deactivate-nonce' ) ); ?>" />
							<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>" />
							<div class="insights-deactivate-dialog__description"><?php echo esc_html( $this->config['deactivation_message'] ); ?></div>
							<ul>
								<?php foreach ( $this->config['deactivation_reasons'] as $reason_key => $reason ) : ?>
								<li>
									<label style="display:block; margin-bottom: 8px;">
										<input type="radio" name="reason_key" value="<?php echo esc_attr( $reason_key ); ?>" required />
										<?php echo esc_html( $reason['label'] ); ?>
									</label>
									<?php
									if ( isset( $reason['toggle_text'] ) && ! empty( $reason['toggle_text'] ) ) :
										?>
									<div class="insights-toggle-text" style="color: #F59E0B; display: none; margin-left: 25px;">
										<?php echo esc_html( $reason['toggle_text'] ); ?>
									<div>
										<?php
										endif;
									?>

									<?php
									if ( isset( $reason['has_custom_reason'] ) && $reason['has_custom_reason'] === true ) :
										?>
									<div class="insights-deactivate-form-reason-message">
										<input type="text" name="reason" placeholder="<?php echo esc_attr( $reason['custom_reason_placeholder'] ); ?>" required />
									<div>
										<?php
										endif;
									?>
								</li>
								<?php endforeach; ?>
							</ul>
							<div class="insights-deactivate-form__control-button">
								<button type="submit">Submit & Deactivate</button>
								<button type="button" data-type="skip">Skip & Deactivate</button>
							</div>
						</form>
					</div>
				`).appendTo('body').hide();

				$link.on('click', function (e) {
					e.preventDefault();
					const href = $(this).attr('href');

					$(`#${dialogId}`).dialog({
						modal: true,
						width: 550,
						dialogClass: 'insights-deactivate-dialog',
						buttons: [],
						create: function () {
							const $thisDialog = $(this);
							// Hide the default close (X) button
							$thisDialog.closest('.ui-dialog').find('.ui-dialog-titlebar-close').hide();
						},
						close: function () {
							// Clean up delegated handler
							$(document).off('click.insightsOverlay');
						}

					});

					const $form = $(`#${formId}`);

					// Unbind first to prevent multiple bindings
					$form.off('submit').on('submit', function (event) {
						event.preventDefault();

						const $submitButton = $(this).find('button[type="submit"]');
						const originalHtml = $submitButton.html();

						// Add WP spinner
						$submitButton.prop('disabled', true).html('Deactivating...');

						const formDataArray = $form.serializeArray();
						const formDataObject = {};

						formDataArray.forEach(field => {
							if (field.value.trim() !== '') {
								formDataObject[field.name] = field.value;
							}
						});

						$.ajax({
							url: '<?php echo $ajax_url; ?>',
							method: 'POST',
							data: formDataObject,
							success: function (response) {
								window.location.href = href;
							},
							error: function () {
								alert('An error occurred. Please try again.');
								$submitButton.prop('disabled', false).html(originalHtml);
							}
						});
					});


					// Skip button closes modal and redirects
					$form.find('button[data-type="skip"]').off('click').on('click', function () {
						$(`#${dialogId}`).dialog('close');
						window.location.href = href;
					});

					$(document).on('click.insightsOverlay', '.ui-widget-overlay', function () {
						// Get the visible dialog that is open now
						const $openDialog = $('.ui-dialog-content:visible');
						if ($openDialog.length) {
							$openDialog.dialog('close');
						}
					});

					$form.find('input[name="reason_key"]').on('change', function () {
						const $selected = $(this).closest('li');

						// Hide all custom reason inputs
						$form.find('.insights-deactivate-form-reason-message').hide().find('input').prop('required', false);
						// toggle text
						$form.find('.insights-toggle-text').hide();
						$selected.find('.insights-toggle-text').show();

						// Show the one under the selected reason, if exists
						const $customReason = $selected.find('.insights-deactivate-form-reason-message');
						if ($customReason.length) {
							$customReason.show().find('input').prop('required', true);
						}
					});

				});
			});
			</script>
			<?php
		});

	}

	private function send_to_server( $track_type, $extra_data = [] ) {
		if ( $this->is_already_sent( $track_type ) ) {
			return;
		}

		$data = [
			'track_type'       => $track_type,
			'project_type'     => $this->type,
			'project_slug'     => $this->slug,
			'site_url'         => site_url(),
			'admin_email'      => get_option( 'admin_email' ),
			'wp_version'       => get_bloginfo( 'version' ),
			'php_version'      => PHP_VERSION,
			'project_version'  => $this->version,
			'deactivation_reason_type'        => '',
			'deactivation_reason'        => '',
			'deactivation_time'        => current_time( 'mysql' ),
		];

		if ( $track_type === 'optin' ) {
			$data['optin_status'] = '';
			$data['optin_time'] = current_time( 'mysql' );
			unset( $data['deactivation_reason_type'] );
			unset( $data['deactivation_reason'] );
			unset( $data['deactivation_time'] );
		}

		$payload = \wp_parse_args( $extra_data, $data );

		$api_url = trailingslashit( $this->api_site_url ) . 'wp-json/insightsforwp/v1/track';

		$response = wp_remote_post( $api_url, [
			'timeout' => 10,
			'body'    => $payload,
			'sslverify'   => false
		] );

		if ( is_wp_error( $response ) ) {
			// Handle error.
			$error_message = $response->get_error_message();
			// You can log this or return an error response.
			error_log( "Request failed: $error_message" );
		}
	}

	private function is_already_sent( $type ) {
		$sent = get_option( 'insightsforwp_sent_data', [] );
		return ! empty( $sent[ $this->slug ][ $type ] );
	}

	private function mark_sent( $type ) {
		$sent = get_option( 'insightsforwp_sent_data', [] );
		$sent[ $this->slug ][ $type ] = true;
		update_option( 'insightsforwp_sent_data', $sent, false );
	}

	public function enqueue_inline_css() {
		if ( $this->is_already_sent( 'deactivated' ) ) {
			return;
		}
		$css = '
            .ui-widget-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100% !important;
                height: 100% !important;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1000;
            }
            .insights-deactivate-dialog {
                display: flex;
                z-index: 99999;
            }
            {{wrapper}} .insights-deactivate-dialog__header {
                padding: 18px 15px;
                box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
                text-align: start;
                display: flex;
            }
            {{wrapper}} .insights-deactivate-dialog__header span {
                font-size: 15px;
                text-transform: uppercase;
                font-weight: bold;
                padding-inline-start: 5px;
                color: #515962;
            }
            {{wrapper}} .insights-deactivate-dialog__description {
                font-size: 15px;
                line-height: 1.4;
                font-weight: bold;
                color:  #515962;
            }
            {{wrapper}} {
                height: auto;
                background-color: #ffffff;
                border-radius: 3px;
                box-shadow: 2px 8px 23px 3px rgba(0, 0, 0, 0.2);
                overflow: hidden;
            }

            {{wrapper}} .insights-deactivate-form {
                padding: 30px;
                text-align: start;
            }
            {{wrapper}} input[type="radio"] {
                margin-block: 0;
                margin-inline: 0 10px;
                box-shadow: none;
            }
            {{wrapper}} label {
                display: block;
                font-size: 13px;
                color:  #515962;
            }
            {{wrapper}} ul {
                padding-block-start: 30px;
                padding-block-end: 15px;
            }
            {{wrapper}} .insights-deactivate-form-reason-message {
                display: none;
            }
            {{wrapper}} .insights-deactivate-form-reason-message input {
                width: 92%;
                margin-left: 25px;
                background-color: transparent;
                color:  #515962;
                padding: 5px;
                box-shadow: none;
            }
            {{wrapper}} .insights-deactivate-form__control-button {
                display: flex;
                justify-content: space-between;
            }
            {{wrapper}} .insights-deactivate-form__control-button button {
                font-size: 12px;
                font-weight: 500;
                line-height: 1.2;
                padding: 8px 16px;
                outline: none;
                border: none;
                border-radius: 4px;
                background-color: white;
                color: #0c0d0e;
                transition: all .3s;
                cursor: pointer;
            }
            {{wrapper}} .insights-deactivate-form__control-button button[type="submit"] {
                background: #008dff;
                color: #fff;
            }
            {{wrapper}} .insights-deactivate-form__control-button button[type="button"] {
                background: transparent;
                color: #c4c4c5;
            }
        ';
		wp_add_inline_style( 'wp-admin', str_replace( '{{wrapper}}', '#insights-deactivate-dialog-' . $this->slug, $css ) );
	}
}

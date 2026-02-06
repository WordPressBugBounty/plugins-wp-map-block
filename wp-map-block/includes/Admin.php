<?php
namespace WPMapBlock;

class Admin
{
	public static function init(){
		$self = new self();
		add_filter( 'plugin_row_meta', array( $self, 'add_plugin_links' ), 10, 2 );
		// add_action( 'admin_notices', array($self, 'ablocks_install_notice') );
		add_action( 'admin_notices', array($self, 'black_friday_notice') );
		add_action( 'admin_init', [ $self, 'ablocks_hide_notice' ] );
		$self->dispatch_insights();
	}

	public function add_plugin_links($links, $file){
		if ( WPMAPBLOCK_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$map_block_links = array(
			'docs'    => array(
				'url'        => 'https://academylms.net/how-to-use-wp-map-block/',
				'label'      => __( 'Docs', 'wp-map-block' ),
				'aria-label' => __( 'View WP Map Block documentation', 'wp-map-block' ),
			),
			'support' => array(
				'url'        => 'https://wordpress.org/support/plugin/wp-map-block/',
				'label'      => __( 'Community Support', 'wp-map-block' ),
				'aria-label' => __( 'Visit community forums', 'wp-map-block' ),
			),
			'review'  => array(
				'url'        => 'https://wordpress.org/support/plugin/wp-map-block/reviews/#new-post',
				'label'      => __( 'Rate the plugin ★★★★★', 'wp-map-block' ),
				'aria-label' => __( 'Rate the plugin.', 'wp-map-block' ),
			),
		);

		foreach ( $map_block_links as $key => $link ) {
			$links[ $key ] = sprintf(
				'<a target="_blank" href="%s" aria-label="%s">%s</a>',
				esc_url( $link['url'] ),
				esc_attr( $link['aria-label'] ),
				esc_html( $link['label'] )
			);
		}

		return $links;
	}

	public function ablocks_install_notice() {
		if(get_option('wpmapblock_ablocks_install_notice_hidden')){
			return;
		}
		if ( current_user_can( 'install_plugins' ) && ! is_plugin_active( 'ablocks/ablocks.php' ) ) {
			// Generate the installation URL
			$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=ablocks' ), 'install-plugin_ablocks' );
			$hide_url = esc_url( add_query_arg( 'ablocks-hide-notice-by-wpmapblock', 1 ) ); // URL to hide the notice
			// Create the admin notice
			?>
			<div class="wpmapblock-to-ablocks-notice notice notice-warning">
				<div class="wpmapblock-to-ablocks-notice__entry-left">
					<img src="<?php echo esc_url(WPMAPBLOCK_ASSETS_URI . 'images/ablocks-editor.png'); ?>" alt="" style="max-width: 100%; height: auto;" />
				</div>
				<div class="wpmapblock-to-ablocks-notice__entry-right">
					<h4><?php esc_html_e('Are you building your site with Gutenberg?', 'wp-map-block'); ?></h4>
					<p>
						<?php esc_html_e( 'aBlocks got you covered. Supercharge your site with aBlocks—the ultimate page builder. With 60+ blocks, you can create stunning websites quickly and effortlessly.', 'wp-map-block' ); ?>
						<?php esc_html_e('Learn More:', 'wp-map-block'); ?> <a href="https://ablocks.pro/">ablocks.pro</a>
					</p>
					<p>
						<a href="<?php echo esc_url( $install_url ); ?>" class="button button-primary">
							<?php esc_html_e( 'Install aBlocks Now', 'wp-map-block' ); ?>
						</a>
						<a href="<?php echo esc_url( $hide_url ); ?>" class="button button-danger">
							<?php esc_html_e( 'Hide Notice', 'wp-map-block' ); ?>
						</a>
					</p>
				</div>
			</div>
			<style>
				.wpmapblock-to-ablocks-notice {
					display: flex;
					background: linear-gradient(90deg, #7346FF 0%, #3A5CFF 100%);
					border-left-width: 180px;
					border-left-color: black;
					padding: 16px;
					column-gap: 35px;
				}
				.wpmapblock-to-ablocks-notice__entry-left {
					margin-left: -80px;
				}
				.wpmapblock-to-ablocks-notice__entry-right {
					display: flex;
					flex-direction: column;
					justify-content: center;
				}
				.wpmapblock-to-ablocks-notice__entry-right h4{
					font-size: 22px;
					font-weight: 600;
					line-height: 36px;
					text-align: left;
					color: #FFFFFF;
					margin-top: 0;
					margin-bottom: 10px;
				}
				.wpmapblock-to-ablocks-notice__entry-right p {
					font-size: 14px;
					font-weight: 400;
					line-height: 18px;
					text-align: left;
					color: #FFFFFF;
				}
				.wpmapblock-to-ablocks-notice__entry-right a {
					font-size: 14px;
					font-weight: 400;
					line-height: 18px;
					text-align: left;
					color: #FEFE3F;
				}
				.wpmapblock-to-ablocks-notice__entry-right .button.button-primary {
					background: #FEFE3F;
					color: #272727;
					padding: 5px 20px;
					font-size: 14px;
					border-radius: 4px;
				}
				.wpmapblock-to-ablocks-notice__entry-right .button.button-danger {
					background: transparent;
					color: white;
					text-decoration: underline;
					padding: 10px 25px;
					font-size: 14px;
					border-radius: 4px;
					border: 0;
				}
				@media all and (max-width: 768px){
					.wpmapblock-to-ablocks-notice {
						border: 0;
					}
					.wpmapblock-to-ablocks-notice__entry-left {
						display: none;
					}
				}
			</style>
			<?php
		}
	}

	public function black_friday_notice() {
		$screen = get_current_screen();
		if ( ! in_array( $screen->id, ['edit-post', 'edit-page', 'dashboard'] ) ) {
			return;
		}

		if(get_option('kodezen_black_friday_notice')){
			return;
		}

		if(did_action('kodezen_dispatch_bfcm')){
			return;
		}

		do_action('kodezen_dispatch_bfcm');
		// Generate the installation URL
		$hide_url   = esc_url( add_query_arg( 'kodezen_black_friday_notice', 1 ) );
        $cta_url    = 'https://kodezen.com/bfcm-offer/?utm_source=wpmapblock&utm_medium=website&utm_campaign=bfcm&utm_content=Buy%20Now&utm_term=BFCM';
        $grid_image = esc_url( WPMAPBLOCK_ASSETS_URI . 'images/grid-image.png' );
        ?>
        <div style="position:relative;margin:24px 0;padding:36px 32px;background:#151516;overflow:hidden;color:#FFFFFF;font-family:'Inter','Segoe UI',sans-serif;width: 93%;">
            <a href="<?php echo $hide_url; ?>" style="position:absolute;top:18px;right:18px;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#FFFFFF;font-size:20px;font-weight:500;text-decoration:none;border: 1px solid;">&times;</a>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <div style="flex:1 1 540px;min-width:280px;display:flex;gap:24px;align-items:start;">
                    <div style="flex-shrink:0;width:70px;height:70px;border-radius:50px;background:#fff;display:flex;align-items:center;justify-content:center;">
                        <img src="<?php echo esc_url(  'https://kodezen.com/wp-content/uploads/2025/09/svg_1_2.webp' ); ?>" alt="<?php esc_attr_e( 'Kodezen logo', 'wp-map-block' ); ?>" style="width:58px;height:58px;object-fit:contain;">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:16px;max-width:576px;">
                        <span style="display:inline-flex;align-items:center;gap:8px;width:max-content;padding:6px 18px;background:#fff;border-radius:50px;font-size:14px;font-weight:500;line-height:1.4;color: #000;"><?php echo wp_kses( __( 'Thanks for using <strong>WP Map Blocks by Kodezen</strong>&#128640;', 'wp-map-block' ), [ 'strong' => [] ] ); ?></span>
                        <span style="font-size:24px;line-height:1.25;font-weight:500;letter-spacing:-0.02em;"><?php esc_html_e( 'Don\'t miss Kodezen\'s Black Friday Mega Sale', 'wp-map-block' ); ?></span>
                        <span style="font-size:14px;line-height:1.7;color:#fff;font-weight:400;">
                            <?php
                            echo wp_kses(
                                sprintf(
                                    __( 'Save up to %1$s OFF on Kodezen WordPress Plugins — the biggest savings of the year!', 'wp-map-block' ),
                                    '<span style="color:#FFE311;font-weight:600;">88% </span>'
                                ),
                                [ 'span' => [ 'style' => [] ] ]
                            );
                            ?>
                        </span>
                        <a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:10px;width:max-content;padding:8px 16px;background:#FF3945;border-radius:8px;font-size:12px;font-weight:600;color:#FFFFFF;text-decoration:none;">
                            <?php esc_html_e( 'Grab Your Deal Now', 'wp-map-block' ); ?>
                        </a>
                    </div>
                </div>
                <div style="flex:1 1 300px;min-width:260px;display:flex;justify-content:center;">
                    <a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" style="display:block;width:100%;max-width:600px;">
                        <img src="<?php echo $grid_image; ?>" alt="<?php esc_attr_e( 'Kodezen plugin bundle products', 'wp-map-block' ); ?>" style="width:100%;height:auto;">
                    </a>
                </div>
            </div>
        </div>
		<?php
	}

	public function ablocks_hide_notice() {
		if ( isset( $_GET['ablocks-hide-notice-by-wpmapblock'] ) ) {
			update_option( 'wpmapblock_ablocks_install_notice_hidden', true, false );
			// Redirect to remove query param
			wp_redirect( remove_query_arg( 'ablocks-hide-notice-by-wpmapblock' ) );
			exit;
		}else if ( isset( $_GET['kodezen_black_friday_notice'] ) ) {
			update_option( 'kodezen_black_friday_notice', true, false );
			// Redirect to remove query param
			wp_redirect( remove_query_arg( 'kodezen_black_friday_notice' ) );
			exit;
		}

	}

	public function dispatch_insights() {
		Insights::init(
			'https://kodezen.com',
			WPMAPBLOCK_PLUGIN_SLUG,
			'plugin',
			WPMAPBLOCK_VERSION,
			[
				'logo'                 => WPMAPBLOCK_ASSETS_URI . 'images/logo.png', // default logo URL
				'optin_message'        => 'Help improve WP Map Block! Allow anonymous usage tracking?',
				'deactivation_message' => 'If you have a moment, please share why you are deactivating WP Map Block:',
				'deactivation_reasons' => [
					'no_longer_needed'               => [
						'label' => 'I no longer need the plugin',
					],
					'found_a_better_plugin'          => [
						'label'                     => 'I found a better plugin',
						'has_custom_reason'         => true,
						'custom_reason_placeholder' => 'Please share which plugin',
					],
					'couldnt_get_the_plugin_to_work' => [
						'label' => 'I couldn\'t get the plugin to work',
					],
					'temporary_deactivation'         => [
						'label' => 'It\'s a temporary deactivation',
					],
					'other'                          => [
						'label'                     => 'Other',
						'has_custom_reason'         => true,
						'custom_reason_placeholder' => 'Please share the reason',
					],
				],
			]
		);
	}
}

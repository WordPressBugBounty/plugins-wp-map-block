<?php
namespace WPMapBlock;

class Admin
{
	public static function init(){
		$self = new self();
		add_filter( 'plugin_row_meta', array( $self, 'add_plugin_links' ), 10, 2 );
		add_action( 'admin_notices', array($self, 'ablocks_install_notice') );
		add_action( 'admin_init', [ $self, 'ablocks_hide_notice' ] );
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

	function ablocks_install_notice() {
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
						<?php esc_html_e( 'aBlocks got you covered. Supercharge your site with aBlocks—the ultimate page builder. With 30+ blocks, you can create stunning websites quickly and effortlessly.', 'wp-map-block' ); ?>
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
	public function ablocks_hide_notice() {
		if ( isset( $_GET['ablocks-hide-notice-by-wpmapblock'] ) ) {
			update_option( 'wpmapblock_ablocks_install_notice_hidden', true, false );
		}
	}
}

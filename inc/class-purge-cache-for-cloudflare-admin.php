<?php 
/**
 * CloudFlare® Purge Admin Class
 *
 * Load CloudFlare® Purge plugin admin area.
 * 
 * @package    CloudFlare_Purge
 * @subpackage Admin
 */

if ( ! class_exists( 'CloudFlare_Purge_Admin' ) ) :
/**
 * Load CloudFlare Purge plugin admin area.
 *
 * @since 1.0
 */
class CloudFlare_Purge_Admin {
	/**
	 * CloudFlare_Purge class instance.
	 *
	 * @access protected
	 */
	protected $cloudflare_purge;

	/**
	 * Add main method to appropriate hook.
	 *
	 * @access public
	 */
	public function __construct() {
		// Initialize main class
		$this->cloudflare_purge = CloudFlare_Purge::get_instance();

		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings'       )     );

		// Register purge all page
		add_action( 'admin_menu', array( $this, 'register_purge_all_page' ), 10 );
	}

	/**
	 * Initialize CloudFlare_Purge_Admin object.
	 *
	 * @access public
	 *
	 * @return CloudFlare_Purge_Admin $instance Instance of CloudFlare_Purge_Admin class.
	 */
	public static function &get_instance() {
		static $instance = false;

		if ( !$instance ) {
			$instance = new CloudFlare_Purge_Admin;
		}

		return $instance;
	}

	/**
	 * Get name of the page where do settings fields appear.
	 *
	 * @access public
	 *
	 * @return string $page Name of the settings page. Default 'writting'.
	 */
	public function get_settings_page() {
		/**
		 * Filter name of the page.
		 *
		 * @param string $page Name of the page. Default 'writing'.
		 */
		$page = apply_filters( 'cloudflare_purge_admin_settings_page_name', 'writing' );

		return $page;
	}

	/**
	 * Get name of the section where do settings fields appear.
	 *
	 * @access public
	 *
	 * @return string $section Name of the settings section. Default 'default'.
	 */
	public function get_settings_section() {
		/**
		 * Filter name of the section.
		 *
		 * @param string $section Name of the section. Default 'default'.
		 */
		$section = apply_filters( 'cloudflare_purge_admin_settings_section_name', 'default' );

		return $section;
	}

	/**
	 * Get name of the option group where do settings fields appear.
	 *
	 * @access public
	 *
	 * @return string $option_group Name of the option group. Default 'writing'.
	 */
	public function get_option_group() {
		/**
		 * Filter name of the option group.
		 *
		 * @param string $option_group Name of the option group. Default 'writting'.
		 */
		$option_group = apply_filters( 'cloudflare_purge_admin_option_group_name', 'writing' );

		return $option_group;
	}

	/**
	 * Register settings fields.
	 *
	 * @access public
	 */
	public function register_settings() {
		// Get name of the page
		$page = $this->get_settings_page();

		// Get name of the page
		$section = $this->get_settings_section();

		// Get name of the option_group
		$option_group = $this->get_option_group();

		add_settings_field( 'cloudflare_purge_api_key',           __( 'CloudFlare API Key',           'cloudflare-purge' ), array( $this, 'render_api_key' ), $page, $section );
		add_settings_field( 'cloudflare_purge_api_email_address', __( 'CloudFlare API Email Address', 'cloudflare-purge' ), array( $this, 'render_email'   ), $page, $section );

		register_setting( $option_group, 'cloudflare_purge_api_key',           'sanitize_key' );
		register_setting( $option_group, 'cloudflare_purge_api_email_address', 'is_email'     );

		/**
		 * Fires after settings fields are registered.
		 *
		 * @since 1.0
		 */
		do_action( 'cloudflare_purge_admin_after_register_settings' );
	}

	/**
	 * Display CloudFlare API Key settings field.
	 *
	 * @access public
	 */
	public function render_api_key() {
		$api_key = get_option( 'cloudflare_purge_api_key' );
		?>
		<label for="cloudflare_purge_api_key">
		<input type="text" id="cloudflare_purge_api_key" class="regular-text ltr" name="cloudflare_purge_api_key" value="<?php echo esc_attr( $api_key ); ?>" />
		</label>
		<br />
		<span class="description"><?php _e( 'Your unique API key for CloudFlare that you can get at your account settings page.', 'cloudflare-purge' ); ?></span>
		<?php
	}

	/**
	 * Display CloudFlare Email Address settings field.
	 *
	 * @access public
	 */
	public function render_email() {
		$email = get_option( 'cloudflare_purge_api_email_address' );
		?>
		<label for="cloudflare_purge_api_email_address">
		<input type="text" id="cloudflare_purge_api_email_address" class="regular-text ltr" name="cloudflare_purge_api_email_address" value="<?php echo esc_attr( $email ); ?>" />
		</label>
		<br />
		<span class="description"><?php _e( 'The email address that you use with your CloudFlare account.', 'cloudflare-purge' ); ?></span>
		<?php
	}

	/**
	 * Register page for purging all files.
	 *
	 * @access public
	 */
	public function register_purge_all_page() {
        add_submenu_page(
			'options.php',
			__( 'CloudFlare Purge All', 'cloudflare-purge' ),
			__( 'CloudFlare Purge All', 'cloudflare-purge' ),
			'activate_plugins',
			'cloudflare-purge-all',
			array( $this, 'display_purge_all_page' )
		);
	}

	/**
	 * Display settings page.
	 *
	 * @access public
	 */
	public function display_purge_all_page() {
		// Handle submission
		if ( isset( $_GET['action'] )
			&& 'purge-all' == $_GET['action']
			&& wp_verify_nonce( $_GET['_wpnonce'], 'cloudflare-purge-all' )
			&& current_user_can( 'activate_plugins' )
			) {
			$this->cloudflare_purge->purge_all();

			// Prepare URL for redirection
			$url = add_query_arg( array( 'page' => 'cloudflare-purge-all', 'action' => 'purged' ), admin_url( 'options.php' ) );

			// Redirect to previous page
			wp_safe_redirect( $url );
			exit;
		}

		// Prepare URL for request
		$url = wp_nonce_url( add_query_arg( array( 'page' => 'cloudflare-purge-all', 'action' => 'purge-all' ), admin_url( 'options.php' ) ), 'cloudflare-purge-all' );

		// Display page content
		?>
		<div class="wrap">
			<?php if ( isset( $_GET['action'] )	&& 'purged' == $_GET['action'] ) : ?>
				<div><?php _e( 'Request for purging sent.', 'cloudflare-purge' ); ?></div>
			<?php else : ?>
				<div><?php _e( 'Are you sure you want to purge all URLs from CloudFlare cache?', 'cloudflare-purge' ); ?></div>
				<div><a href="<?php echo $url; ?>" class="button"><?php _e( 'Yes, purge all', 'cloudflare-purge' ); ?></a></div>
			<?php endif; ?>
		</div>
		<?php
	}
}
endif;

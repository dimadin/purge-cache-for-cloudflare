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
		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );
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
	 * Register settings fields.
	 *
	 * @access public
	 */
	public function register_settings() {
		// Get name of the page
		$page = $this->get_settings_page();

		// Get name of the page
		$section = $this->get_settings_section();

		add_settings_field( 'cloudflare_purge_api_key', __( 'CloudFlare API Key',           'cloudflare-purge' ), array( $this, 'render_api_key' ), $page, $section );
		add_settings_field( 'cloudflare_purge_mail',    __( 'CloudFlare API Email Address', 'cloudflare-purge' ), array( $this, 'render_email'   ), $page, $section );

		register_setting( $page, 'cloudflare_purge_api_key', array( $this, 'validate_settings' ) );
		register_setting( $page, 'cloudflare_purge_mail',    array( $this, 'validate_email'    ) );

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
		$email = get_option( 'cloudflare_purge_mail' );
		?>
		<label for="cloudflare_purge_mail">
		<input type="text" id="cloudflare_purge_mail" class="regular-text ltr" name="cloudflare_purge_mail" value="<?php echo esc_attr( $email ); ?>" />
		</label>
		<br />
		<span class="description"><?php _e( 'The email address that you use with your CloudFlare account.', 'cloudflare-purge' ); ?></span>
		<?php
	}

	/**
	 * Validate settings fields submission.
	 *
	 * Make submitted value a string.
	 *
	 * @access public
	 *
	 * @param  string $setting     Setting value.
	 * @return string $new_setting Validated setting value.
	 */
	public function validate_settings( $setting ) {
		$new_setting = (string) $setting;
		return $new_setting;
	}

	/**
	 * Validate email setting field submission.
	 *
	 * Check if submitted value is email address
	 * and remove it if it isn't.
	 *
	 * @access public
	 *
	 * @param  string $setting     Setting value.
	 * @return string $new_setting Validated setting value.
	 */
	public function validate_email( $settings ) {
		if ( ! is_email( $settings ) ) {
			return '';
		}

		return $settings;
	}
}
endif;

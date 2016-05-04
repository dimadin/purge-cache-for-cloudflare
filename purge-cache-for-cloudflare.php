<?php

/**
 * The Purge Cache for CloudFlare Plugin
 *
 * Simple full HTML page cache purger for CloudFlare.
 *
 * @package    Purge_Cache_for_CloudFlare
 * @subpackage Main
 */

/**
 * Plugin Name: Purge Cache for CloudFlare
 * Plugin URI:  http://blog.milandinic.com/wordpress/plugins/purge-cache-for-cloudflare/
 * Description: Simple full HTML page cache purger for CloudFlare.
 * Author:      Milan Dinić
 * Author URI:  http://blog.milandinic.com/
 * Version:     1.1
 * Text Domain: purge-cache-for-cloudflare
 * Domain Path: /languages/
 * License:     GPL
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) exit;

// Load dependencies
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

/*
 * Initialize a plugin.
 *
 * Load class when all plugins are loaded
 * so that other plugins can overwrite it.
 */
add_action( 'plugins_loaded', array( 'Purge_Cache_for_CloudFlare', 'plugins_loaded' ), 10 );

if ( ! class_exists( 'Purge_Cache_for_CloudFlare' ) ) :
/**
 * Purge Cache for CloudFlare main class.
 *
 * Simple full HTML page cache purger for CloudFlare.
 */
class Purge_Cache_for_CloudFlare {
	/**
	 * CloudFlare API base endpoint.
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @var string
	 */
	protected $base_endpoint = 'https://api.cloudflare.com/client/v4/';

	/**
	 * Path to plugin's directory.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Plugin's basename.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @var string
	 */
	public $basename;

	/**
	 * CloudFlare API key.
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * CloudFlare email.
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @var string
	 */
	protected $email;

	/**
	 * Set class properties and add main methods to appropriate hooks.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		// Set path
		$this->path = rtrim( plugin_dir_path( __FILE__ ), '/' );

		// Set basename
		$this->basename = plugin_basename( __FILE__ );

		// Set CloudFlare API values
		$this->api_key = get_option( 'purge_cache_for_cloudflare_api_key'           );
		$this->email   = get_option( 'purge_cache_for_cloudflare_api_email_address' );

		// Load translations
		load_plugin_textdomain( 'purge-cache-for-cloudflare', false, dirname( $this->basename ) . '/languages' );

		// Delete expired URLs from option
		add_action( 'wp_scheduled_delete',    array( $this, 'purge_expired_urls'     )        );

		// Purge when post is transitioned
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );

		// Purge all files on shutdown
		add_action( 'shutdown',               array( $this, 'maybe_purge'            ), 10    );

		// Rewrite all links to include nocache string
		add_action( 'wp_loaded',              array( $this, 'nocache_start_ob'       ), 1     );

		// Include and load admin class
		add_action( 'admin_menu',             array( $this, 'admin_menu'             ), 1     );

		// Add caching headers
		add_filter( 'wp_headers',             array( $this, 'wp_headers'             ), 10    );

		// Don't cache search pages
		add_filter( 'wp_headers',             array( $this, 'nocache_search'         ), 11, 2 );

		// Register plugins action links filter
		add_filter( 'plugin_action_links',               array( $this, 'action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'action_links' ), 10, 2 );

		// Register plugin row meta link filter
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );

		// Empty data for current commenter
		add_filter( 'wp_get_current_commenter', array( $this, 'wp_get_current_commenter' ), 10 );

		// Clean expired temporaries
		add_action( 'wp_scheduled_delete', array( 'WP_Temporary', 'clean' ) );
	}

	/**
	 * Initialize Purge_Cache_for_CloudFlare object.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return Purge_Cache_for_CloudFlare $instance Instance of Purge_Cache_for_CloudFlare class.
	 */
	public static function &get_instance() {
		static $instance = false;

		if ( !$instance ) {
			$instance = new Purge_Cache_for_CloudFlare;
		}

		return $instance;
	}

	/**
	 * Load plugin.
	 *
	 * @since 1.0
	 * @access public
	 */
	public static function plugins_loaded() {
		// Initialize class
		$purge_cache_for_cloudflare = Purge_Cache_for_CloudFlare::get_instance();
	}

	/**
	 * Load admin class.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function admin_menu() {
		require_once $this->path . '/inc/class-purge-cache-for-cloudflare-admin.php';

		Purge_Cache_for_CloudFlare_Admin::get_instance();
	}

	/**
	 * Add action links to plugins page.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param array  $links       Existing plugin's action links.
	 * @param string $plugin_file Path to the plugin file.
	 * @return array $links New plugin's action links.
	 */
	public function action_links( $links, $plugin_file ) {
		// Check if it is for this plugin
		if ( $this->basename != $plugin_file ) {
			return $links;
		}

		$links['settings'] = '<a href="' . esc_url( $this->settings_page_url() ) . '">' . _x( 'Settings', 'plugin actions link', 'purge-cache-for-cloudflare' ) . '</a>';
		$links['donate']   = '<a href="http://blog.milandinic.com/donate/">' . __( 'Donate', 'purge-cache-for-cloudflare' ) . '</a>';
		$links['wpdev']    = '<a href="http://blog.milandinic.com/wordpress/custom-development/">' . __( 'WordPress Developer', 'purge-cache-for-cloudflare' ) . '</a>';
		$links['premium']  = '<strong><a href="https://shop.milandinic.com/downloads/purge-cache-for-cloudflare-plus/">' . __( 'Premium Version', 'purge-cache-for-cloudflare' ) . '</a></strong>';

		return $links;
	}

	/**
	 * Add row meta links to plugins page.
	 *
	 * @since 1.2
	 * @access public
	 *
	 * @param array  $links       Existing plugin's row meta links.
	 * @param string $plugin_file Path to the plugin file.
	 * @return array $links New plugin's row meta links.
	 */
	public function row_meta( $links, $plugin_file ) {
		// Check if it is for this plugin
		if ( $this->basename != $plugin_file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( add_query_arg( array( 'page' => 'cloudflare-purge-all' ), admin_url( 'options.php' ) ) ) . '">' . _x( 'Purge All', 'plugin actions link', 'purge-cache-for-cloudflare' ) . '</a>';

		return $links;
	}

	/**
	 * Check if CloudFlare API user has reached limit.
	 *
	 * If user passed CloudFlare API limits, return false,
	 * otherwise return number of request in current period.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return bool|int Number of made requests or false if reached limit.
	 */
	public function can_fetch() {
		// Check how much requests are already performed in this interval
		$requests = WP_Temporary::get( 'purge_cache_for_cloudflare_requests_limit' );
		if ( ! $requests ) {
			$requests = 1;
		}

		if ( 1200 > $requests ) {
			return $requests;
		} else {
			return false;
		}
	}

	/**
	 * Get page cache timeout.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return int $timeout Value of cache timeout. Default 1800.
	 */
	public function cache_timeout() {
		/**
		 * Filter value of cache timeout.
		 *
		 * @since 1.0
		 *
		 * @param int $timeout Value of cache timeout. Default 1800.
		 */
		$timeout = absint( apply_filters( 'purge_cache_for_cloudflare_cache_timeout', 30 * MINUTE_IN_SECONDS ) );

		return $timeout;
	}

	/**
	 * Get nocache string.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return string $nocache_string Value of nocache string. Default 'wp-'.
	 */
	public function nocache_string() {
		/**
		 * Filter value of nocache string.
		 *
		 * @since 1.0
		 *
		 * @param string $nocache_string Value of nocache string. Default 'wp-'.
		 */
		$nocache_string = apply_filters( 'purge_cache_for_cloudflare_nocache_string', 'wp-' );

		return $nocache_string;
	}

	/**
	 * Get URL of settings page.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return string $url URL of settings page.
	 *                     Default URL of Settings > Writting.
	 */
	public function settings_page_url() {
		$url = admin_url( 'options-writing.php' );

		/**
		 * Filter value of settings page URL.
		 *
		 * @since 1.0
		 *
		 * @param string $url Value of settings page URL.
		 *                     Default URL of Settings > Writting.
		 */
		$url = apply_filters( 'purge_cache_for_cloudflare_settings_page_url', $url );

		return $url;
	}

	/**
	 * Make CloudFlare API request.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string $endpoint Endpoint of CloudFlare API URL.
	 * @param array  $args     Request arguments.
	 * @return WP_Error|array $response The response or WP_Error on failure.
	 */
	public function request( $endpoint, $args ) {
		// Have we passed limit
		$requests = $this->can_fetch();

		if ( ! $requests ) {
			return new WP_Error( 'purge-cache-for-cloudflare-requests-limit', __( 'Requests limit passed.', 'purge-cache-for-cloudflare' ), 429 );
		}

		// Save new number of requests in this interval
		WP_Temporary::update( 'purge_cache_for_cloudflare_requests_limit', $requests + 1, 15 * MINUTE_IN_SECONDS );

		$defaults = array(
			'headers' => array(
				'X-Auth-Email' => $this->email,
				'X-Auth-Key'   => $this->api_key,
				'Content-Type' => 'application/json',
			),
		);

		$r = wp_parse_args( $args, $defaults );

		$response = wp_remote_request( $this->base_endpoint . $endpoint, $r );

		return $response;
	}

	/**
	 * Get unique zone data for a domain.
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @param string $type Type of data for a CloudFlare zone current domain belongs to.
	 * @return string $zone_data Data of a CloudFlare zone current domain belongs to.
	 */
	protected function get_zone_data( $type ) {
		// If not cached, get raw
		if ( false === ( $zone_data = get_transient( 'purge_cache_for_cloudflare_zone_data' ) ) ) {
			$domain = parse_url( site_url(), PHP_URL_HOST );

			$response = $this->request( 'zones?name?' . $domain, array( 'method' => 'GET' ) );

			// Response should have appropiate code
			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
				return;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ) );

			$zone_id   = $response_body->result[0]->id;
			$zone_plan = $response_body->result[0]->plan->legacy_id;

			$zone_data = array( 'zone_id' => $zone_id, 'zone_plan' => $zone_plan );

			// Save to cache for an hour
			set_transient( 'purge_cache_for_cloudflare_zone_data', $zone_data, HOUR_IN_SECONDS );
		}

		if ( isset( $zone_data[ $type ] ) ) {
			return $zone_data[ $type ];
		} else {
			return '';
		}
	}

	/**
	 * Get unique zone ID for a domain.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return int $zone_id ID of a CloudFlare zone current domain belongs to.
	 */
	public function get_zone_id() {
		return $this->get_zone_data( 'zone_id' );
	}

	/**
	 * Get plan of the zone for a domain.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return int $zone_plan Plan of a CloudFlare zone current domain belongs to.
	 */
	public function get_zone_plan() {
		return $this->get_zone_data( 'zone_plan' );
	}

	/**
	 * Purge files from CloudFlare cache.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return WP_Error|array $response The response or WP_Error on failure.
	 */
	public function purge_urls() {
		if ( ! $urls = $this->get_urls() ) {
			return;
		}

		$args = array(
			'method' => 'DELETE',
			'body'   => json_encode(
				array(
					'files' => $urls,
				)
			),
		);

		$response = $this->request( 'zones/' . $this->get_zone_id() . '/purge_cache', $args );

		// If response has appropiate code, delete URLs
		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$this->remove_urls( $urls );
		}

		return $response;
	}

	/**
	 * Purge all files from CloudFlare cache.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return WP_Error|array $response The response or WP_Error on failure.
	 */
	public function purge_all() {
		$args = array(
			'method' => 'DELETE',
			'body'   => json_encode(
				array(
					'purge_everything' => true,
				)
			),
		);

		$response = $this->request( 'zones/' . $this->get_zone_id() . '/purge_cache', $args );

		return $response;
	}

	/**
	 * Purge basic cache URLs when post is transitioned.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post Post object.
	 */
	public function transition_post_status( $new_status, $old_status, $_post ) {
		// If new or old is 'publish'
		if ( 'publish' != $new_status ) {
			if ( 'publish' != $old_status ) {
				return;
			}
		}

		// Add post permalink
		$this->set_url( get_permalink( $_post->ID ) );

		// Add home page URL
		$this->set_url( home_url( '/' ) );

		// Add main feed URL
		$this->set_url( get_feed_link() );

		/**
		 * Fires when a post is transitioned from one status to another.
		 *
		 * @since 1.0
		 *
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $_post      Post object.
		 */
		do_action( 'purge_cache_for_cloudflare_transition_post_status', $new_status, $old_status, $_post );
	}

	/**
	 * Set additional headers for caching.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param array $headers The list of headers to be sent.
	 * @return array $headers Modifies list of headers to be sent.
	 */
	public function wp_headers( $headers ) {
		if ( ! is_user_logged_in() ) {
			// Allow value of cache timeout to be filtered
			$timeout = $this->cache_timeout();

			$headers['Cache-Control'] = 'public, max-age=' . $timeout;
		}

		return $headers;
	}

	/**
	 * Set headers that prevent caching for search.
	 *
	 * @since 1.2
	 * @access public
	 *
	 * @param array $headers The list of headers to be sent.
	 * @param WP    $wp      Current WordPress environment instance.
	 * @return array $headers Modifies list of headers to be sent.
	 */
	public function nocache_search( $headers, $wp ) {
		if ( isset( $wp->query_vars['s'] ) && $wp->query_vars['s'] ) {
			$headers = array_merge( $headers, wp_get_nocache_headers() );
		}

		return $headers;
	}

	/**
	 * Set URL whose cache should be purged.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string $url URL whose cache should be purged.
	 * @param int    $priority Optional. Priority of the URL.
	 *                          Default 1.
	 */
	public function set_url( $url, $priority = 1 ) {
		// URL can be empty
		if ( ! $url ) {
			return;
		}

		// Get existing value
		$option = get_option( 'purge_cache_for_cloudflare_urls', array() );

		$option[] = array(
			'url'      => $url,
			'priority' => $priority,
			'expires'  => time() + $this->cache_timeout(),
		);

		update_option( 'purge_cache_for_cloudflare_urls', $option );
	}

	/**
	 * Get an array uf URLs whose cache should be purged.
	 *
	 * Always return from top based on priority.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return array $urls An array of URLs that should be purged,
	 *                      sorted by priority.
	 */
	public function get_urls() {
		$urls = array();

		// Get existing value
		$option = get_option( 'purge_cache_for_cloudflare_urls', array() );

		if ( ! $option ) {
			return $urls;
		}

		$priority = 1;
		$count = 0;
		$time = time();

		while ( $count < 30 ) {
			// priority should be 1-4
			if ( $priority > 4 ) {
				break;
			}

			foreach ( $option as $key => $entry ) {
				// Check if counted
				if ( $count > 29 ) {
					break 2;
				}

				// Check if entry expired
				if ( $time > $entry['expires'] ) {
					continue;
				}

				// Check if entry is in priority
				if ( $priority != $entry['priority'] ) {
					continue;
				}

				$urls[] = $entry['url'];
				$count++;
			}

			$priority++;
		}

		// Only return unique URLs
		$urls = array_unique( $urls );

		// Create new array of URLs
		$new_key = 0;
		$new_urls = array();

		foreach ( $urls as $url ) {
			$new_urls[ $new_key ] = $url;
			$new_key++;
		}

		return $new_urls;
	}

	/**
	 * Remove an array uf URLs from previous array.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param array $urls An array of URLs that should be removed.
	 */
	public function remove_urls( $urls ) {
		// Get existing value
		$option = get_option( 'purge_cache_for_cloudflare_urls', array() );

		$time = time();

		foreach ( $option as $key => $entry ) {
			// Check if URL is in entry
			if ( in_array( $entry['url'], $urls ) ) {
				unset( $option[ $key ] );
			}

			// Check if entry expired
			if ( $time > $entry['expires'] ) {
				unset( $option[ $key ] );
			}
		}

		// Create new option array
		$new_key = 0;
		$new_option = array();

		foreach ( $option as $key => $entry ) {
			$new_option[ $new_key ] = $entry;
			$new_key++;
		}

		update_option( 'purge_cache_for_cloudflare_urls', $new_option );
	}

	/**
	 * Purge expired URLs from database option.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function purge_expired_urls() {
		$this->remove_urls( array() );
	}

	/**
	 * Purge if there is database option with URLs.
	 *
	 * @access public
	 */
	public function maybe_purge() {
		if ( get_option( 'purge_cache_for_cloudflare_urls' ) && $this->can_fetch() ) {
			$this->purge_urls();
		}
	}

	/**
	 * Return empty values for current commenter data.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param array $comment_author_data {
	 *     Array of arguments of current commenter.
	 *
	 *     @type string $comment_author       The name of the author of the comment. Default empty.
	 *     @type string $comment_author_email The email address of the `$comment_author`. Default empty.
	 *     @type string $comment_author_url   The URL address of the `$comment_author`. Default empty.
	 * }
	 * @return array $comment_author_data Modified array of arguments of current commenter.
	 */
	public function wp_get_current_commenter( $comment_author_data ) {
		// Return standard when comment is just posted
		$nocache = $this->nocache_string() . 'nocache';

		if ( isset( $_GET[ $nocache ] )
			&& ( 'true' == $_GET[ $nocache ] )
			&& isset( $_GET['comment-posted'] )
			&& ( 'true' == $_GET['comment-posted'] )
		) {
			return $comment_author_data;
		}

		// Otherwise, return empty data
		$comment_author_data = array(
			'comment_author'       => '',
			'comment_author_email' => '',
			'comment_author_url'   => '',
		);

		return $comment_author_data;
	}

	/**
	 * Catch response to rewrite all links to nocache variants.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function nocache_start_ob() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Hook end catcher and rewriter
		add_action( 'wp_footer',    array( $this, 'nocache_end_ob' ), 999 );
		add_action( 'admin_footer', array( $this, 'nocache_end_ob' ), 999 );

		ob_start();
	}

	/**
	 * Rewrite all links in a response to nocache variants.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function nocache_end_ob() {
		$content = ob_get_clean();

		if ( ! $content ) {
			return;
		}

		$domain = parse_url( site_url(), PHP_URL_HOST );

		$new_content = $content;

		// Disable libxml errors and store them in internal buffer per http://stackoverflow.com/a/26853864
		libxml_use_internal_errors( true );

		// Get all links in a post via http://stackoverflow.com/a/1519791
		$doc = new DOMDocument();
		$doc->loadHTML( $content );

		$xpath = new DOMXPath( $doc );
		$nodeList = $xpath->query( '//a/@href' );

		for ( $i = 0; $i < $nodeList->length; $i++ ) {
			// Xpath query for attributes gives a NodeList containing DOMAttr objects.
			// http://php.net/manual/en/class.domattr.php
			$old_url = $nodeList->item( $i )->value;

			// If there is link to admin or external site, continue
			if ( strpos( $old_url, 'wp-' ) || ! strpos( $old_url, $domain ) ) {
				continue;
			}

			// Form new URL by suffixing it with nocache string
			$new_url = add_query_arg( $this->nocache_string() . 'nocache', 'true', $old_url );

			// URL can be surrounded by both types
			$new_content = str_replace( '"' . $old_url . '"', '"' . $new_url . '"', $new_content );
			$new_content = str_replace( "'" . $old_url . "'", "'" . $new_url . "'", $new_content );
		}

		// Clear libxml error buffer
		libxml_clear_errors();

		echo $new_content;
	}
}
endif;

<?php

/**
 * The CloudFlare® Purge Plugin
 *
 * Simple full HTML page cache purger for CloudFlare®.
 *
 * @package    CloudFlare_Purge
 * @subpackage Main
 */

/**
 * Plugin Name: CloudFlare® Purge
 * Plugin URI:  http://blog.milandinic.com/wordpress/plugins/
 * Description: Simple full HTML page cache purger for CloudFlare®.
 * Author:      Milan Dinić
 * Author URI:  http://blog.milandinic.com/
 * Version:     0.4-beta-1
 * Text Domain: cloudflare-purge
 * Domain Path: /languages/
 * License:     GPL
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) exit;

// Load dependencies
require __DIR__ . '/vendor/autoload.php';

/*
 * Initialize a plugin.
 *
 * Load class when all plugins are loaded
 * so that other plugins can overwrite it.
 */
add_action( 'plugins_loaded', array( 'CloudFlare_Purge', 'plugins_loaded' ), 10 );

if ( ! class_exists( 'CloudFlare_Purge' ) ) :
/**
 * CloudFlare Purge main class.
 *
 * Simple full HTML page cache purger for CloudFlare.
 */
class CloudFlare_Purge {
	/**
	 * CloudFlare API base endpoint.
	 *
	 * @access protected
	 *
	 * @var string
	 */
	protected $base_endpoint = 'https://api.cloudflare.com/client/v4/';

	/**
	 * Path to plugin's directory.
	 *
	 * @access public
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Plugin's basename.
	 *
	 * @access public
	 *
	 * @var string
	 */
	public $basename;

	/**
	 * CloudFlare API key.
	 *
	 * @access protected
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * CloudFlare email.
	 *
	 * @access protected
	 *
	 * @var string
	 */
	protected $email;

	/**
	 * Set class properties and add main methods to appropriate hooks.
	 *
	 * @access public
	 */
	public function __construct() {
		// Set path
		$this->path = rtrim( plugin_dir_path( __FILE__ ), '/' );

		// Set basename
		$this->basename = plugin_basename( __FILE__ );

		// Set CloudFlare API values
		$this->api_key = get_option( 'cloudflare_purge_api_key' );
		$this->email   = get_option( 'cloudflare_purge_mail' );

		// Load translations
		load_plugin_textdomain( 'cloudflare-purge', false, dirname( $this->basename ) . '/languages' );

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

		// Register plugins action links filter
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'action_links' ) );

		// Empty data for current commenter
		add_filter( 'wp_get_current_commenter', array( $this, 'wp_get_current_commenter' ), 10 );

		// Clean expired temporaries
		add_action( 'wp_scheduled_delete', array( 'WP_Temporary', 'clean' ) );
	}

	/**
	 * Initialize CloudFlare_Purge object.
	 *
	 * @access public
	 *
	 * @return CloudFlare_Purge $instance Instance of CloudFlare_Purge class.
	 */
	public static function &get_instance() {
		static $instance = false;

		if ( !$instance ) {
			$instance = new CloudFlare_Purge;
		}

		return $instance;
	}

	/**
	 * Load plugin.
	 *
	 * @access public
	 */
	public static function plugins_loaded() {
		// Initialize class
		$cloudflare_purge = CloudFlare_Purge::get_instance();
	}

	/**
	 * Load admin class.
	 *
	 * @access public
	 */
	public function admin_menu() {
		require_once $this->path . '/inc/class-cloudflare-purge-admin.php';

		CloudFlare_Purge_Admin::get_instance();
	}

	/**
	 * Add action links to plugins page.
	 *
	 * @access public
	 *
	 * @param  array $links Existing plugin's action links.
	 * @return array $links New plugin's action links.
	 */
	public function action_links( $links ) {
		$links['donate']   = '<a href="http://blog.milandinic.com/donate/">' . __( 'Donate', 'cloudflare-purge' ) . '</a>';
		$links['settings'] = '<a href="' . esc_url( $this->settings_page_url() ) . '">' . _x( 'Settings', 'plugin actions link', 'cloudflare-purge' ) . '</a>';
		$links['purgeall'] = '<a href="' . esc_url( add_query_arg( array( 'page' => 'cloudflare-purge-all' ), admin_url( 'options.php' ) ) ) . '">' . _x( 'Purge All', 'plugin actions link', 'cloudflare-purge' ) . '</a>';

		return $links;
	}

	/**
	 * Check if CloudFlare API user has reached limit.
	 *
	 * If user passed CloudFlare API limits, return false,
	 * otherwise return number of request in current period.
	 *
	 * @access public
	 *
	 * @return bool|int Number of made requests or false if reached limit.
	 */
	public function can_fetch() {
		// Check how much requests are already performed in this interval
		$requests = WP_Temporary::get( 'cloudflare_purge_requests_limit' );
		if ( ! $requests ) {
			$requests = 1;
		}

		if ( 1200 > $limit ) {
			return $requests;
		} else {
			return false;
		}
	}

	/**
	 * Get page cache timeout.
	 *
	 * @access public
	 *
	 * @return int $timeout Value of cache timeout. Default 1800.
	 */
	public function page_cache_timeout() {
		/**
		 * Filter value of cache timeout.
		 *
		 * @param int   $timeout Value of cache timeout. Default 1800.
		 * @param array $headers An array of existing headers.
		 */
		$timeout = absint( apply_filters( 'cloudflare_purge_cache_timeout', 30 * MINUTE_IN_SECONDS, $headers ) );

		return $timeout;
	}

	/**
	 * Get nocache string.
	 *
	 * @access public
	 *
	 * @return string $nocache_string Value of nocache string. Default 'wp-'.
	 */
	public function nocache_string() {
		/**
		 * Filter value of nocache string.
		 *
		 * @param string $nocache_string Value of nocache string. Default 'wp-'.
		 */
		$nocache_string = apply_filters( 'cloudflare_purge_nocache_string', 'wp-' );

		return $nocache_string;
	}

	/**
	 * Get URL of settings page.
	 *
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
		 * @param string $url Value of settings page URL.
		 *                     Default URL of Settings > Writting.
		 */
		$url = apply_filters( 'cloudflare_purge_settings_page_url', $url );

		return $url;
	}

	/**
	 * Make CloudFlare API request.
	 *
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
			return new WP_Error( 'cloudflare-purge-requests-limit', __( 'Requests limit passed.' ), 429 );
		}

		$defaults = array(
			'headers' => array(
				'X-Auth-Email' => $this->email,
				'X-Auth-Key'   => $this->api_key,
				'Content-Type' => 'application/json',
			),
		);

		$r = wp_parse_args( $args, $defaults );

		$response = wp_remote_request( $this->base_endpoint . $endpoint, $r );

		// Save new number of requests in this interval
		WP_Temporary::update( 'cloudflare_purge_requests_limit', $requests + 1, 15 * MINUTE_IN_SECONDS );

		return $response;
	}

	/**
	 * Get unique zone ID for a domain.
	 *
	 * @access public
	 *
	 * @return int $zone_id ID of a CloudFlare zone current domain belongs to.
	 */
	public function get_zone_id() {
		// If not cached, get raw
		if ( false === ( $zone_id = get_transient( 'cloudflare_purge_zone_id' ) ) ) {
			$domain = parse_url( site_url(), PHP_URL_HOST );

			$response = $this->request( 'zones?name?' . $domain, array( 'method' => 'GET' ) );

			// Response should have appropiate code
			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
				return;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ) );

			$zone_id = $response_body->result[0]->id;

			// Save to cache for an hour
			set_transient( 'cloudflare_purge_zone_id', $zone_id, HOUR_IN_SECONDS );
		}

		return $zone_id;
	}

	/**
	 * Purge files from CloudFlare cache.
	 *
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
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $_post      Post object.
		 */
		do_action( 'cloudflare_purge_transition_post_status', $new_status, $old_status, $_post );
	}

	/**
	 * Set additional headers for caching.
	 *
	 * @access public
	 *
	 * @param array $headers The list of headers to be sent.
	 * @return array $headers Modifies list of headers to be sent.
	 */
	public function wp_headers( $headers ) {
		if ( ! is_user_logged_in() ) {
			// Allow value of cache timeout to be filtered
			$timeout = $this->page_cache_timeout();

			$headers['Cache-Control'] = 'public, max-age=' . $timeout;
		}

		return $headers;
	}

	/**
	 * Set URL whose cache should be purged.
	 *
	 * TODO: only unique URLs
	 *
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
		$option = get_option( 'cloudflare_purge_urls', array() );

		$option[] = array(
			'url'      => $url,
			'priority' => $priority,
			'expires'  => time() + $this->page_cache_timeout(),
		);

		update_option( 'cloudflare_purge_urls', $option );
	}

	/**
	 * Get an array uf URLs whose cache should be purged.
	 *
	 * Always return from top based on priority.
	 *
	 * @access public
	 *
	 * @return array $urls An array of URLs that should be purged,
	 *                      sorted by priority.
	 */
	public function get_urls() {
		$urls = array();

		// Get existing value
		$option = get_option( 'cloudflare_purge_urls', array() );

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

		return $urls;
	}

	/**
	 * Remove an array uf URLs from previous array.
	 *
	 * @access public
	 *
	 * @param array $urls An array of URLs that should be removed.
	 */
	public function remove_urls( $urls ) {
		// Get existing value
		$option = get_option( 'cloudflare_purge_urls', array() );

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

		update_option( 'cloudflare_purge_urls', $new_option );
	}

	/**
	 * Purge expired URLs from database option.
	 *
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
		if ( get_option( 'cloudflare_purge_urls' ) && $this->can_fetch() ) {
			$this->purge_urls();
		}
	}

	/**
	 * Return empty values for current commenter data.
	 *
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

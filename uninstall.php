<?php

/**
 * The Purge Cache for CloudFlare® Plugin
 *
 * Code used when the plugin is deleted.
 *
 * @package    Purge_Cache_for_CloudFlare
 * @subpackage Unistall
 */

/* Exit if accessed directly or not in unistall */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Load dependencies
require __DIR__ . '/vendor/autoload.php';

/*
 * Remove options on uninstallation of plugin.
 *
 * @since 1.0
 */
delete_option( 'purge_cache_for_cloudflare_api_key'           );
delete_option( 'purge_cache_for_cloudflare_api_email_address' );
delete_option( 'purge_cache_for_cloudflare_urls'              );

/*
 * Clean expired temporaries on uninstallation of plugin.
 *
 * @since 1.0
 */
WP_Temporary::clean();

/*
 * Delete temporaries on uninstallation of plugin.
 *
 * @since 1.0
 */
WP_Temporary::delete( 'purge_cache_for_cloudflare_requests_limit' );

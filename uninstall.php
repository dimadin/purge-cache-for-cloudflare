<?php

/**
 * The CloudFlare® Purge Plugin
 *
 * Code used when the plugin is deleted.
 *
 * @package    CloudFlare_Purge
 * @subpackage Unistall
 */

/* Exit if accessed directly or not in unistall */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Load dependencies
require __DIR__ . '/vendor/autoload.php';

/*
 * Remove options on uninstallation of plugin.
 */
delete_option( 'cloudflare_purge_api_key' );
delete_option( 'cloudflare_purge_mail'    );
delete_option( 'cloudflare_purge_urls'    );

/*
 * Clean expired temporaries on uninstallation of plugin.
 */
WP_Temporary::clean();

/*
 * Delete temporaries on uninstallation of plugin.
 */
WP_Temporary::delete( 'cloudflare_purge_requests_limit' );

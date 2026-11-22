<?php
/**
 * Removes everything the plugin stored.
 *
 * WordPress runs this file when the plugin is deleted from the Plugins screen.
 * Each option the plugin writes is deleted here, in the same change that
 * adds it.
 *
 * @package SitePwa
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'site_pwa_settings' );
delete_option( 'site_pwa_rewrite_revision' );
delete_site_transient( 'site_pwa_github_update' );

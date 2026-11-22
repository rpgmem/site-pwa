<?php
/**
 * Plugin Name:       Site PWA
 * Plugin URI:        https://github.com/rpgmem/site-pwa
 * Description:       Turns the site into an installable app: a home-screen icon, an app shell for signed-in users and screens other plugins register.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.3
 * Author:            Alex Meusburger
 * Author URI:        https://github.com/rpgmem
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       site-pwa
 * Domain Path:       /languages
 *
 * @package SitePwa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The plugin version, beside the header WordPress reads as text. The two are
 * bumped together; readme.txt's Stable tag is the third site.
 */
define( 'SITE_PWA_VERSION', '0.1.0' );

/*
 * Inline autoloader for the SitePwa\ namespace, so the plugin
 * ships no Composer runtime. A class maps to a kebab-case file under src/:
 *
 *   SitePwa\Plugin             -> src/plugin.php
 *   SitePwa\Update\GithubUpdater -> src/update/github-updater.php
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SitePwa\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$parts    = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$basename = array_pop( $parts );
		$file     = strtolower( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $basename ) );
		$dir      = $parts ? implode( '/', array_map( 'strtolower', $parts ) ) . '/' : '';
		$path     = __DIR__ . '/src/' . $dir . $file . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

\SitePwa\Plugin::boot( __FILE__ );

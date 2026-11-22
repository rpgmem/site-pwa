<?php

declare(strict_types=1);

namespace SitePwa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The plugin never names the login page by its path: a site that moves it
 * (a security plugin, a custom login page) filters `wp_login_url()`, and a
 * literal `wp-login.php` would send people past that. The service worker's
 * list of paths it never touches is the one place the name may appear.
 *
 * @coversNothing
 */
final class LoginUrlTest extends TestCase {

	public function test_no_php_or_template_names_the_login_page_by_its_path(): void {
		$root  = dirname( __DIR__, 2 );
		$files = array( $root . '/site-pwa.php', $root . '/uninstall.php' );
		foreach ( array( '/src', '/templates' ) as $dir ) {
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				$files[] = $file->getPathname();
			}
		}

		$read = 0;
		foreach ( $files as $file ) {
			++$read;
			self::assertStringNotContainsString( 'wp-login.php', (string) file_get_contents( $file ), "{$file} names wp-login.php; use wp_login_url()." );
		}

		$expected = 2 + count( glob( $root . '/src/*.php' ) ?: array() ) + count( glob( $root . '/src/*/*.php' ) ?: array() ) + count( glob( $root . '/templates/*.php' ) ?: array() );
		self::assertSame( $expected, $read, 'The walk and a glob recount disagree; the scan missed files.' );
	}
}

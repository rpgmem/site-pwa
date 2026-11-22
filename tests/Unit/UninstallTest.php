<?php

declare(strict_types=1);

namespace SitePwa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Everything the plugin stores is deleted on uninstall. The scan reads the
 * option names the code passes to update_option()/add_option(), and fails on
 * one this test does not know or uninstall.php does not delete.
 *
 * @coversNothing
 */
final class UninstallTest extends TestCase {

	/**
	 * The option each write expression names. The settings option is written
	 * by options.php through register_setting(), which the scan cannot see,
	 * so it is listed here directly.
	 */
	private const KNOWN = array(
		'Settings::OPTION'        => \SitePwa\Settings::OPTION,
		'self::REVISION_OPTION'   => \SitePwa\App\Routes::REVISION_OPTION,
	);

	public function test_every_known_option_is_deleted(): void {
		$uninstall = (string) file_get_contents( __DIR__ . '/../../uninstall.php' );
		foreach ( self::KNOWN as $option ) {
			self::assertStringContainsString( "delete_option( '{$option}' )", $uninstall, "uninstall.php does not delete {$option}." );
		}
	}

	public function test_the_update_cache_is_deleted(): void {
		$uninstall = (string) file_get_contents( __DIR__ . '/../../uninstall.php' );
		self::assertStringContainsString( "delete_site_transient( '" . \SitePwa\Update\GithubUpdater::CACHE_KEY . "' )", $uninstall );
	}

	public function test_no_option_is_written_outside_the_known_list(): void {
		$found = 0;
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( __DIR__ . '/../../src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			++$found;
			$code = (string) file_get_contents( $file->getPathname() );
			preg_match_all( '/\b(?:update_option|add_option)\s*\(\s*([^,)]+)/', $code, $writes );
			foreach ( $writes[1] as $option ) {
				self::assertArrayHasKey( trim( $option ), self::KNOWN, $file->getPathname() . " writes {$option}; list it here and in uninstall.php." );
			}
		}
		self::assertGreaterThan( 0, $found );
	}
}

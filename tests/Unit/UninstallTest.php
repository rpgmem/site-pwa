<?php

declare(strict_types=1);

namespace SitePwa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Everything the plugin stores is deleted on uninstall: today that is the
 * update cache alone, and the scan fails when the code starts writing an
 * option nobody listed here.
 *
 * @coversNothing
 */
final class UninstallTest extends TestCase {

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
				// The plugin writes no option yet; the first one joins a list here
				// and a delete_option() in uninstall.php in the same change.
				self::fail( $file->getPathname() . " writes {$option}; list it here and in uninstall.php." );
			}
		}
		self::assertGreaterThan( 0, $found );
	}
}

<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\Plugin;
use WP_UnitTestCase;

/**
 * The plugin loads inside a real WordPress and wires its hooks.
 *
 * @covers \SitePwa\Plugin
 */
final class BootTest extends WP_UnitTestCase {

	public function test_the_plugin_boots_from_its_main_file(): void {
		self::assertStringEndsWith( '/' . Plugin::SLUG . '.php', Plugin::file() );
		self::assertStringEndsWith( '/', Plugin::dir() );
	}

	public function test_translations_load_on_init(): void {
		self::assertSame( 10, has_action( 'init', array( Plugin::class, 'loadTextdomain' ) ) );
	}

	public function test_booting_wires_the_translations_and_the_updater(): void {
		remove_all_actions( 'init' );
		remove_all_filters( 'upgrader_pre_download' );

		Plugin::boot( Plugin::file() );

		self::assertSame( 10, has_action( 'init', array( Plugin::class, 'loadTextdomain' ) ) );
		self::assertSame( 10, has_filter( 'upgrader_pre_download', array( \SitePwa\Update\GithubUpdater::class, 'verifyDownload' ) ) );
	}

	public function test_loading_translations_registers_the_languages_directory(): void {
		global $wp_textdomain_registry;
		$saved = $wp_textdomain_registry;
		$spy   = new class() extends \WP_Textdomain_Registry {
			/** @var array<string, string> Domain => path registered. */
			public array $registered = array();

			// phpcs:ignore Squiz.Commenting.FunctionComment.Missing -- spy override of a core method.
			public function set_custom_path( $domain, $path ) {
				$this->registered[ $domain ] = $path;
				parent::set_custom_path( $domain, $path );
			}
		};
		$wp_textdomain_registry = $spy;

		Plugin::loadTextdomain();
		$wp_textdomain_registry = $saved;

		// WordPress 6.7+ loads the catalogue just in time from this path.
		self::assertArrayHasKey( Plugin::SLUG, $spy->registered );
		self::assertStringEndsWith( '/site-pwa/languages', $spy->registered[ Plugin::SLUG ] );
	}

	public function test_the_plugin_url_is_the_directory_url(): void {
		self::assertSame( plugin_dir_url( Plugin::file() ), Plugin::url() );
		self::assertStringEndsWith( '/', Plugin::url() );
	}
}

<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use FreeFormCertificate\Settings\SettingsReader;
use SitePwa\App\Frame;
use SitePwa\App\Pages;
use SitePwa\App\Routes;
use SitePwa\App\Scheme;
use SitePwa\App\ServiceWorker;
use SitePwa\Settings;
use WP_UnitTestCase;

require_once __DIR__ . '/Stubs/ffc-settings-reader.php';

/**
 * The app's light and dark theme: its own choice, or Free Form Certificate's
 * Dark Mode, so the frame and the screens FFC draws inside it agree.
 *
 * @covers \SitePwa\App\Scheme
 * @covers \SitePwa\Settings
 */
final class SchemeTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		SettingsReader::$stored = array();
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		remove_all_filters( 'site_pwa_scheme_sources' );
		parent::tearDown();
	}

	private function settings( array $values ): void {
		update_option( Settings::OPTION, $values + array( 'enabled' => true ) );
	}

	public function test_the_default_follows_ffcs_dark_mode(): void {
		$this->settings( array() );
		self::assertSame( Scheme::FFC, Settings::get()['color_scheme'] );

		foreach ( array( 'off' => 'light', 'on' => 'dark', 'auto' => 'auto' ) as $mode => $scheme ) {
			SettingsReader::$stored = array( 'dark_mode' => $mode );
			self::assertSame( $scheme, Scheme::current(), $mode );
		}
	}

	public function test_ffcs_default_dark_mode_is_light(): void {
		$this->settings( array() );

		self::assertSame( 'light', Scheme::current() );
	}

	public function test_an_unknown_ffc_value_follows_the_device(): void {
		$this->settings( array() );
		SettingsReader::$stored = array( 'dark_mode' => 'sometimes' );

		self::assertSame( 'auto', Scheme::current() );
	}

	public function test_the_apps_own_choice_wins_over_ffc(): void {
		SettingsReader::$stored = array( 'dark_mode' => 'on' );
		foreach ( array( 'auto', 'light', 'dark' ) as $scheme ) {
			$this->settings( array( 'color_scheme' => $scheme ) );
			self::assertSame( $scheme, Scheme::current() );
		}
	}

	public function test_a_source_that_is_gone_follows_the_device(): void {
		$this->settings( array( 'color_scheme' => 'gone' ) );

		self::assertSame( 'auto', Scheme::current() );
	}

	public function test_another_plugin_can_be_a_source_and_cannot_take_an_own_id(): void {
		add_filter(
			'site_pwa_scheme_sources',
			static fn( array $sources ): array => $sources + array(
				'other' => array( 'label' => 'Other', 'read' => static fn(): string => 'dark' ),
				'light' => array( 'label' => 'Shadow', 'read' => static fn(): string => 'dark' ),
				'bad'   => array( 'label' => 'Bad' ),
			)
		);
		$this->settings( array( 'color_scheme' => 'other' ) );

		self::assertSame( array( 'ffc', 'other' ), array_keys( Scheme::sources() ) );
		self::assertSame( 'dark', Scheme::current() );
	}

	public function test_a_source_answering_nothing_usable_follows_the_device(): void {
		add_filter(
			'site_pwa_scheme_sources',
			static fn( array $sources ): array => $sources + array( 'other' => array( 'label' => 'Other', 'read' => static fn() => null ) )
		);
		$this->settings( array( 'color_scheme' => 'other' ) );

		self::assertSame( 'auto', Scheme::current() );
	}

	public function test_a_filter_that_breaks_the_list_leaves_no_source(): void {
		add_filter( 'site_pwa_scheme_sources', '__return_false' );

		self::assertSame( array(), Scheme::sources() );
	}

	public function test_an_empty_choice_is_stored_as_ffc_and_a_value_is_kept_as_a_key(): void {
		self::assertSame( 'ffc', Settings::sanitize( array( 'color_scheme' => '' ) )['color_scheme'] );
		self::assertSame( 'dark', Settings::sanitize( array( 'color_scheme' => 'Dark' ) )['color_scheme'] );
		self::assertSame( 'ffc', Settings::sanitize( array( 'color_scheme' => array( 'x' ) ) )['color_scheme'] );
	}

	public function test_the_frame_and_the_offline_page_carry_the_scheme_class(): void {
		$this->settings( array( 'color_scheme' => 'dark' ) );
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		wp_set_current_user( self::factory()->user->create() );
		set_query_var( Routes::QUERY_VAR, 'home' );

		$html = Frame::render( 'home' );
		set_query_var( Routes::QUERY_VAR, '' );

		self::assertStringContainsString( '<body class="site-pwa-app site-pwa-scheme-dark">', $html );
		self::assertStringContainsString( '<body class="site-pwa site-pwa-scheme-dark">', Pages::render() );
	}

	public function test_changing_the_scheme_renews_the_workers_cache(): void {
		$this->settings( array( 'color_scheme' => 'light' ) );
		$before = ServiceWorker::config()['cache'];

		$this->settings( array( 'color_scheme' => 'dark' ) );

		self::assertNotSame( $before, ServiceWorker::config()['cache'] );
	}
}

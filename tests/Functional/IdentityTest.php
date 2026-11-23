<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use FreeFormCertificate\Core\PublicDesign;
use SitePwa\App\Frame;
use SitePwa\App\Identity;
use SitePwa\App\Routes;
use SitePwa\App\Login;
use SitePwa\App\Manifest;
use SitePwa\App\Pages;
use SitePwa\App\ServiceWorker;
use SitePwa\Settings;
use WP_UnitTestCase;

require_once __DIR__ . '/Stubs/ffc-public-design.php';

/**
 * The app's look: its own theme colour and corners, or another plugin's
 * design taken as the source, and every place that draws it.
 *
 * @covers \SitePwa\App\Identity
 * @covers \SitePwa\Settings
 */
final class IdentityTest extends WP_UnitTestCase {

	private const FFC_ON = array(
		'enabled'     => true,
		'accent'      => '#B32D2E',
		'radius'      => 2,
		'card_radius' => 20,
	);

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles'] = null;
		PublicDesign::$stored = array( 'enabled' => false, 'accent' => '#2271b1', 'radius' => 4, 'card_radius' => 8 );
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		remove_all_filters( 'site_pwa_identity_sources' );
		parent::tearDown();
	}

	private function settings( array $values ): void {
		update_option( Settings::OPTION, $values + array( 'enabled' => true, 'theme_color' => '#1d5e8c' ) );
	}

	public function test_the_own_look_is_the_theme_colour_and_the_stylesheets_corners(): void {
		$this->settings( array() );

		self::assertSame(
			array( 'accent' => '#1d5e8c', 'radius' => 12, 'control_radius' => 8, 'source' => 'own' ),
			Identity::current()
		);
	}

	public function test_free_form_certificate_is_a_source_when_its_class_is_loaded(): void {
		$sources = Identity::sources();

		self::assertArrayHasKey( 'ffc', $sources );
		self::assertSame( 'Free Form Certificate: Public Design', $sources['ffc']['label'] );
	}

	public function test_the_public_design_gives_the_accent_and_both_corners_while_it_is_on(): void {
		$this->settings( array( 'identity_source' => 'ffc' ) );
		PublicDesign::$stored = self::FFC_ON;

		self::assertSame(
			array( 'accent' => '#b32d2e', 'radius' => 20, 'control_radius' => 2, 'source' => 'ffc' ),
			Identity::current()
		);
	}

	public function test_a_switched_off_design_falls_back_to_the_own_look(): void {
		$this->settings( array( 'identity_source' => 'ffc' ) );

		self::assertSame( 'own', Identity::current()['source'] );
		self::assertNull( Identity::readFfc() );
	}

	public function test_a_source_that_is_gone_falls_back_and_stays_stored(): void {
		$this->settings( array( 'identity_source' => 'retired-plugin' ) );

		self::assertSame( 'retired-plugin', Settings::get()['identity_source'] );
		self::assertSame( 'own', Identity::current()['source'] );
	}

	public function test_a_source_value_out_of_bounds_or_malformed_takes_the_own_value(): void {
		$this->settings( array( 'identity_source' => 'other' ) );
		add_filter(
			'site_pwa_identity_sources',
			static fn( array $sources ): array => $sources + array(
				'other'  => array(
					'label' => 'Other',
					'read'  => static fn(): array => array( 'accent' => 'red;}body{x', 'radius' => 99, 'control_radius' => 'wide' ),
				),
				'own'    => array( 'label' => 'Takeover', 'read' => '__return_null' ),
				'broken' => array( 'label' => 'No reader' ),
			)
		);

		$look = Identity::current();

		self::assertSame( '#1d5e8c', $look['accent'] );
		self::assertSame( Identity::RADIUS_MAX, $look['radius'] );
		self::assertSame( 8, $look['control_radius'] );
		self::assertSame( array( 'ffc', 'other' ), array_keys( Identity::sources() ) );
	}

	public function test_a_filter_that_returns_nothing_usable_leaves_no_sources(): void {
		add_filter( 'site_pwa_identity_sources', '__return_false' );

		self::assertSame( array(), Identity::sources() );
	}

	public function test_the_css_carries_the_accent_and_the_corners(): void {
		$this->settings( array( 'identity_source' => 'ffc' ) );
		PublicDesign::$stored = self::FFC_ON;

		self::assertSame(
			':root{--site-pwa-radius:20px;--site-pwa-control-radius:2px;--site-pwa-accent:#b32d2e;--site-pwa-on-accent:#ffffff}',
			Identity::css()
		);
	}

	public function test_the_text_on_the_accent_is_whichever_of_black_and_white_reads_better(): void {
		self::assertSame( '#ffffff', Identity::onAccent( '#1d5e8c' ) );
		self::assertSame( '#000000', Identity::onAccent( '#f0c419' ) );
		self::assertSame( '#000000', Identity::onAccent( '#ffffff' ) );
		self::assertSame( '#ffffff', Identity::onAccent( '#000000' ) );
	}

	public function test_every_place_that_draws_the_app_takes_the_inherited_accent(): void {
		$this->settings( array( 'identity_source' => 'ffc' ) );
		PublicDesign::$stored = self::FFC_ON;

		self::assertSame( '#b32d2e', Manifest::data()['theme_color'] );

		$offline = Pages::render();
		self::assertStringContainsString( '<meta name="theme-color" content="#b32d2e">', $offline );
		self::assertStringContainsString( '<style>' . Identity::css() . '</style>', $offline );

		Login::enqueue();
		$inline = implode( '', wp_styles()->get_data( Login::HANDLE, 'after' ) );
		self::assertStringContainsString( '--site-pwa-accent:#b32d2e;--site-pwa-on-accent:#ffffff;--site-pwa-radius:20px;--site-pwa-control-radius:2px', $inline );
	}

	public function test_the_frame_carries_the_look_after_its_stylesheet_and_in_its_theme_colour(): void {
		$this->settings( array( 'identity_source' => 'ffc' ) );
		PublicDesign::$stored = self::FFC_ON;
		$GLOBALS['wp_scripts'] = null;
		wp_set_current_user( self::factory()->user->create() );
		set_query_var( Routes::QUERY_VAR, 'home' );

		$html = Frame::render( 'home' );
		set_query_var( Routes::QUERY_VAR, '' );

		self::assertStringContainsString( '<meta name="theme-color" content="#b32d2e">', $html );
		self::assertMatchesRegularExpression( '#site-pwa-app-css[^>]*>\s*<style id=["\']site-pwa-app-inline-css["\'][^>]*>\s*' . preg_quote( Identity::css(), '#' ) . '#', $html );
	}

	public function test_changing_the_look_renews_the_workers_cache_so_the_offline_page_follows(): void {
		$this->settings( array( 'identity_source' => 'ffc' ) );
		$before = ServiceWorker::config()['cache'];

		PublicDesign::$stored = self::FFC_ON;

		self::assertNotSame( $before, ServiceWorker::config()['cache'] );
	}

	public function test_an_empty_source_is_stored_as_the_own_settings(): void {
		self::assertSame( 'own', Settings::sanitize( array( 'identity_source' => '' ) )['identity_source'] );
		self::assertSame( 'ffc', Settings::sanitize( array( 'identity_source' => 'FFC' ) )['identity_source'] );
	}
}

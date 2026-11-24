<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Frame;
use SitePwa\App\Invite;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The invite to install the app: where its markup and script go, and the
 * setting that turns the site banner off. Whether it shows on a given device
 * is install.js's decision, covered in tests/js/install.test.js.
 *
 * @covers \SitePwa\App\Invite
 * @covers \SitePwa\Settings
 */
final class InviteTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva', 'short_name' => 'Viva' ) );
	}

	protected function tearDown(): void {
		set_query_var( Routes::QUERY_VAR, '' );
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	private function signIn(): void {
		wp_set_current_user( self::factory()->user->create() );
	}

	private function footer(): string {
		ob_start();
		Invite::banner();
		return (string) ob_get_clean();
	}

	public function test_the_hooks_are_wired(): void {
		self::assertSame( 10, has_action( 'wp_enqueue_scripts', array( Invite::class, 'enqueue' ) ) );
		self::assertSame( 5, has_action( 'wp_footer', array( Invite::class, 'banner' ) ) );
	}

	public function test_a_signed_in_visitor_on_a_site_page_gets_the_banner_hidden_and_its_script(): void {
		$this->signIn();

		$html = $this->footer();
		Invite::enqueue();

		self::assertStringContainsString( '<aside class="site-pwa-site-banner" data-key="site-pwa-banner" aria-label="App" hidden>', $html );
		self::assertStringContainsString( '<strong>Viva</strong>', $html );
		self::assertStringContainsString( 'href="' . esc_url( Routes::url( 'install' ) ) . '">Install</a>', $html, 'the banner leads to the public install page, not the login (#34)' );
		self::assertStringContainsString( '<span>Faster in the app.</span>', $html );
		self::assertTrue( wp_script_is( Invite::SCRIPT, 'enqueued' ) );
		self::assertTrue( wp_style_is( Invite::BANNER_STYLE, 'enqueued' ) );
		self::assertStringContainsString( '--site-pwa-accent:#1d5e8c;--site-pwa-on-accent:#ffffff;--site-pwa-accent-text:#1d5e8c', implode( '', wp_styles()->get_data( Invite::BANNER_STYLE, 'after' ) ) );
	}

	public function test_visitors_who_are_not_signed_in_get_the_banner_by_default(): void {
		self::assertTrue( Invite::bannerWanted() );
		self::assertStringContainsString( 'href="' . esc_url( Routes::url( 'install' ) ) . '">Install</a>', $this->footer() );
		Invite::enqueue();
		self::assertTrue( wp_script_is( Invite::SCRIPT, 'enqueued' ) );
	}

	public function test_the_banner_carries_the_configured_sentence_as_text(): void {
		update_option( Settings::OPTION, array( 'enabled' => true, 'short_name' => 'Viva', 'banner_text' => 'Agenda & <b>presença</b>' ) );

		$html = $this->footer();

		self::assertStringContainsString( '<span>Agenda &amp; presença</span>', $html );
		self::assertStringNotContainsString( '<b>', $html );
	}

	public function test_with_the_guest_option_off_only_signed_in_visitors_get_it(): void {
		update_option( Settings::OPTION, array( 'enabled' => true, 'short_name' => 'Viva', 'site_banner_guests' => '0' ) );

		self::assertFalse( Invite::bannerWanted() );
		self::assertSame( '', $this->footer() );
		Invite::enqueue();
		self::assertFalse( wp_script_is( Invite::SCRIPT, 'enqueued' ) );

		$this->signIn();
		self::assertTrue( Invite::bannerWanted() );
	}

	public function test_no_banner_while_the_app_is_off_or_the_setting_is_unticked(): void {
		$this->signIn();

		update_option( Settings::OPTION, array( 'enabled' => false ) );
		self::assertFalse( Invite::bannerWanted() );

		update_option( Settings::OPTION, array( 'enabled' => true, 'site_banner' => '0' ) );
		self::assertFalse( Invite::bannerWanted() );
		self::assertSame( '', $this->footer() );
	}

	public function test_no_banner_inside_the_app_or_in_wp_admin(): void {
		$this->signIn();

		set_query_var( Routes::QUERY_VAR, 'account' );
		self::assertFalse( Invite::bannerWanted() );
		set_query_var( Routes::QUERY_VAR, '' );

		set_current_screen( 'dashboard' );
		self::assertFalse( Invite::bannerWanted() );
		set_current_screen( 'front' );
	}

	public function test_the_app_home_carries_the_hidden_card_and_its_script(): void {
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'home' );

		$html = Frame::render( 'home' );

		self::assertTrue( Invite::onAppHome() );
		self::assertStringContainsString( '<section class="site-pwa-card site-pwa-install" data-key="site-pwa-install" aria-labelledby="site-pwa-install-heading" hidden>', $html );
		self::assertStringContainsString( '<div data-install-prompt hidden>', $html );
		self::assertStringContainsString( '<div data-install-ios hidden>', $html );
		self::assertStringContainsString( 'aria-label="Not now"', $html );
		self::assertTrue( wp_script_is( Invite::SCRIPT, 'enqueued' ) );
		self::assertFalse( wp_style_is( Invite::BANNER_STYLE, 'enqueued' ) );
	}

	public function test_other_app_screens_load_no_invite(): void {
		$this->signIn();
		set_query_var( Routes::QUERY_VAR, 'account' );

		self::assertStringNotContainsString( 'site-pwa-install', Frame::render( 'account' ) );
		self::assertFalse( wp_script_is( Invite::SCRIPT, 'enqueued' ) );
	}

	public function test_the_banner_setting_is_on_by_default_and_an_unticked_box_turns_it_off(): void {
		self::assertTrue( Settings::defaults()['site_banner'] );
		self::assertTrue( Settings::sanitize( array() )['site_banner'] );
		self::assertFalse( Settings::sanitize( array( 'site_banner' => '0' ) )['site_banner'] );
		self::assertTrue( Settings::sanitize( array( 'site_banner' => '1' ) )['site_banner'] );
		self::assertTrue( Settings::defaults()['site_banner_guests'] );
		self::assertTrue( Settings::sanitize( array() )['site_banner_guests'] );
		self::assertFalse( Settings::sanitize( array( 'site_banner_guests' => '0' ) )['site_banner_guests'] );
	}
}

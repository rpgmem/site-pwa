<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Frame;
use SitePwa\App\Icons;
use SitePwa\App\Login;
use SitePwa\App\Navigation;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The app's frame: its document, its navigation, the theme kept out, and
 * the login dressed as the app.
 *
 * @covers \SitePwa\App\Frame
 * @covers \SitePwa\App\Navigation
 * @covers \SitePwa\App\Icons
 * @covers \SitePwa\App\Login
 */
final class FrameTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Fresh registries: an earlier test's printed or queued assets would
		// otherwise hide what this one enqueues.
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
		wp_set_current_user( self::factory()->user->create( array( 'first_name' => 'Maria', 'display_name' => 'Maria Souza', 'user_email' => 'maria@example.test' ) ) );
	}

	protected function tearDown(): void {
		set_query_var( Routes::QUERY_VAR, '' );
		delete_option( Settings::OPTION );
		unset( $_REQUEST['redirect_to'] );
		parent::tearDown();
	}

	private function screen( string $route ): string {
		set_query_var( Routes::QUERY_VAR, $route );
		return Frame::render( $route );
	}

	public function test_the_frame_has_landmarks_a_skip_link_and_the_current_item_marked(): void {
		$html = $this->screen( 'account' );

		self::assertMatchesRegularExpression( '/<a class="site-pwa-skip" href="#site-pwa-main">/', $html );
		self::assertStringContainsString( '<header class="site-pwa-header">', $html );
		self::assertStringContainsString( '<main id="site-pwa-main" class="site-pwa-main" tabindex="-1">', $html );
		self::assertStringContainsString( '<nav class="site-pwa-nav" aria-label="App">', $html );
		self::assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		self::assertMatchesRegularExpression( '#href="' . preg_quote( Routes::url( 'account' ), '#' ) . '" aria-current="page"#', $html );
	}

	public function test_the_skip_link_comes_before_the_header_and_the_navigation_after_the_content(): void {
		$html = $this->screen( 'home' );
		$skip = strpos( $html, 'site-pwa-skip' );
		$head = strpos( $html, '<header' );
		$main = strpos( $html, '<main' );
		$nav  = strpos( $html, '<nav' );

		self::assertTrue( $skip < $head && $head < $main && $main < $nav, 'Focus order: skip link, header, content, navigation.' );
	}

	public function test_the_home_screen_greets_by_first_name(): void {
		self::assertStringContainsString( 'Hello, Maria', $this->screen( 'home' ) );
	}

	public function test_the_account_screen_shows_the_users_own_details_and_the_way_out(): void {
		$html = $this->screen( 'account' );

		self::assertStringContainsString( 'Maria Souza', $html );
		self::assertStringContainsString( 'maria@example.test', $html );
		self::assertStringContainsString( esc_url( get_edit_profile_url( get_current_user_id() ) ), $html );
		self::assertStringContainsString( 'action=logout', $html );
	}

	public function test_the_frame_prints_wp_head_with_the_manifest_and_wp_footer_with_the_worker_registration(): void {
		$html = $this->screen( 'home' );

		self::assertStringContainsString( '<link rel="manifest" href="' . esc_url( Routes::url( 'manifest' ) ) . '">', $html );
		self::assertStringContainsString( '<meta name="theme-color" content="#1d5e8c">', $html );
		self::assertStringContainsString( "noindex", $html );
		self::assertMatchesRegularExpression( '#<script data-sw="' . preg_quote( esc_url( Routes::url( 'sw' ) ), '#' ) . '" data-scope="/"[^>]*register#', $html );
		self::assertStringContainsString( 'site-pwa-app-css', $html );
	}

	public function test_the_themes_assets_are_taken_out_and_other_plugins_stay(): void {
		set_query_var( Routes::QUERY_VAR, 'home' );
		wp_enqueue_style( 'theme-style', get_stylesheet_directory_uri() . '/style.css', array(), '1' );
		wp_enqueue_script( 'theme-script', get_template_directory_uri() . '/app.js', array(), '1', true );
		wp_enqueue_style( 'other-plugin', plugins_url( 'other/x.css' ), array(), '1' );
		wp_enqueue_style( 'global-styles', false, array(), '1' );

		Frame::isolate();

		self::assertFalse( wp_style_is( 'theme-style', 'enqueued' ) );
		self::assertFalse( wp_script_is( 'theme-script', 'enqueued' ) );
		self::assertFalse( wp_style_is( 'global-styles', 'enqueued' ) );
		self::assertTrue( wp_style_is( 'other-plugin', 'enqueued' ) );
	}

	public function test_outside_the_app_nothing_is_touched(): void {
		wp_enqueue_style( 'theme-style', get_stylesheet_directory_uri() . '/style.css', array(), '1' );

		Frame::isolate();
		Frame::enqueue();
		ob_start();
		Frame::head();

		self::assertSame( '', ob_get_clean() );
		self::assertTrue( wp_style_is( 'theme-style', 'enqueued' ) );
		self::assertFalse( wp_style_is( Frame::HANDLE, 'enqueued' ) );
		self::assertTrue( Frame::adminBar( true ) );
		self::assertSame( 'Title', Frame::title( 'Title' ) );
		self::assertSame( array(), Frame::robots( array() ) );
		self::assertSame( '<script src="x">', Frame::scriptTag( '<script src="x">', 'other' ) );
	}

	public function test_inside_the_app_there_is_no_admin_bar_and_the_title_names_the_screen(): void {
		set_query_var( Routes::QUERY_VAR, 'account' );

		self::assertFalse( Frame::adminBar( true ) );
		self::assertSame( 'Account – Escola Viva', Frame::title( '' ) );
	}

	public function test_navigation_lists_home_then_account_with_one_current(): void {
		$items = Navigation::items( 'home' );

		self::assertSame( array( 'home', 'account' ), array_column( $items, 'id' ) );
		self::assertSame( array( true, false ), array_column( $items, 'current' ) );
		self::assertSame( '', Navigation::label( 'unknown' ) );
	}

	public function test_icons_are_a_closed_set_hidden_from_assistive_technology(): void {
		self::assertStringContainsString( 'aria-hidden="true"', Icons::svg( 'home' ) );
		self::assertSame( '', Icons::svg( '<script>' ) );
	}

	public function test_the_login_styles_load_only_while_the_app_is_on(): void {
		Login::enqueue();
		self::assertTrue( wp_style_is( Login::HANDLE, 'enqueued' ) );
		self::assertStringContainsString( '--site-pwa-logo:url(', implode( '', wp_styles()->get_data( Login::HANDLE, 'after' ) ) );

		wp_dequeue_style( Login::HANDLE );
		update_option( Settings::OPTION, array( 'enabled' => false ) );
		Login::enqueue();
		self::assertFalse( wp_style_is( Login::HANDLE, 'enqueued' ) );
	}

	public function test_the_login_logo_points_back_to_the_app_only_when_reached_from_it(): void {
		self::assertSame( 'https://wordpress.org/', Login::headerUrl( 'https://wordpress.org/' ) );
		self::assertSame( 'Powered by WordPress', Login::headerText( 'Powered by WordPress' ) );

		$_REQUEST['redirect_to'] = Routes::url( 'account' );

		self::assertSame( Routes::url( 'home' ), Login::headerUrl( 'https://wordpress.org/' ) );
		self::assertSame( 'Escola Viva', Login::headerText( 'Powered by WordPress' ) );
	}

	public function test_every_login_rule_applies_only_inside_the_installed_app(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/login.css' );
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		self::assertSame( 1, preg_match( '/^\s*@media \(display-mode: standalone\) \{(.*)\}\s*$/s', $css ), 'login.css holds one standalone media block and nothing outside it.' );
	}
}

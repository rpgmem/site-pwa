<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\Frame;
use SitePwa\App\Lock;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The app lock (#38): what the server hands the device, the head check that
 * hides a locked app before the first paint, and the screens that carry it.
 *
 * @covers \SitePwa\App\Lock
 */
final class LockTest extends WP_UnitTestCase {

	private \WP_User $user;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
		$this->user = new \WP_User( self::factory()->user->create( array( 'display_name' => 'Maria Souza' ) ) );
		wp_set_current_user( $this->user->ID );
	}

	protected function tearDown(): void {
		set_query_var( Routes::QUERY_VAR, '' );
		delete_option( Settings::OPTION );
		remove_all_filters( 'site_pwa_lock_required' );
		parent::tearDown();
	}

	private function screen( string $route ): string {
		set_query_var( Routes::QUERY_VAR, $route );
		return Frame::render( $route );
	}

	public function test_the_lock_is_optional_until_a_consumer_makes_it_required(): void {
		self::assertFalse( Lock::required( $this->user ) );

		add_filter( 'site_pwa_lock_required', static fn( $required, $user ) => 'Maria Souza' === $user->display_name, 10, 2 );
		self::assertTrue( Lock::required( $this->user ) );
		self::assertFalse( Lock::required( new \WP_User( 0 ) ), 'signed out' );

		add_filter( 'site_pwa_lock_required', static fn() => 'yes', 20 );
		self::assertFalse( Lock::required( $this->user ), 'only a real true makes it mandatory' );
	}

	public function test_each_account_keeps_its_own_lock_on_a_shared_phone(): void {
		$other = new \WP_User( self::factory()->user->create() );
		self::assertSame( 16, strlen( Lock::key( $this->user ) ) );
		self::assertSame( Lock::key( $this->user ), Lock::key( $this->user ) );
		self::assertNotSame( Lock::key( $this->user ), Lock::key( $other ) );
		// The key must not let anyone read the account back: a salted hash,
		// not the id and not a plain hash of it. Asserting that the id's
		// digits are absent measured chance instead (id 57 sits in
		// "ccfbd157d768fdc2"), and failed the same way on every run.
		self::assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', Lock::key( $this->user ) );
		self::assertNotSame( substr( md5( 'site-pwa-lock|' . $this->user->ID ), 0, 16 ), Lock::key( $this->user ), 'salted, not a plain hash of the id' );
	}

	public function test_the_device_gets_its_key_the_grace_and_the_way_out_and_nothing_personal(): void {
		$config = Lock::config( $this->user );
		self::assertSame( Lock::GRACE, $config['grace'] );
		self::assertSame( 10, Lock::GRACE );
		self::assertSame( Lock::NUDGE_DAYS, $config['nudgeDays'] );
		self::assertSame( 'Escola Viva', $config['name'] );
		self::assertStringContainsString( 'action=logout', $config['logout'] );
		self::assertNotSame( '', $config['strings']['noLock'] );
		self::assertStringNotContainsString( 'Maria', (string) wp_json_encode( $config ) );
	}

	public function test_the_head_marks_a_locked_app_before_the_first_paint(): void {
		$html = $this->screen( 'home' );
		$head = substr( $html, 0, (int) strpos( $html, '</head>' ) );
		self::assertStringContainsString( Lock::headScript( $this->user ), $head );
		self::assertStringContainsString( "'site-pwa-lock:'+c.key", Lock::headScript( $this->user ), 'the key lock.js writes' );
		self::assertStringContainsString( "'site-pwa-lock-seen:'+c.key", Lock::headScript( $this->user ) );
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/lock.js' );
		self::assertStringContainsString( "'site-pwa-lock:'", $script );
		self::assertStringContainsString( "'site-pwa-lock-seen:'", $script );
		self::assertStringContainsString( "'site-pwa-locked'", $script );
	}

	public function test_every_screen_carries_the_lock_screen_and_its_script(): void {
		$html = $this->screen( 'account' );
		self::assertStringContainsString( 'data-site-pwa-lock="overlay"', $html );
		self::assertStringContainsString( 'data-site-pwa-lock="signout"', $html );
		self::assertTrue( wp_script_is( Lock::HANDLE, 'enqueued' ) );
		self::assertStringContainsString( 'sitePwaLock', (string) wp_scripts()->get_data( Lock::HANDLE, 'data' ) );
		self::assertLessThan( strpos( $html, '<main' ), strpos( $html, 'data-site-pwa-lock="overlay"' ), 'drawn before the content' );
	}

	public function test_preferences_has_the_switch_and_home_the_invitation(): void {
		self::assertStringContainsString( 'data-site-pwa-lock-switch', $this->screen( 'preferences' ) );
		self::assertStringNotContainsString( 'data-site-pwa-lock-switch', $this->screen( 'more' ), 'More only links to it' );
		$home = $this->screen( 'home' );
		self::assertStringContainsString( 'data-site-pwa-lock-nudge', $home );
		self::assertMatchesRegularExpression( '/data-site-pwa-lock-nudge[^>]*hidden/', $home, 'the script decides whether it shows' );
	}
}

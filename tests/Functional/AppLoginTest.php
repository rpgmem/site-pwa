<?php

declare(strict_types=1);

namespace SitePwa\Tests\Functional;

use SitePwa\App\AppLogin;
use SitePwa\App\Login;
use SitePwa\App\Routes;
use SitePwa\Settings;
use WP_UnitTestCase;

/**
 * The app's own sign-in at /app/login/ (#74): drawn inside the app's scope,
 * signing in through wp_signon(), and stepping aside for WordPress's login
 * where another plugin extends it.
 *
 * @covers \SitePwa\App\AppLogin
 * @covers \SitePwa\App\Pages
 */
final class AppLoginTest extends WP_UnitTestCase {

	/**
	 * The person signed in by the tests.
	 */
	private int $user = 0;

	protected function setUp(): void {
		parent::setUp();
		update_option( Settings::OPTION, array( 'enabled' => true, 'name' => 'Escola Viva' ) );
		update_option( 'blogdescription', 'Learn & grow' );
		$this->user = self::factory()->user->create(
			array(
				'user_login' => 'maria',
				'user_pass'  => 'fake-password-maria',
			)
		);
		// The test process has sent its headers; a cookie would be refused.
		add_filter( 'send_auth_cookies', '__return_false' );
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_METHOD'] );
		$_POST    = array();
		$_REQUEST = array();
		remove_all_filters( 'site_pwa_app_login' );
		remove_all_filters( 'send_auth_cookies' );
		remove_all_actions( 'set_auth_cookie' );
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	/**
	 * Post the form.
	 *
	 * @param array<string, string> $fields The form's fields.
	 * @return array{status: int, headers: array<string, string>, body: string}
	 */
	private function post( array $fields ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = $fields + array(
			'_wpnonce'    => wp_create_nonce( AppLogin::NONCE ),
			'redirect_to' => Routes::url( 'account' ),
		);
		$_REQUEST                  = $_POST;
		return Routes::response( 'login' );
	}

	public function test_the_screen_is_the_apps_own_page_with_the_form_ticked_to_keep_signed_in(): void {
		$_REQUEST['redirect_to'] = Routes::url( 'news' );
		$response                = Routes::response( 'login' );

		self::assertSame( 200, $response['status'] );
		self::assertSame( 'private, no-store', $response['headers']['Cache-Control'] );
		$body = $response['body'];
		self::assertStringContainsString( '<form class="site-pwa-card site-pwa-login-form" method="post" action="' . esc_url( Routes::url( 'login' ) ) . '">', $body );
		self::assertStringContainsString( '<h1>Escola Viva</h1>', $body );
		self::assertStringContainsString( '<p>Learn &amp; grow</p>', $body );
		self::assertMatchesRegularExpression( '/name="rememberme" value="forever"\s+checked/', $body );
		self::assertStringContainsString( 'name="redirect_to" value="' . esc_url( Routes::url( 'news' ) ) . '"', $body );
		self::assertStringContainsString( 'name="_wpnonce" value="' . wp_create_nonce( AppLogin::NONCE ) . '"', $body );
		self::assertStringContainsString( esc_url( wp_lostpassword_url( Routes::url( 'news' ) ) ), $body );
		self::assertStringContainsString( esc_url( wp_login_url( Routes::url( 'news' ) ) ), $body, 'WordPress\'s login stays one tap away' );
		self::assertStringContainsString( 'autocomplete="current-password"', $body );
		self::assertSame( home_url( '/app/login/' ), Routes::url( 'login' ) );
	}

	public function test_a_return_address_off_the_app_is_replaced_by_the_apps_home(): void {
		self::assertSame( Routes::url( 'account' ), AppLogin::target( Routes::url( 'account' ) ) );
		self::assertSame( Routes::url( 'home' ), AppLogin::target( 'https://elsewhere.example/app/' ) );
		self::assertSame( Routes::url( 'home' ), AppLogin::target( home_url( '/wp-admin/' ) ) );
		self::assertSame( Routes::url( 'home' ), AppLogin::target( '' ) );
		self::assertStringStartsWith( Routes::url( 'login' ) . '?redirect_to=', AppLogin::url( Routes::url( 'more' ) ) );
	}

	public function test_signing_in_returns_to_the_app_for_ninety_days(): void {
		$expires = 0;
		add_action(
			'set_auth_cookie',
			static function ( $cookie, $expire ) use ( &$expires ): void {
				$expires = (int) $expire;
			},
			10,
			2
		);

		$before   = time();
		$response = $this->post(
			array(
				'log'        => 'maria',
				'pwd'        => 'fake-password-maria',
				'rememberme' => 'forever',
			)
		);

		self::assertSame( 302, $response['status'] );
		self::assertSame( Routes::url( 'account' ), $response['headers']['Location'] );
		self::assertGreaterThanOrEqual( $before + Login::SESSION_DAYS * DAY_IN_SECONDS, $expires, 'the app\'s long session (#72)' );
	}

	public function test_a_wrong_password_says_so_without_naming_which_half_and_keeps_the_username(): void {
		$failed = 0;
		add_action(
			'wp_login_failed',
			static function () use ( &$failed ): void {
				++$failed;
			}
		);

		$response = $this->post(
			array(
				'log' => 'maria',
				'pwd' => 'fake-wrong',
			)
		);

		self::assertSame( 200, $response['status'] );
		self::assertStringContainsString( '<p class="site-pwa-login-error" role="alert">The username or password is incorrect.</p>', $response['body'] );
		self::assertStringContainsString( 'name="log" value="maria"', $response['body'] );
		self::assertDoesNotMatchRegularExpression( '/name="rememberme" value="forever"\s+checked/', $response['body'], 'the person\'s own choice stands' );
		self::assertSame( 1, $failed, 'security plugins see the failure' );

		$unknown = $this->post(
			array(
				'log' => 'nobody',
				'pwd' => 'fake-wrong',
			)
		);
		self::assertStringContainsString( 'The username or password is incorrect.', $unknown['body'], 'an unknown account reads the same' );
	}

	public function test_an_expired_page_or_an_empty_field_is_refused_before_any_sign_in(): void {
		$expired = $this->post(
			array(
				'log'      => 'maria',
				'pwd'      => 'fake-password-maria',
				'_wpnonce' => 'fake-nonce',
			)
		);
		self::assertStringContainsString( 'This page expired. Try again.', $expired['body'] );

		$empty = $this->post(
			array(
				'log' => 'maria',
				'pwd' => '',
			)
		);
		self::assertStringContainsString( 'Type your username or email and your password.', $empty['body'] );
	}

	public function test_a_signed_in_person_goes_straight_back(): void {
		wp_set_current_user( $this->user );
		$_REQUEST['redirect_to'] = Routes::url( 'more' );
		$response                = Routes::response( 'login' );

		self::assertSame( 302, $response['status'] );
		self::assertSame( Routes::url( 'more' ), $response['headers']['Location'] );
	}

	public function test_a_login_another_plugin_extends_stays_wordpress_s(): void {
		self::assertTrue( AppLogin::enabled() );

		$captcha = static function (): void {
			echo '<input name="fake-captcha">';
		};
		add_action( 'login_form', $captcha );
		self::assertFalse( AppLogin::enabled(), 'a field this screen does not draw would refuse every attempt' );
		$_REQUEST['redirect_to'] = Routes::url( 'account' );
		$response                = Routes::response( 'login' );
		self::assertSame( 302, $response['status'] );
		self::assertSame( wp_login_url( Routes::url( 'account' ) ), $response['headers']['Location'] );

		add_filter( 'site_pwa_app_login', '__return_true' );
		self::assertTrue( AppLogin::enabled(), 'the filter decides either way' );
		remove_action( 'login_form', $captcha );
	}

	public function test_the_screen_answers_nothing_while_the_app_is_off(): void {
		update_option( Settings::OPTION, array( 'enabled' => false ) );
		self::assertNull( Routes::response( 'login' ) );
	}
}

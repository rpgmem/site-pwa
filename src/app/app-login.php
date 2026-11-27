<?php
/**
 * The app's own sign-in screen, inside the app's scope.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use WP_Error;
use WP_User;

/**
 * `/app/login/`: the sign-in drawn by the app, so signing in never leaves
 * the installed app for WordPress's login, which sits outside its scope and
 * opens under the browser's own bar (#74).
 *
 * The person is signed in by `wp_signon()`, so the `authenticate` filters,
 * `wp_login_failed` and `wp_login` run as on the WordPress login, and the
 * security plugins that hook them keep working. A site whose login another
 * plugin extends (a captcha or a second factor drawn on the login form, the
 * Two Factor plugin) keeps WordPress's login instead: a field this screen
 * does not draw would refuse every attempt. `site_pwa_app_login` decides
 * either way. The lost-password flow stays WordPress's.
 *
 * @phpstan-import-type Response from Routes
 */
final class AppLogin {

	/**
	 * Nonce action of the sign-in form.
	 */
	public const NONCE = 'site_pwa_app_login';

	/**
	 * Whether the app signs people in on its own screen.
	 */
	public static function enabled(): bool {
		$default = ! self::loginFormExtended() && ! class_exists( 'Two_Factor_Core' );
		/**
		 * Whether the app signs people in on its own screen, at /app/login/,
		 * instead of WordPress's login.
		 *
		 * @param bool $enabled True unless another plugin extends the login.
		 */
		return (bool) apply_filters( 'site_pwa_app_login', $default );
	}

	/**
	 * Whether another plugin draws something on WordPress's login form,
	 * which this screen would not: a captcha, a second factor, a consent.
	 */
	private static function loginFormExtended(): bool {
		global $wp_filter;
		if ( ! isset( $wp_filter['login_form'] ) ) {
			return false;
		}
		foreach ( $wp_filter['login_form']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( array( Login::class, 'lostPasswordLink' ) !== $callback['function'] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * The sign-in screen's address, returning to an app address afterwards.
	 *
	 * @param string $back Where to go once signed in; the app's home if empty.
	 */
	public static function url( string $back = '' ): string {
		return add_query_arg( 'redirect_to', rawurlencode( self::target( $back ) ), Routes::url( 'login' ) );
	}

	/**
	 * Where a sign-in returns: the address asked for when it is inside the
	 * app, the app's home otherwise, so the screen never sends anyone off
	 * the site.
	 *
	 * @param string $back The address asked for.
	 */
	public static function target( string $back ): string {
		$home = Routes::url( 'home' );
		return '' !== $back && 0 === strpos( $back, $home ) ? $back : $home;
	}

	/**
	 * What `/app/login/` answers: the screen, the result of a sign-in, or a
	 * redirect when there is nothing to sign in to.
	 *
	 * @return Response
	 */
	public static function response(): array {
		$headers = array(
			'Content-Type'  => 'text/html; charset=utf-8',
			'Cache-Control' => 'private, no-store',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the address to return to, checked by target(); the sign-in itself checks the nonce.
		$back = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) ) : '';
		$back = self::target( $back );

		if ( is_user_logged_in() ) {
			return self::redirect( $back, $headers );
		}
		if ( ! self::enabled() ) {
			return self::redirect( wp_login_url( $back ), $headers );
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'POST' !== $method ) {
			return array(
				'status'  => 200,
				'headers' => $headers,
				'body'    => self::render( $back, '', '', true ),
			);
		}

		$result = self::signIn();
		if ( $result instanceof WP_User ) {
			return self::redirect( $back, $headers );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in signIn(); read again only to refill the field.
		$login    = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( (string) $_POST['log'] ) ) : '';
		$remember = ! empty( $_POST['rememberme'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in signIn(); read only to refill the box.
		return array(
			'status'  => 200,
			'headers' => $headers,
			'body'    => self::render( $back, $login, $result->get_error_message(), $remember ),
		);
	}

	/**
	 * Sign the person in through WordPress, with a message they can act on
	 * when it fails. The message never says whether the account exists.
	 *
	 * @return WP_User|WP_Error
	 */
	private static function signIn() {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return new WP_Error( 'site_pwa_expired', __( 'This page expired. Try again.', 'site-pwa' ) );
		}
		$login    = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( (string) $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a password is checked as typed, never stored or printed.
		if ( '' === $login || '' === $password ) {
			return new WP_Error( 'site_pwa_empty', __( 'Type your username or email and your password.', 'site-pwa' ) );
		}
		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => ! empty( $_POST['rememberme'] ),
			),
			is_ssl()
		);
		if ( $user instanceof WP_User ) {
			return $user;
		}
		return new WP_Error( 'site_pwa_refused', __( 'The username or password is incorrect.', 'site-pwa' ) );
	}

	/**
	 * The screen.
	 *
	 * @param string $back     Where a sign-in returns.
	 * @param string $login    The username to refill.
	 * @param string $error    The message of a failed attempt, or ''.
	 * @param bool   $remember Whether "Keep me signed in" is ticked.
	 */
	private static function render( string $back, string $login, string $error, bool $remember ): string {
		return Pages::render(
			'login',
			array(
				'action'     => Routes::url( 'login' ),
				'back'       => $back,
				'login'      => $login,
				'error'      => $error,
				'remember'   => $remember,
				'nonce'      => wp_create_nonce( self::NONCE ),
				'lost'       => wp_lostpassword_url( $back ),
				'site_login' => wp_login_url( $back ),
				'tagline'    => trim( wp_strip_all_tags( (string) get_bloginfo( 'description' ) ) ),
			)
		);
	}

	/**
	 * A redirect answer.
	 *
	 * @param string                $to      Where to.
	 * @param array<string, string> $headers Headers of the answer.
	 * @return Response
	 */
	private static function redirect( string $to, array $headers ): array {
		return array(
			'status'  => 302,
			'headers' => $headers + array( 'Location' => $to ),
			'body'    => '',
		);
	}
}

<?php
/**
 * The WordPress login, dressed as the app when opened from it.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;
use SitePwa\Settings;

/**
 * Styles the WordPress login screen for the installed app without replacing it.
 *
 * The form, its fields and every plugin hooked into it (two-factor, captcha,
 * a security plugin) stay as WordPress draws them. The stylesheet's rules
 * all sit under `@media (display-mode: standalone)`, so the login looks like
 * the app only inside the installed app; in a browser tab it is untouched.
 */
final class Login {

	/**
	 * Style handle.
	 */
	public const HANDLE = 'site-pwa-login';

	/**
	 * Wire the login hooks.
	 */
	public static function register(): void {
		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'login_headerurl', array( self::class, 'headerUrl' ) );
		add_filter( 'login_headertext', array( self::class, 'headerText' ) );
	}

	/**
	 * Whether this login was reached from the app: its `redirect_to` points
	 * inside the app.
	 */
	public static function fromApp(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only to choose the logo link; core validates redirect_to itself.
		$target = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) ) : '';
		return Settings::enabled() && '' !== $target && 0 === strpos( $target, Routes::url( 'home' ) );
	}

	/**
	 * Enqueue the app's login styles while the app is on. The logo is the
	 * app's icon, a value only known at runtime, carried as a custom property.
	 */
	public static function enqueue(): void {
		if ( ! Settings::enabled() ) {
			return;
		}
		$css = Plugin::asset( 'assets/css/login.css' );
		wp_enqueue_style( self::HANDLE, Plugin::url() . $css, array( 'login' ), Plugin::assetVersion( $css ) );
		wp_add_inline_style(
			self::HANDLE,
			sprintf(
				':root{--site-pwa-logo:url("%1$s");--site-pwa-accent:%2$s}',
				esc_url_raw( Manifest::touchIcon() ),
				Settings::get()['theme_color']
			)
		);
	}

	/**
	 * The logo links back to the app when the login came from it.
	 *
	 * @param string $url Where WordPress would link the logo.
	 */
	public static function headerUrl( string $url ): string {
		return self::fromApp() ? Routes::url( 'home' ) : $url;
	}

	/**
	 * The logo's text is the app's name when the login came from it.
	 *
	 * @param string $text The text WordPress would print.
	 */
	public static function headerText( string $text ): string {
		return self::fromApp() ? Settings::get()['name'] : $text;
	}
}

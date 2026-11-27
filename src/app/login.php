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
	 * How many days a login from the app keeps the person signed in when
	 * "Keep me signed in on this device" is ticked (#72), before the
	 * `site_pwa_session_days` filter.
	 */
	public const SESSION_DAYS = 90;

	/**
	 * Wire the login hooks.
	 */
	public static function register(): void {
		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'login_headerurl', array( self::class, 'headerUrl' ) );
		add_filter( 'login_headertext', array( self::class, 'headerText' ) );
		add_filter( 'login_message', array( self::class, 'message' ) );
		add_action( 'login_form', array( self::class, 'lostPasswordLink' ) );
		add_action( 'login_footer', array( self::class, 'rememberByDefault' ) );
		add_filter( 'login_display_language_dropdown', array( self::class, 'languageDropdown' ) );
		add_filter( 'the_privacy_policy_link', array( self::class, 'privacyLink' ) );
		add_filter( 'auth_cookie_expiration', array( self::class, 'sessionLength' ), 10, 3 );
		add_filter( 'login_redirect', array( self::class, 'welcome' ), 10, 3 );
		// Only on the login screen: gettext runs for every string of the site.
		add_action( 'login_init', array( self::class, 'relabel' ) );
	}

	/**
	 * Hook the "Remember Me" relabelling, from `login_init`.
	 */
	public static function relabel(): void {
		add_filter( 'gettext', array( self::class, 'rememberLabel' ), 10, 3 );
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
		$look = Identity::current();
		$css  = Plugin::asset( 'assets/css/login.css' );
		wp_enqueue_style( self::HANDLE, Plugin::url() . $css, array( 'login' ), Plugin::assetVersion( $css ) );
		wp_add_inline_style(
			self::HANDLE,
			sprintf(
				':root{--site-pwa-logo:url("%1$s");--site-pwa-accent:%2$s;--site-pwa-on-accent:%3$s;--site-pwa-radius:%4$dpx;--site-pwa-control-radius:%5$dpx;--site-pwa-accent-text:%6$s}',
				esc_url_raw( Manifest::touchIcon() ),
				$look['accent'],
				Identity::onAccent( $look['accent'] ),
				$look['radius'],
				$look['control_radius'],
				Identity::accentText( $look['accent'] )
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

	/**
	 * The app's name and the site's tagline under the logo, when the login
	 * came from the app (#23). It is printed `hidden`; the stylesheet shows
	 * it only inside the installed app.
	 *
	 * @param string $message What WordPress or another plugin prints there.
	 */
	public static function message( string $message ): string {
		if ( ! self::fromApp() ) {
			return $message;
		}
		$tagline = trim( wp_strip_all_tags( (string) get_bloginfo( 'description' ) ) );
		$heading = sprintf(
			'<div class="site-pwa-login-heading" hidden><p class="site-pwa-login-name">%1$s</p>%2$s</div>',
			esc_html( Settings::get()['name'] ),
			'' === $tagline ? '' : '<p class="site-pwa-login-tagline">' . esc_html( $tagline ) . '</p>'
		);
		return $heading . $message;
	}

	/**
	 * The lost-password link inside the card, above the button, when the
	 * login came from the app; the stylesheet hides core's link under the card.
	 */
	public static function lostPasswordLink(): void {
		if ( ! self::fromApp() ) {
			return;
		}
		printf(
			'<p class="site-pwa-login-lost" hidden><a href="%1$s">%2$s</a></p>',
			esc_url( wp_lostpassword_url( Routes::url( 'home' ) ) ),
			esc_html__( 'Forgot your password?', 'site-pwa' )
		);
	}

	/**
	 * "Remember Me" says what it does on a phone, when the login came from
	 * the app.
	 *
	 * @param string $translation The translated text.
	 * @param string $text        The source text.
	 * @param string $domain      Its text domain.
	 */
	public static function rememberLabel( string $translation, string $text, string $domain ): string {
		if ( 'default' !== $domain || 'Remember Me' !== $text || ! self::fromApp() ) {
			return $translation;
		}
		return __( 'Keep me signed in on this device', 'site-pwa' );
	}

	/**
	 * "Keep me signed in on this device" starts ticked on a login from the
	 * app (#72): a phone is personal, and the app's screen lock guards the
	 * long session. A login that failed keeps the person's own choice.
	 */
	public static function rememberByDefault(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only to tell a first view from a failed attempt.
		if ( ! self::fromApp() || isset( $_POST['log'] ) ) {
			return;
		}
		wp_print_inline_script_tag( 'var r=document.getElementById("rememberme");if(r){r.checked=true;}' );
	}

	/**
	 * No language switcher under the login from the app (#72): the app
	 * speaks the site's language, and the switcher is a site visitor's tool.
	 *
	 * @param bool $show Whether WordPress would show it.
	 */
	public static function languageDropdown( bool $show ): bool {
		return $show && ! self::fromApp();
	}

	/**
	 * No privacy-policy link under the login from the app (#72); the site
	 * keeps it everywhere else.
	 *
	 * @param string $link The link WordPress would print.
	 */
	public static function privacyLink( string $link ): string {
		return did_action( 'login_init' ) && self::fromApp() ? '' : $link;
	}

	/**
	 * A login from the app with "Keep me signed in" ticked lasts
	 * SESSION_DAYS (#72) instead of WordPress's 14 days, so the app does
	 * not ask for the password every two weeks. The session is still
	 * WordPress's own cookie: signing out or changing the password ends it.
	 * Without the tick, or from the site, WordPress's length stands.
	 *
	 * @param int  $length   Seconds WordPress would give the cookie.
	 * @param int  $user_id  The person.
	 * @param bool $remember Whether "Keep me signed in" was ticked.
	 */
	public static function sessionLength( $length, $user_id = 0, $remember = false ): int {
		if ( ! $remember || ! self::fromApp() ) {
			return (int) $length;
		}
		/**
		 * Days a login from the app keeps the person signed in.
		 *
		 * @param int $days    Days; SESSION_DAYS by default.
		 * @param int $user_id The person.
		 */
		$days = (int) apply_filters( 'site_pwa_session_days', self::SESSION_DAYS, (int) $user_id );
		return $days > 0 ? $days * DAY_IN_SECONDS : (int) $length;
	}

	/**
	 * A login from the app returns with the lock offered on the first
	 * screen (#76), as the app's own sign-in does. Hooked at the default
	 * priority, after which only plugins that move the person elsewhere
	 * run; an address outside the app is left as it is.
	 *
	 * @param string             $to        Where WordPress sends the person.
	 * @param string             $requested The address the login asked for.
	 * @param \WP_User|\WP_Error $user      The result of the login.
	 */
	public static function welcome( $to, $requested = '', $user = null ): string {
		$to = (string) $to;
		if ( ! $user instanceof \WP_User || ! Settings::enabled() || 0 !== strpos( $to, Routes::url( 'home' ) ) ) {
			return $to;
		}
		return add_query_arg( Lock::WELCOME, '1', $to );
	}
}

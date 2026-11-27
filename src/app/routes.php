<?php
/**
 * The app's URLs and what answers them.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Settings;

/**
 * Registers the app's rewrite rules and answers them.
 *
 * Every app page lives under one prefix (`/app/` unless filtered); the service
 * worker script is at the site root, because a worker's scope can only reach
 * the paths at and below its own file.
 *
 * @phpstan-type Response array{status: int, headers: array<string, string>, body: string}
 */
final class Routes {

	/**
	 * The query var that names the route.
	 */
	public const QUERY_VAR = 'site_pwa_route';

	/**
	 * The query var that names a registered screen, for the `screen` route.
	 */
	public const SCREEN_VAR = 'site_pwa_screen';

	/**
	 * The service worker's file name, at the site root.
	 */
	public const SW_FILE = 'site-pwa-sw.js';

	/**
	 * Bumped whenever the rules change, so an install that updated without
	 * an activation still gets them: a GitHub update runs no activation hook.
	 */
	public const REVISION = 7;

	/**
	 * Option holding the rules revision last flushed. Deleted by uninstall.php.
	 */
	public const REVISION_OPTION = 'site_pwa_rewrite_revision';

	/**
	 * The routes and the path each one answers under the prefix.
	 */
	public const ROUTES = array( 'home', 'account', 'more', 'preferences', 'news', 'screen', 'post', 'offline', 'install', 'login', 'manifest' );

	/**
	 * The routes drawn inside the app frame; they need a signed-in user.
	 * `post` is the home URL with `?p=ID` (see Content::request()); `screen`
	 * is one registered by another plugin (see Screens).
	 */
	public const SCREENS = array( 'home', 'account', 'more', 'preferences', 'news', 'screen', 'post' );

	/**
	 * Wire the rules and the handler.
	 */
	public static function register(): void {
		// Default priority on `init`: rules must exist before `parse_request`.
		add_action( 'init', array( self::class, 'addRules' ) );
		// Late on `init`, after every rule is added, so a flush sees them all.
		add_action( 'init', array( self::class, 'maybeFlush' ), 99 );
		add_filter( 'query_vars', array( self::class, 'queryVars' ) );
		// Priority 1, before redirect_canonical (10): a redirect would add a
		// trailing slash to the worker script, and browsers refuse to register
		// a worker whose script answers with a redirect.
		add_action( 'template_redirect', array( self::class, 'dispatch' ), 1 );
	}

	/**
	 * The app's path segment, without slashes. Filterable for a site where
	 * `/app/` is already a page.
	 */
	public static function prefix(): string {
		$prefix = (string) apply_filters( 'site_pwa_app_prefix', 'app' );
		$prefix = strtolower( (string) preg_replace( '/[^A-Za-z0-9-]/', '', $prefix ) );
		return '' === $prefix ? 'app' : $prefix;
	}

	/**
	 * Add the rewrite rules.
	 */
	public static function addRules(): void {
		$prefix = self::prefix();
		add_rewrite_rule( '^' . preg_quote( self::SW_FILE, '/' ) . '$', 'index.php?' . self::QUERY_VAR . '=sw', 'top' );
		add_rewrite_rule( '^' . $prefix . '/manifest\.webmanifest$', 'index.php?' . self::QUERY_VAR . '=manifest', 'top' );
		add_rewrite_rule( '^' . $prefix . '/offline/?$', 'index.php?' . self::QUERY_VAR . '=offline', 'top' );
		add_rewrite_rule( '^' . $prefix . '/install/?$', 'index.php?' . self::QUERY_VAR . '=install', 'top' );
		add_rewrite_rule( '^' . $prefix . '/login/?$', 'index.php?' . self::QUERY_VAR . '=login', 'top' );
		add_rewrite_rule( '^' . $prefix . '/account/?$', 'index.php?' . self::QUERY_VAR . '=account', 'top' );
		add_rewrite_rule( '^' . $prefix . '/more/?$', 'index.php?' . self::QUERY_VAR . '=more', 'top' );
		add_rewrite_rule( '^' . $prefix . '/preferences/?$', 'index.php?' . self::QUERY_VAR . '=preferences', 'top' );
		add_rewrite_rule( '^' . $prefix . '/news/?$', 'index.php?' . self::QUERY_VAR . '=news', 'top' );
		add_rewrite_rule( '^' . $prefix . '/s/([a-z0-9-]+)/?$', 'index.php?' . self::QUERY_VAR . '=screen&' . self::SCREEN_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $prefix . '/?$', 'index.php?' . self::QUERY_VAR . '=home', 'top' );
	}

	/**
	 * Flush the rules once per revision and prefix.
	 */
	public static function maybeFlush(): void {
		$revision = self::REVISION . '|' . self::prefix();
		if ( get_option( self::REVISION_OPTION ) === $revision ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REVISION_OPTION, $revision, false );
	}

	/**
	 * Make the route query var public.
	 *
	 * @param array<int, string> $vars Public query vars.
	 * @return array<int, string>
	 */
	public static function queryVars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::SCREEN_VAR;
		return $vars;
	}

	/**
	 * The URL of a route.
	 *
	 * @param string $route `home`, `account`, `more`, `preferences`, `news`, `offline`, `install`, `login`, `manifest` or `sw`.
	 */
	public static function url( string $route ): string {
		if ( 'sw' === $route ) {
			return home_url( '/' . self::SW_FILE );
		}
		$path = array(
			'home'        => '/',
			'account'     => '/account/',
			'more'        => '/more/',
			'preferences' => '/preferences/',
			'news'        => '/news/',
			'offline'     => '/offline/',
			'install'     => '/install/',
			'login'       => '/login/',
			'manifest'    => '/manifest.webmanifest',
		);
		return home_url( '/' . self::prefix() . ( $path[ $route ] ?? '/' ) );
	}

	/**
	 * The site's root path, the service worker's scope (`/`, or `/blog/` on
	 * a site in a subdirectory).
	 */
	public static function scope(): string {
		$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return '' === $path ? '/' : trailingslashit( $path );
	}

	/**
	 * What a route answers, or null when the request is not the app's or the
	 * app is off (the theme's 404 then answers).
	 *
	 * The worker script answers even with the app off: it then serves a worker
	 * that removes itself, so a browser that installed the app is cleaned up.
	 *
	 * @param string $route The route query var.
	 * @return Response|null
	 */
	public static function response( string $route ): ?array {
		if ( 'sw' === $route ) {
			return array(
				'status'  => 200,
				'headers' => array(
					'Content-Type'           => 'application/javascript; charset=utf-8',
					'Cache-Control'          => 'no-cache',
					'Service-Worker-Allowed' => self::scope(),
					'X-Content-Type-Options' => 'nosniff',
				),
				'body'    => Settings::enabled() ? ServiceWorker::script() : ServiceWorker::retirement(),
			);
		}

		if ( ! in_array( $route, self::ROUTES, true ) || ! Settings::enabled() ) {
			return null;
		}

		if ( 'manifest' === $route ) {
			return array(
				'status'  => 200,
				// Revalidated on every fetch (#28): an installed app takes its
				// colour, name and icon from it, and a cached copy would keep
				// the old ones on every phone.
				'headers' => array(
					'Content-Type'  => 'application/manifest+json; charset=utf-8',
					'Cache-Control' => 'no-cache',
				),
				'body'    => (string) wp_json_encode( Manifest::data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			);
		}

		// The install page is public and the same for everyone (#34): the
		// browser decides which of its states shows, so no login and no
		// personal data. Revalidated, so a changed name or colour shows.
		if ( 'install' === $route ) {
			return array(
				'status'  => 200,
				'headers' => array(
					'Content-Type'  => 'text/html; charset=utf-8',
					'Cache-Control' => 'no-cache',
				),
				'body'    => Pages::render( 'install' ),
			);
		}

		// The app's own sign-in (#74), public like the install page and
		// never cached: it carries a nonce and answers a sign-in.
		if ( 'login' === $route ) {
			return AppLogin::response();
		}

		// No page of the app may sit in a shared cache: a screen names the
		// signed-in user, and the offline page is the worker's to keep.
		$headers = array(
			'Content-Type'  => 'text/html; charset=utf-8',
			'Cache-Control' => 'private, no-store',
		);

		if ( in_array( $route, self::SCREENS, true ) && ! is_user_logged_in() ) {
			$back = self::requested( $route );
			return array(
				'status'  => 302,
				'headers' => $headers + array( 'Location' => AppLogin::enabled() ? AppLogin::url( $back ) : wp_login_url( $back ) ),
				'body'    => '',
			);
		}

		// A required screen still to complete comes first (#80): every other
		// screen leads to it, carrying where the person was going; once
		// complete, it sends them on.
		if ( in_array( $route, self::SCREENS, true ) ) {
			$user    = wp_get_current_user();
			$pending = Screens::pending( $user );
			$here    = 'screen' === $route ? Screens::page( self::screen() ) : null;
			if ( null !== $pending && ( null === $here || $here['id'] !== $pending['id'] ) ) {
				return array(
					'status'  => 302,
					'headers' => $headers + array( 'Location' => Screens::requiredUrl( $pending, self::requested( $route ) ) ),
					'body'    => '',
				);
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- an address to go on to, kept inside the app by AppLogin::target().
			$next = isset( $_GET[ Screens::NEXT ] ) ? esc_url_raw( wp_unslash( (string) $_GET[ Screens::NEXT ] ) ) : '';
			if ( null === $pending && null !== $here && null !== $here['required'] && '' !== $next ) {
				return array(
					'status'  => 302,
					'headers' => $headers + array( 'Location' => AppLogin::target( $next ) ),
					'body'    => '',
				);
			}
		}

		// An unknown screen, one the user may not open, and a URL screen
		// (which opens at its own address) are one answer.
		if ( 'screen' === $route ) {
			if ( null === Screens::page( self::screen() ) ) {
				return array(
					'status'  => 404,
					'headers' => $headers,
					'body'    => Frame::render( 'missing' ),
				);
			}
		}

		if ( 'post' === $route ) {
			$post = Content::current();
			if ( null === $post ) {
				return array(
					'status'  => 404,
					'headers' => $headers,
					'body'    => Frame::render( 'missing' ),
				);
			}
			if ( ! Content::opensInApp( $post ) ) {
				return array(
					'status'  => 302,
					'headers' => $headers + array( 'Location' => (string) get_permalink( $post ) ),
					'body'    => '',
				);
			}
		}

		return array(
			'status'  => 200,
			'headers' => $headers,
			'body'    => in_array( $route, self::SCREENS, true ) ? Frame::render( $route ) : Pages::render(),
		);
	}

	/**
	 * The address of an app screen request, to come back to after a sign-in
	 * or a required screen: the screen, the post or the route.
	 *
	 * @param string $route The route.
	 */
	private static function requested( string $route ): string {
		if ( 'post' === $route ) {
			return Content::url( Content::requested() );
		}
		if ( 'screen' === $route ) {
			return home_url( '/' . self::prefix() . '/s/' . sanitize_key( self::screen() ) . '/' );
		}
		return self::url( $route );
	}

	/**
	 * The app route of the current request, or '' outside the app.
	 */
	public static function current(): string {
		return (string) get_query_var( self::QUERY_VAR );
	}

	/**
	 * The screen id of a `screen` request, or ''.
	 */
	public static function screen(): string {
		return (string) get_query_var( self::SCREEN_VAR );
	}

	/**
	 * Answer an app route and stop, or mark the request as not found.
	 */
	public static function dispatch(): void {
		$route = self::current();
		if ( '' === $route ) {
			return;
		}

		$response = self::response( $route );
		if ( null === $response ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		status_header( $response['status'] );
		foreach ( $response['headers'] as $name => $value ) {
			header( $name . ': ' . $value );
		}
		echo $response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the body is built escaped by Pages, or is JSON or the worker script.
		exit;
	}
}

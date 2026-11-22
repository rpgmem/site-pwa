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
	 * The service worker's file name, at the site root.
	 */
	public const SW_FILE = 'site-pwa-sw.js';

	/**
	 * Bumped whenever the rules change, so an install that updated without
	 * an activation still gets them: a GitHub update runs no activation hook.
	 */
	public const REVISION = 1;

	/**
	 * Option holding the rules revision last flushed. Deleted by uninstall.php.
	 */
	public const REVISION_OPTION = 'site_pwa_rewrite_revision';

	/**
	 * The routes and the path each one answers under the prefix.
	 */
	public const ROUTES = array( 'home', 'offline', 'manifest' );

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
		return $vars;
	}

	/**
	 * The URL of a route.
	 *
	 * @param string $route `home`, `offline`, `manifest` or `sw`.
	 */
	public static function url( string $route ): string {
		if ( 'sw' === $route ) {
			return home_url( '/' . self::SW_FILE );
		}
		$path = array(
			'home'     => '/',
			'offline'  => '/offline/',
			'manifest' => '/manifest.webmanifest',
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
				'headers' => array( 'Content-Type' => 'application/manifest+json; charset=utf-8' ),
				'body'    => (string) wp_json_encode( Manifest::data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			);
		}

		// No page of the app may sit in a shared cache: the home page names the
		// signed-in user, and the offline page is the worker's to keep.
		return array(
			'status'  => 200,
			'headers' => array(
				'Content-Type'  => 'text/html; charset=utf-8',
				'Cache-Control' => 'private, no-store',
			),
			'body'    => Pages::render( $route ),
		);
	}

	/**
	 * Answer an app route and stop, or mark the request as not found.
	 */
	public static function dispatch(): void {
		$route = (string) get_query_var( self::QUERY_VAR );
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

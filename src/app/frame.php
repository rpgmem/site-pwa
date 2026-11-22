<?php
/**
 * The app's frame: its own document around each screen.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;
use SitePwa\Settings;

/**
 * Draws a screen inside the app's own document.
 *
 * The document calls `wp_head()` and `wp_footer()`, so other plugins' assets
 * and shortcodes keep working inside it, but the active theme's stylesheets
 * and scripts are taken out: the app looks the same whatever theme the site
 * runs, and a theme cannot break its layout.
 */
final class Frame {

	/**
	 * Style and script handle of the app's own assets.
	 */
	public const HANDLE = 'site-pwa-app';

	/**
	 * Handle of the worker registration script.
	 */
	public const REGISTER_HANDLE = 'site-pwa-register';

	/**
	 * Wire the hooks that only act on an app screen.
	 */
	public static function register(): void {
		// Latest priority: the theme and every plugin have enqueued by then,
		// so the isolation sees the final queue.
		add_action( 'wp_enqueue_scripts', array( self::class, 'isolate' ), PHP_INT_MAX );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		// Early in wp_head, beside the charset and viewport the frame prints.
		add_action( 'wp_head', array( self::class, 'head' ), 1 );
		add_filter( 'script_loader_tag', array( self::class, 'scriptTag' ), 10, 2 );
		add_filter( 'show_admin_bar', array( self::class, 'adminBar' ) );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_filter( 'pre_get_document_title', array( self::class, 'title' ) );
	}

	/**
	 * Whether the request is an app screen.
	 */
	public static function active(): bool {
		return in_array( Routes::current(), Routes::SCREENS, true );
	}

	/**
	 * Render a screen in the frame.
	 *
	 * @param string $route A screen route.
	 */
	public static function render( string $route ): string {
		$settings = Settings::get();
		$user     = wp_get_current_user();

		$screen = self::template(
			'screen-' . $route,
			array(
				'user'        => $user,
				'first_name'  => '' !== (string) $user->first_name ? (string) $user->first_name : (string) $user->display_name,
				'profile_url' => get_edit_profile_url( $user->ID ),
				'logout_url'  => wp_logout_url( Routes::url( 'home' ) ),
			)
		);

		return self::template(
			'frame',
			array(
				'name'       => $settings['name'],
				'icon'       => Manifest::touchIcon(),
				'home'       => Routes::url( 'home' ),
				'navigation' => Navigation::items( $route ),
				'content'    => $screen,
			)
		);
	}

	/**
	 * Take the active theme's stylesheets and scripts out of an app screen.
	 * Core's block styles stay, because posts drawn in the app need them;
	 * the theme's global styles (theme.json) go with the theme.
	 */
	public static function isolate(): void {
		if ( ! self::active() ) {
			return;
		}
		$roots = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );
		foreach ( array( wp_styles(), wp_scripts() ) as $registry ) {
			foreach ( $registry->queue as $handle ) {
				$dependency = $registry->registered[ $handle ] ?? null;
				$src        = null === $dependency ? '' : (string) $dependency->src;
				foreach ( $roots as $root ) {
					if ( '' !== $src && 0 === strpos( $src, $root ) ) {
						$registry->dequeue( $handle );
					}
				}
			}
		}
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	}

	/**
	 * Enqueue the app's stylesheet and the worker registration.
	 */
	public static function enqueue(): void {
		if ( ! self::active() ) {
			return;
		}
		$css = Plugin::asset( 'assets/css/app.css' );
		$js  = Plugin::asset( 'assets/js/register.js' );
		wp_enqueue_style( self::HANDLE, Plugin::url() . $css, array(), Plugin::assetVersion( $css ) );
		wp_enqueue_script(
			self::REGISTER_HANDLE,
			Plugin::url() . $js,
			array(),
			Plugin::assetVersion( $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * The manifest, the theme colour and the home-screen icon.
	 */
	public static function head(): void {
		if ( ! self::active() ) {
			return;
		}
		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( Routes::url( 'manifest' ) ) );
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( Settings::get()['theme_color'] ) );
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( Manifest::touchIcon() ) );
	}

	/**
	 * Hand the registration script the worker's URL and scope.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle Its handle.
	 */
	public static function scriptTag( string $tag, string $handle ): string {
		if ( self::REGISTER_HANDLE !== $handle ) {
			return $tag;
		}
		$attributes = sprintf( ' data-sw="%s" data-scope="%s"', esc_url( Routes::url( 'sw' ) ), esc_attr( Routes::scope() ) );
		return (string) preg_replace( '/<script\b/', '<script' . $attributes, $tag, 1 );
	}

	/**
	 * No admin bar inside the app.
	 *
	 * @param bool $show Whether WordPress would show it.
	 */
	public static function adminBar( bool $show ): bool {
		return self::active() ? false : $show;
	}

	/**
	 * Keep the app's screens out of search engines.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( self::active() ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * The document title: the screen, then the app's name.
	 *
	 * @param string $title The title WordPress would print.
	 */
	public static function title( string $title ): string {
		if ( ! self::active() ) {
			return $title;
		}
		return Navigation::label( Routes::current() ) . ' – ' . Settings::get()['name'];
	}

	/**
	 * Render a template of `templates/` with its values.
	 *
	 * @param string               $name   Template name, without `.php`.
	 * @param array<string, mixed> $values What the template reads as `$site_pwa`.
	 */
	private static function template( string $name, array $values ): string {
		ob_start();
		( static function ( string $site_pwa_file, array $site_pwa ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the included template reads $site_pwa.
			include $site_pwa_file;
		} )( Plugin::dir() . 'templates/' . $name . '.php', $values );
		return (string) ob_get_clean();
	}
}

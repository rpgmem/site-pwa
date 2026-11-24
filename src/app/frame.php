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
	 * Handle of the Share / Copy link script on a post.
	 */
	public const SHARE_HANDLE = 'site-pwa-share';

	/**
	 * Handle of the home screen's script, which hides a dismissed banner.
	 */
	public const HOME_HANDLE = 'site-pwa-home';

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
	 * @param string $route A screen route, or `missing` for a post or a
	 *                      screen the app cannot show.
	 */
	public static function render( string $route ): string {
		$settings = Settings::get();
		$user     = wp_get_current_user();
		$post     = 'post' === $route ? Content::current() : null;
		$screen   = 'screen' === $route ? Screens::page( Routes::screen() ) : null;
		$home     = 'home' === $route ? Home::items( $user ) : null;
		$name     = '' !== (string) $user->display_name ? (string) $user->display_name : (string) $user->user_login;

		$content = self::template(
			'screen-' . $route,
			array(
				'screens'     => Screens::visible(),
				'news'        => 'home' === $route ? News::page( 1, News::LATEST ) : ( 'news' === $route ? News::page( News::requested() ) : null ),
				'news_page'   => News::requested(),
				'news_url'    => News::url(),
				'pages'       => in_array( $route, array( 'home', 'more' ), true ) ? AppPages::items() : array(),
				'more'        => Screens::split()['more'],
				'account_url' => Routes::url( 'account' ),
				'screen'      => $screen,
				'screen_html' => null === $screen ? '' : self::screenHtml( $screen ),
				'user'        => $user,
				'first_name'  => '' !== (string) $user->first_name ? (string) $user->first_name : (string) $user->display_name,
				'home_items'  => $home,
				'profile_url' => get_edit_profile_url( $user->ID ),
				'logout_url'  => wp_logout_url( Routes::url( 'home' ) ),
				'home'        => Routes::url( 'home' ),
				'permalink'   => null === $post ? '' : (string) get_permalink( $post ),
				'post_title'  => null === $post ? '' : wp_strip_all_tags( get_the_title( $post ) ),
			)
		);

		return self::template(
			'frame',
			array(
				'name'       => $settings['name'],
				'icon'       => Manifest::touchIcon(),
				'home'       => Routes::url( 'home' ),
				'route'      => $route,
				'greeting'   => Home::greeting(),
				'user_name'  => $name,
				'pending'    => null === $home ? 0 : count( $home['pending'] ),
				'back'       => self::back( $route ),
				'navigation' => Navigation::items( Navigation::key( $route, Routes::screen() ) ),
				'content'    => $content,
			)
		);
	}

	/**
	 * Where a screen's back arrow leads, or '' for a screen the navigation
	 * reaches directly: a post goes back to the news, the account to More.
	 *
	 * @param string $route The route being drawn.
	 */
	public static function back( string $route ): string {
		switch ( $route ) {
			case 'post':
				return News::url();
			case 'account':
				return Routes::url( 'more' );
			default:
				return '';
		}
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
		wp_add_inline_style( self::HANDLE, Identity::css() );
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
		if ( 'home' === Routes::current() ) {
			$home = Plugin::asset( 'assets/js/home.js' );
			wp_enqueue_script(
				self::HOME_HANDLE,
				Plugin::url() . $home,
				array(),
				Plugin::assetVersion( $home ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
		if ( 'post' === Routes::current() ) {
			$share = Plugin::asset( 'assets/js/share.js' );
			wp_enqueue_script(
				self::SHARE_HANDLE,
				Plugin::url() . $share,
				array(),
				Plugin::assetVersion( $share ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * The title, the manifest, the theme colour and the home-screen icon.
	 *
	 * WordPress prints the title only for a theme that declares `title-tag`,
	 * and a block theme declares it only while it renders its own template,
	 * which the frame never does: without this line the app has no title.
	 */
	public static function head(): void {
		if ( ! self::active() ) {
			return;
		}
		if ( ! current_theme_supports( 'title-tag' ) ) {
			printf( '<title>%s</title>' . "\n", esc_html( wp_get_document_title() ) );
		}
		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( Routes::url( 'manifest' ) ) );
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( Identity::current()['accent'] ) );
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
		return self::label( Routes::current() ) . ' – ' . Settings::get()['name'];
	}

	/**
	 * The name of what a route shows, for the document title.
	 *
	 * @param string $route A screen route.
	 */
	private static function label( string $route ): string {
		switch ( $route ) {
			case 'account':
				return __( 'Account', 'site-pwa' );
			case 'more':
				return __( 'More', 'site-pwa' );
			case 'news':
				return __( 'News', 'site-pwa' );
			case 'post':
				$post = Content::current();
				return null === $post ? __( 'Not available', 'site-pwa' ) : wp_strip_all_tags( get_the_title( $post ) );
			case 'screen':
				$screen = Screens::page( Routes::screen() );
				return null === $screen ? __( 'Not available', 'site-pwa' ) : $screen['label'];
			default:
				return __( 'Home', 'site-pwa' );
		}
	}

	/**
	 * A registered screen's HTML: what its callback prints, then what it
	 * returns. It is the consumer's markup, drawn as the consumer wrote it;
	 * escaping it is the consumer's job, as with a shortcode.
	 *
	 * @param array{render: callable|null} $screen The screen.
	 */
	private static function screenHtml( array $screen ): string {
		if ( null === $screen['render'] ) {
			return '';
		}
		ob_start();
		$returned = call_user_func( $screen['render'] );
		$printed  = (string) ob_get_clean();
		return $printed . ( is_string( $returned ) ? $returned : '' );
	}

	/**
	 * Render a template of `templates/` with its values.
	 *
	 * @param string               $name   Template name, without `.php`.
	 * @param array<string, mixed> $values What the template reads as `$site_pwa`.
	 */
	public static function template( string $name, array $values ): string {
		ob_start();
		( static function ( string $site_pwa_file, array $site_pwa ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the included template reads $site_pwa.
			include $site_pwa_file;
		} )( Plugin::dir() . 'templates/' . $name . '.php', $values );
		return (string) ob_get_clean();
	}
}

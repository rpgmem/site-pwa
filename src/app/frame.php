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
	 * Handle of More's script, which saves the dark theme on this device.
	 */
	public const MORE_HANDLE = 'site-pwa-more';

	/**
	 * Where this device keeps the dark theme chosen on More.
	 */
	public const DARK_KEY = 'site-pwa-dark';

	/**
	 * A class name the dark root list accepts: what a stylesheet can select.
	 */
	public const ROOT_CLASS_PATTERN = '/^-?[A-Za-z_][A-Za-z0-9_-]*$/';

	/**
	 * The classes the app adds to `<html>` when the person turns it dark on More.
	 *
	 * Another plugin whose screens have their own dark palette names the class
	 * that switches it on, so those screens turn dark with the frame. A name
	 * that is not a plain class name is dropped.
	 *
	 * @return list<string>
	 */
	public static function darkRootClasses(): array {
		/**
		 * The root classes that turn another plugin's screens dark inside the app.
		 *
		 * @param list<string> $classes Class names.
		 */
		$classes = apply_filters( 'site_pwa_dark_root_classes', array() );
		if ( ! is_array( $classes ) ) {
			return array();
		}
		$valid = array_filter(
			$classes,
			static fn( $name ): bool => is_string( $name ) && 1 === preg_match( self::ROOT_CLASS_PATTERN, $name )
		);
		return array_values( array_unique( $valid ) );
	}

	/**
	 * Applies the stored choice to the root element before the first paint.
	 */
	public static function darkScript(): string {
		return sprintf(
			"try{if(localStorage.getItem('%s')==='1'){var r=document.documentElement;r.setAttribute('data-site-pwa-scheme','dark');%s.forEach(function(c){r.classList.add(c);});document.querySelectorAll('meta[name=theme-color]').forEach(function(m){m.setAttribute('content','%s');m.removeAttribute('media');});}}catch(e){}",
			self::DARK_KEY,
			(string) wp_json_encode( self::darkRootClasses() ),
			self::DARK_BAR
		);
	}

	/**
	 * The colour of the phone's status and navigation bars in the dark
	 * theme: the dark palette's ground (`--site-pwa-bg` in app.css), so the
	 * bars match the screen instead of keeping the light theme's accent.
	 */
	public const DARK_BAR = '#10161d';

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
				'screens'         => Screens::visible(),
				'news'            => 'home' === $route ? News::page( 1, News::LATEST ) : ( 'news' === $route ? News::page( News::requested(), News::PER_PAGE, (int) ( News::requestedCategory()['id'] ?? 0 ) ) : null ),
				'news_categories' => 'news' === $route ? News::categories() : array(),
				'news_category'   => 'news' === $route ? News::requestedCategory() : null,
				'news_page'       => News::requested(),
				'news_url'        => News::url(),
				'pages'           => in_array( $route, array( 'home', 'more' ), true ) ? AppPages::items() : array(),
				'more'            => Screens::split()['more'],
				'profile'         => 'more' === $route ? self::profile( $user ) : null,
				'account_url'     => self::detailsUrl( $user ),
				'screen'          => $screen,
				'screen_html'     => null === $screen ? '' : self::screenHtml( $screen ),
				'user'            => $user,
				'first_name'      => '' !== (string) $user->first_name ? (string) $user->first_name : (string) $user->display_name,
				'home_items'      => $home,
				'profile_url'     => get_edit_profile_url( $user->ID ),
				'logout_url'      => wp_logout_url( Routes::url( 'home' ) ),
				'home'            => Routes::url( 'home' ),
				'permalink'       => null === $post ? '' : (string) get_permalink( $post ),
				'post_title'      => null === $post ? '' : wp_strip_all_tags( get_the_title( $post ) ),
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
				'lock'       => Lock::config( $user ),
			)
		);
	}

	/**
	 * Where More's "My details" leads: the screen a plugin names through the
	 * `site_pwa_details_screen` filter, when this user may open it, or the
	 * app's own account screen.
	 *
	 * @param \WP_User $user The person.
	 */
	public static function detailsUrl( \WP_User $user ): string {
		/**
		 * The id of a registered screen that holds the person's details.
		 *
		 * @param string   $id   A screen id, or '' for the app's own account screen.
		 * @param \WP_User $user The person.
		 */
		$id     = apply_filters( 'site_pwa_details_screen', '', $user );
		$screen = is_string( $id ) && '' !== $id ? Screens::find( $id ) : null;
		return null === $screen ? Routes::url( 'account' ) : Screens::link( $screen );
	}

	/**
	 * The profile card on More: the person's initials, name and one line
	 * about them. The line is empty unless a plugin gives one through the
	 * `site_pwa_profile_line` filter, such as a masked registration number
	 * and a role; it is shown as plain text.
	 *
	 * @param \WP_User $user The signed-in user.
	 * @return array{initials: string, name: string, line: string}
	 */
	public static function profile( \WP_User $user ): array {
		$name = '' !== (string) $user->display_name ? (string) $user->display_name : (string) $user->user_login;
		/**
		 * One line under the person's name on More.
		 *
		 * @param string   $line Empty by default.
		 * @param \WP_User $user The signed-in user.
		 */
		$line = apply_filters( 'site_pwa_profile_line', '', $user );
		return array(
			'initials' => self::initials( $name ),
			'name'     => $name,
			'line'     => is_string( $line ) ? trim( wp_strip_all_tags( $line ) ) : '',
		);
	}

	/**
	 * The first letters of the first and the last word of a name.
	 *
	 * @param string $name A name.
	 */
	public static function initials( string $name ): string {
		$words = preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $words || array() === $words ) {
			return '';
		}
		$first = mb_substr( $words[0], 0, 1 );
		$last  = count( $words ) > 1 ? mb_substr( (string) end( $words ), 0, 1 ) : '';
		return mb_strtoupper( $first . $last );
	}

	/**
	 * Where a screen's back arrow leads, or '' for a screen the navigation
	 * reaches directly (#44): the app screen the person came from, or Home
	 * when they arrived from outside the app (a link, a notification, a
	 * reload of a page reached that way).
	 *
	 * @param string $route The route being drawn.
	 */
	public static function back( string $route ): string {
		if ( ! in_array( $route, array( 'post', 'account', 'news' ), true ) ) {
			return '';
		}
		$previous = self::previous();
		return '' === $previous ? Routes::url( 'home' ) : $previous;
	}

	/**
	 * The app screen the request came from, read from the referrer: on this
	 * host, under the app's prefix, and not the page being drawn. Anything
	 * else (another site, a site page outside the app, the login, none) is ''.
	 */
	private static function previous(): string {
		$referer = wp_get_raw_referer();
		if ( ! is_string( $referer ) || '' === $referer ) {
			return '';
		}
		$from = wp_parse_url( $referer );
		$home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $from ) || ! is_array( $home ) || strtolower( (string) ( $from['host'] ?? '' ) ) !== strtolower( (string) ( $home['host'] ?? '' ) ) ) {
			return '';
		}
		$app  = Routes::scope() . Routes::prefix() . '/';
		$path = (string) ( $from['path'] ?? '' );
		if ( 0 !== strpos( trailingslashit( $path ), $app ) ) {
			return '';
		}
		$current = add_query_arg( array() );
		$here    = wp_parse_url( is_string( $current ) ? $current : '' );
		$same    = is_array( $here ) && ( $here['path'] ?? '' ) === $path && ( $here['query'] ?? '' ) === ( $from['query'] ?? '' );
		return $same ? '' : esc_url_raw( $referer );
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
		Lock::enqueue( wp_get_current_user() );
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
		if ( 'more' === Routes::current() ) {
			$more = Plugin::asset( 'assets/js/more.js' );
			wp_enqueue_script(
				self::MORE_HANDLE,
				Plugin::url() . $more,
				array(),
				Plugin::assetVersion( $more ),
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
		echo self::themeColorTags(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped inside themeColorTags().
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( Manifest::touchIcon() ) );
		// The dark theme chosen on More, on this device only (#23), applied
		// before the first paint, with the root classes other plugins name so
		// the screens they draw inside the frame turn dark with it (#48).
		wp_print_inline_script_tag( self::darkScript() );
		// The app lock (#38), also before the first paint: a locked app
		// shows nothing until the phone is unlocked.
		wp_print_inline_script_tag( Lock::headScript( wp_get_current_user() ) );
	}

	/**
	 * The phone's status and navigation bars: the accent in the light theme,
	 * the dark ground in the dark one, and both, by the device's setting,
	 * when the theme follows the device. The dark theme chosen on More turns
	 * them dark too (darkScript(), more.js).
	 */
	public static function themeColorTags(): string {
		$accent = Identity::current()['accent'];
		$scheme = Scheme::current();
		if ( Scheme::DARK === $scheme ) {
			return sprintf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( self::DARK_BAR ) );
		}
		if ( Scheme::AUTO === $scheme ) {
			return sprintf( '<meta name="theme-color" content="%s" media="(prefers-color-scheme: light)">' . "\n", esc_attr( $accent ) )
				. sprintf( '<meta name="theme-color" content="%s" media="(prefers-color-scheme: dark)">' . "\n", esc_attr( self::DARK_BAR ) );
		}
		return sprintf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( $accent ) );
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

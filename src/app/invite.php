<?php
/**
 * The invite to install the app, on phones.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;
use SitePwa\Settings;

/**
 * Prints the invite's markup and loads the script that decides whether to
 * show it: a card on the app's home screen, and a "Use the app" banner on the
 * site's own pages for signed-in visitors. Both are hidden in the markup;
 * `assets/js/install.js` reveals them only on a phone or tablet, outside the
 * installed app, and not after a dismissal in the last 30 days.
 */
final class Invite {

	/**
	 * Script handle, shared by the card and the banner.
	 */
	public const SCRIPT = 'site-pwa-install';

	/**
	 * Stylesheet handle of the banner on the site's pages.
	 */
	public const BANNER_STYLE = 'site-pwa-site-banner';

	/**
	 * Wire the hooks.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		// In the footer, so the banner sits after the theme's content and
		// before the script that reveals it.
		add_action( 'wp_footer', array( self::class, 'banner' ), 5 );
	}

	/**
	 * Whether the app's home screen is being drawn.
	 */
	public static function onAppHome(): bool {
		return Settings::enabled() && 'home' === Routes::current();
	}

	/**
	 * Whether a site page should carry the banner: the app is on, the
	 * setting allows it, the visitor is signed in (the app requires it) and
	 * the request is not one of the app's own.
	 */
	public static function bannerWanted(): bool {
		return Settings::enabled()
			&& Settings::get()['site_banner']
			&& is_user_logged_in()
			&& ! is_admin()
			&& '' === Routes::current();
	}

	/**
	 * Load the invite's script where an invite is printed, and the banner's
	 * stylesheet on site pages.
	 */
	public static function enqueue(): void {
		$banner = self::bannerWanted();
		if ( ! $banner && ! self::onAppHome() ) {
			return;
		}
		$js = Plugin::asset( 'assets/js/install.js' );
		wp_enqueue_script(
			self::SCRIPT,
			Plugin::url() . $js,
			array(),
			Plugin::assetVersion( $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		if ( $banner ) {
			$css = Plugin::asset( 'assets/css/site-banner.css' );
			wp_enqueue_style( self::BANNER_STYLE, Plugin::url() . $css, array(), Plugin::assetVersion( $css ) );
			$look = Identity::current();
			wp_add_inline_style(
				self::BANNER_STYLE,
				sprintf( '.site-pwa-site-banner{--site-pwa-accent:%1$s;--site-pwa-on-accent:%2$s}', $look['accent'], Identity::onAccent( $look['accent'] ) )
			);
		}
	}

	/**
	 * Print the banner on a site page.
	 */
	public static function banner(): void {
		if ( ! self::bannerWanted() ) {
			return;
		}
		$settings = Settings::get();
		$html     = Frame::template(
			'site-banner',
			array(
				'name' => $settings['short_name'],
				'url'  => Routes::url( 'home' ),
				'icon' => Manifest::touchIcon(),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a template, built escaped.
	}
}

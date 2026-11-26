<?php
/**
 * The web app manifest.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Settings;

/**
 * Builds the manifest that makes the app installable.
 */
final class Manifest {

	/**
	 * The manifest, as an array for `wp_json_encode()`.
	 *
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		$settings = Settings::get();
		$home     = Routes::url( 'home' );
		// Android paints the installed app's navigation bar from these two
		// colours, fixed when the app is installed or refreshed; the page's
		// theme-color meta only reaches the status bar. So a site whose
		// scheme is dark hands the dark ground here too.
		$dark = Scheme::DARK === Scheme::current();

		return array(
			'id'                   => $home,
			'name'                 => $settings['name'],
			'short_name'           => $settings['short_name'],
			'start_url'            => $home,
			'scope'                => $home,
			'display'              => 'standalone',
			'background_color'     => $dark ? Frame::DARK_BAR : $settings['background_color'],
			'theme_color'          => $dark ? Frame::DARK_BAR : Identity::current()['accent'],
			'lang'                 => (string) get_bloginfo( 'language' ),
			'dir'                  => is_rtl() ? 'rtl' : 'ltr',
			'icons'                => self::icons(),
			// The app names itself, so its install page can ask the browser
			// whether it is already installed (getInstalledRelatedApps, #34).
			// It is not preferred over itself: the browser still offers it.
			'related_applications' => array(
				array(
					'platform' => 'webapp',
					'url'      => Routes::url( 'manifest' ),
				),
			),
		);
	}

	/**
	 * The app's icons, from the source chosen in the settings (AppIcon).
	 *
	 * @return list<array{src: string, sizes: string, type: string, purpose: string}>
	 */
	public static function icons(): array {
		return AppIcon::icons();
	}

	/**
	 * The icon a home screen on iOS reads (`apple-touch-icon`).
	 */
	public static function touchIcon(): string {
		return self::icons()[0]['src'];
	}
}

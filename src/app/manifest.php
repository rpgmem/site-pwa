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

		return array(
			'id'               => $home,
			'name'             => $settings['name'],
			'short_name'       => $settings['short_name'],
			'start_url'        => $home,
			'scope'            => $home,
			'display'          => 'standalone',
			'background_color' => $settings['background_color'],
			'theme_color'      => Identity::current()['accent'],
			'lang'             => (string) get_bloginfo( 'language' ),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'icons'            => self::icons(),
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

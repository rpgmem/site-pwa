<?php
/**
 * The app's light and dark theme.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Settings;

/**
 * Whether the app draws light, dark, or follows the device.
 *
 * The app's frame and the screens other plugins draw inside it have to agree:
 * a dark header around a light screen reads as two apps. So the choice can
 * come from another plugin, the way the accent can (see {@see Identity}):
 * Free Form Certificate's Dark Mode setting is the built-in source, and
 * others join through `site_pwa_scheme_sources`. The app's own choices are
 * `auto` (follow the device), `light` and `dark`.
 *
 * @phpstan-type SchemeSource array{label: string, read: callable(): (string|null)}
 */
final class Scheme {

	/**
	 * Follow the device's light or dark setting.
	 */
	public const AUTO = 'auto';

	/**
	 * Always light.
	 */
	public const LIGHT = 'light';

	/**
	 * Always dark.
	 */
	public const DARK = 'dark';

	/**
	 * The default choice: the source Free Form Certificate registers under this
	 * id through `site_pwa_scheme_sources`. It is a stored value, so it stays;
	 * where no plugin offers it, the choice means `auto`.
	 */
	public const FFC = 'ffc';

	/**
	 * The app's own choices.
	 *
	 * @return array<string, string> Labels by value.
	 */
	public static function own(): array {
		return array(
			self::AUTO  => __( 'Follow the device', 'site-pwa' ),
			self::LIGHT => __( 'Always light', 'site-pwa' ),
			self::DARK  => __( 'Always dark', 'site-pwa' ),
		);
	}

	/**
	 * The other plugins the choice can come from, by id.
	 *
	 * @return array<string, SchemeSource>
	 */
	public static function sources(): array {
		$sources = array();
		/**
		 * The plugins the app's light and dark theme can follow.
		 *
		 * Each entry is `id => [ 'label' => string, 'read' => callable ]`.
		 * The callable returns `auto`, `light` or `dark`, or null when it
		 * has nothing to say.
		 *
		 * @param array<string, SchemeSource> $sources Sources by id.
		 */
		$sources = apply_filters( 'site_pwa_scheme_sources', $sources );
		return is_array( $sources ) ? array_filter(
			$sources,
			static fn( $source, $id ): bool => ! isset( self::own()[ $id ] ) && is_array( $source ) && isset( $source['label'], $source['read'] ) && is_callable( $source['read'] ),
			ARRAY_FILTER_USE_BOTH
		) : array();
	}

	/**
	 * The theme in use: `auto`, `light` or `dark`.
	 *
	 * A source that is gone, or answers nothing usable, falls back to `auto`,
	 * which is what the app did before the choice existed.
	 */
	public static function current(): string {
		$choice = Settings::get()['color_scheme'];
		if ( isset( self::own()[ $choice ] ) ) {
			return $choice;
		}
		$sources = self::sources();
		if ( ! isset( $sources[ $choice ] ) ) {
			return self::AUTO;
		}
		$value = call_user_func( $sources[ $choice ]['read'] );
		return is_string( $value ) && isset( self::own()[ $value ] ) ? $value : self::AUTO;
	}

	/**
	 * The class the app's `<body>` carries for the theme in use.
	 */
	public static function bodyClass(): string {
		return 'site-pwa-scheme-' . self::current();
	}
}

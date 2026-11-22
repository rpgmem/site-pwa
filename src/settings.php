<?php
/**
 * The plugin's settings, stored in one option.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa;

/**
 * Reads, defaults and sanitizes the `site_pwa_settings` option.
 *
 * The app is off until an administrator turns it on: a site that installs the
 * plugin gets no manifest and no service worker by surprise.
 *
 * @phpstan-type SettingsShape array{enabled: bool, name: string, short_name: string, theme_color: string, background_color: string}
 */
final class Settings {

	/**
	 * Option key. Deleted by uninstall.php.
	 */
	public const OPTION = 'site_pwa_settings';

	/**
	 * The longest short name the manifest carries; home screens truncate
	 * beyond about twelve characters.
	 */
	public const SHORT_NAME_MAX = 12;

	/**
	 * The defaults, the site's name included.
	 *
	 * @return SettingsShape
	 */
	public static function defaults(): array {
		$name = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		return array(
			'enabled'          => false,
			'name'             => $name,
			'short_name'       => self::shorten( $name ),
			'theme_color'      => '#1d5e8c',
			'background_color' => '#ffffff',
		);
	}

	/**
	 * The stored settings over the defaults, every value in its type.
	 *
	 * @return SettingsShape
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Whether the app is on.
	 */
	public static function enabled(): bool {
		return self::get()['enabled'];
	}

	/**
	 * Clean a submitted or stored value; anything missing or invalid takes
	 * its default, so a cleared field never stores an empty name or colour.
	 *
	 * @param mixed $input Raw value.
	 * @return SettingsShape
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$name       = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$name       = '' === $name ? $defaults['name'] : $name;
		$short_name = sanitize_text_field( (string) ( $input['short_name'] ?? '' ) );
		$short_name = '' === $short_name ? self::shorten( $name ) : self::shorten( $short_name );

		return array(
			'enabled'          => ! empty( $input['enabled'] ),
			'name'             => $name,
			'short_name'       => $short_name,
			'theme_color'      => self::color( $input['theme_color'] ?? '', $defaults['theme_color'] ),
			'background_color' => self::color( $input['background_color'] ?? '', $defaults['background_color'] ),
		);
	}

	/**
	 * A `#rrggbb` colour, or the fallback.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Default colour.
	 */
	private static function color( $value, string $fallback ): string {
		$color = sanitize_hex_color( is_string( $value ) ? $value : '' );
		return is_string( $color ) && '' !== $color ? strtolower( $color ) : $fallback;
	}

	/**
	 * Cut a name to the short-name length, on a character boundary.
	 *
	 * @param string $name Name to shorten.
	 */
	private static function shorten( string $name ): string {
		return trim( mb_substr( $name, 0, self::SHORT_NAME_MAX ) );
	}
}

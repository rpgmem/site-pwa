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
 * @phpstan-type SettingsShape array{enabled: bool, replace_confirmed: bool, name: string, short_name: string, theme_color: string, background_color: string, identity_source: string, color_scheme: string, site_banner: bool, site_banner_guests: bool, news_categories: list<int>, app_pages: array<int, bool>}
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
			'enabled'            => false,
			'replace_confirmed'  => false,
			'name'               => $name,
			'short_name'         => self::shorten( $name ),
			'theme_color'        => '#1d5e8c',
			'background_color'   => '#ffffff',
			'identity_source'    => App\Identity::OWN,
			'color_scheme'       => App\Scheme::FFC,
			'site_banner'        => true,
			'site_banner_guests' => true,
			'news_categories'    => array(),
			'app_pages'          => array(),
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
	 * Whether the app is on: turned on, and either no known plugin competes
	 * for the root service worker or the administrator confirmed replacing it.
	 */
	public static function enabled(): bool {
		$settings = self::get();
		return $settings['enabled'] && ( $settings['replace_confirmed'] || array() === App\Conflicts::active() );
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
			'enabled'            => ! empty( $input['enabled'] ),
			'replace_confirmed'  => ! empty( $input['replace_confirmed'] ),
			'name'               => $name,
			'short_name'         => $short_name,
			'theme_color'        => self::color( $input['theme_color'] ?? '', $defaults['theme_color'] ),
			'background_color'   => self::color( $input['background_color'] ?? '', $defaults['background_color'] ),
			'identity_source'    => self::source( $input['identity_source'] ?? '' ),
			'color_scheme'       => self::scheme( $input['color_scheme'] ?? '' ),
			// Absent means "never saved", so the default holds; the form posts
			// a hidden 0 before the checkbox, so unticking it is a real false.
			'site_banner'        => array_key_exists( 'site_banner', $input ) ? ! empty( $input['site_banner'] ) : $defaults['site_banner'],
			'site_banner_guests' => array_key_exists( 'site_banner_guests', $input ) ? ! empty( $input['site_banner_guests'] ) : $defaults['site_banner_guests'],
			'news_categories'    => self::ids( $input['news_categories'] ?? array() ),
			'app_pages'          => self::pages( $input['app_pages'] ?? array() ),
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
	 * Positive integer ids, unique, in their order. An empty list of news
	 * categories means every category.
	 *
	 * @param mixed $value Raw value.
	 * @return list<int>
	 */
	private static function ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( $value as $id ) {
			// A negative or non-numeric entry is dropped, not turned positive.
			if ( is_numeric( $id ) && (int) $id > 0 ) {
				$ids[] = (int) $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * The pages chosen for the app: page id => whether it opens on the site.
	 *
	 * Stored as `id => bool`; the settings form posts `id => [show, site]`,
	 * where a page without `show` is left out.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, bool>
	 */
	private static function pages( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$pages = array();
		foreach ( $value as $id => $choice ) {
			if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
				continue;
			}
			$id = (int) $id;
			if ( is_array( $choice ) ) {
				if ( ! empty( $choice['show'] ) ) {
					$pages[ $id ] = ! empty( $choice['site'] );
				}
				continue;
			}
			$pages[ $id ] = (bool) $choice;
		}
		return $pages;
	}

	/**
	 * A source id. Kept even when that source is not available now (its
	 * plugin may be deactivated for a while): App\Identity falls back to the
	 * own settings until it is back.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function source( $value ): string {
		$id = sanitize_key( is_string( $value ) ? $value : '' );
		return '' === $id ? App\Identity::OWN : $id;
	}

	/**
	 * The light and dark choice: one of App\Scheme's own values, or a source
	 * id, kept like an identity source while its plugin is away. Empty takes
	 * the default, Free Form Certificate's Dark Mode, which means "follow the
	 * device" on a site without FFC.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function scheme( $value ): string {
		$id = sanitize_key( is_string( $value ) ? $value : '' );
		return '' === $id ? App\Scheme::FFC : $id;
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

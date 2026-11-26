<?php
/**
 * The app's look: its accent and its corners.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Settings;

/**
 * The accent colour and the corner radii the app draws with.
 *
 * They come from the plugin's own settings (the theme colour and the
 * stylesheet's corners), or from another plugin's design when the
 * administrator picks one as the source and that design is switched on.
 * Free Form Certificate's Public Design is the built-in source; others join
 * through `site_pwa_identity_sources`.
 *
 * The accent applies to the light theme only: the dark palette keeps its own
 * accent, chosen to read on a dark ground, as FFC does with its design.
 *
 * @phpstan-type Look array{accent: string, radius: int, control_radius: int, source: string}
 * @phpstan-type Source array{label: string, read: callable(): (array{accent?: mixed, radius?: mixed, control_radius?: mixed}|null)}
 */
final class Identity {

	/**
	 * The stylesheet's own corners: cards, and buttons and fields.
	 */
	public const RADIUS = 12;

	/**
	 * The stylesheet's own corners of buttons and fields.
	 */
	public const CONTROL_RADIUS = 8;

	/**
	 * The largest corner a source may set.
	 */
	public const RADIUS_MAX = 24;

	/**
	 * The `identity_source` value for the plugin's own settings.
	 */
	public const OWN = 'own';

	/**
	 * The sources available on this site, by id.
	 *
	 * @return array<string, Source>
	 */
	public static function sources(): array {
		$sources = array();
		/**
		 * The designs the app can take its accent and corners from.
		 *
		 * Each entry is `id => [ 'label' => string, 'read' => callable ]`. The
		 * callable returns `accent` (`#rrggbb`), `radius` (cards, px) and
		 * `control_radius` (buttons and fields, px), any of them optional, or
		 * null while that design is switched off.
		 *
		 * @param array<string, Source> $sources Sources by id.
		 */
		$sources = apply_filters( 'site_pwa_identity_sources', $sources );
		return is_array( $sources ) ? array_filter(
			$sources,
			static fn( $source, $id ): bool => self::OWN !== $id && is_array( $source ) && isset( $source['label'], $source['read'] ) && is_callable( $source['read'] ),
			ARRAY_FILTER_USE_BOTH
		) : array();
	}

	/**
	 * The look in use.
	 *
	 * @return Look
	 */
	public static function current(): array {
		$own = array(
			'accent'         => Settings::get()['theme_color'],
			'radius'         => self::RADIUS,
			'control_radius' => self::CONTROL_RADIUS,
			'source'         => self::OWN,
		);

		$id      = Settings::get()['identity_source'];
		$sources = self::sources();
		if ( self::OWN === $id || ! isset( $sources[ $id ] ) ) {
			return $own;
		}

		$values = call_user_func( $sources[ $id ]['read'] );
		if ( ! is_array( $values ) ) {
			return $own;
		}

		$accent = sanitize_hex_color( is_string( $values['accent'] ?? null ) ? $values['accent'] : '' );
		return array(
			'accent'         => is_string( $accent ) && '' !== $accent ? strtolower( $accent ) : $own['accent'],
			'radius'         => self::corner( $values['radius'] ?? null, $own['radius'] ),
			'control_radius' => self::corner( $values['control_radius'] ?? null, $own['control_radius'] ),
			'source'         => $id,
		);
	}

	/**
	 * The light page background every surface sits on; the readable shade
	 * must reach its floor on it as well as on white.
	 */
	public const PAGE_BACKGROUND = '#f4f6f8';

	/**
	 * How much of the accent tints the Info tone's background.
	 */
	public const TINT = 0.12;

	/**
	 * The contrast text needs (WCAG AA).
	 */
	public const TEXT_CONTRAST = 4.5;

	/**
	 * The custom properties that carry the look, for the app's stylesheet.
	 * Every value is validated in current(), which is what makes them safe
	 * to print.
	 */
	public static function css(): string {
		$look = self::current();
		return sprintf(
			':root{--site-pwa-radius:%1$dpx;--site-pwa-control-radius:%2$dpx;--site-pwa-accent:%3$s;--site-pwa-on-accent:%4$s;--site-pwa-accent-text:%5$s;--site-pwa-info-bg:%6$s}',
			$look['radius'],
			$look['control_radius'],
			$look['accent'],
			self::onAccent( $look['accent'] ),
			self::accentText( $look['accent'] ),
			self::tint( $look['accent'] )
		);
	}

	/**
	 * The accent as it may be drawn as text or an icon on the light theme's
	 * surfaces (#26): the accent itself when it reads there, otherwise the
	 * first darker shade of the same hue that does. Where the accent is a
	 * background, the exact colour stays and onAccent() picks the text.
	 *
	 * @param string $hex `#rrggbb`.
	 */
	public static function accentText( string $hex ): string {
		$grounds = array( '#ffffff', self::PAGE_BACKGROUND, self::tint( $hex ) );
		for ( $step = 0; $step <= 50; $step++ ) {
			$shade = self::scale( $hex, 1 - $step / 50 );
			if ( min( array_map( static fn( string $ground ): float => self::contrast( $shade, $ground ), $grounds ) ) >= self::TEXT_CONTRAST ) {
				return $shade;
			}
		}
		return '#000000';
	}

	/**
	 * A light tint of a colour: TINT of it over white.
	 *
	 * @param string $hex `#rrggbb`.
	 */
	public static function tint( string $hex ): string {
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$channel = hexdec( substr( $hex, 1 + 2 * $i, 2 ) );
			$out    .= sprintf( '%02x', (int) round( 255 - ( 255 - $channel ) * self::TINT ) );
		}
		return $out;
	}

	/**
	 * The WCAG contrast ratio between two `#rrggbb` colours.
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 */
	public static function contrast( string $a, string $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * A colour with every channel multiplied by a factor, which keeps its hue.
	 *
	 * @param string $hex    `#rrggbb`.
	 * @param float  $factor 0 to 1.
	 */
	private static function scale( string $hex, float $factor ): string {
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$out .= sprintf( '%02x', (int) round( hexdec( substr( $hex, 1 + 2 * $i, 2 ) ) * $factor ) );
		}
		return $out;
	}

	/**
	 * Black or white, whichever reads better on a colour (WCAG contrast).
	 *
	 * @param string $hex `#rrggbb`.
	 */
	public static function onAccent( string $hex ): string {
		$luminance = self::luminance( $hex );
		// Contrast with white is 1.05 / (L + 0.05); with black, (L + 0.05) / 0.05.
		return ( 1.05 / ( $luminance + 0.05 ) ) >= ( ( $luminance + 0.05 ) / 0.05 ) ? '#ffffff' : '#000000';
	}

	/**
	 * Relative luminance of a `#rrggbb` colour.
	 *
	 * @param string $hex Colour.
	 */
	private static function luminance( string $hex ): float {
		$sum = 0.0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$channel = hexdec( substr( $hex, 1 + 2 * $i, 2 ) ) / 255;
			$linear  = $channel <= 0.03928 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4;
			$sum    += $weight * $linear;
		}
		return $sum;
	}

	/**
	 * A corner in px within the bounds, or the fallback.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $fallback Default.
	 */
	private static function corner( $value, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		return max( 0, min( self::RADIUS_MAX, (int) $value ) );
	}
}

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
		if ( class_exists( '\FreeFormCertificate\Core\PublicDesign' ) ) {
			$sources['ffc'] = array(
				'label' => __( 'Free Form Certificate: Public Design', 'site-pwa' ),
				'read'  => array( self::class, 'readFfc' ),
			);
		}
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
	 * Free Form Certificate's Public Design, or null while it is off: switched
	 * off, it stands for FFC's stylesheet defaults, not for a choice.
	 *
	 * Read through the public API only (`from_settings()` and the public
	 * properties), and validated in current() like any other source.
	 *
	 * @return array{accent: mixed, radius: mixed, control_radius: mixed}|null
	 */
	public static function readFfc(): ?array {
		$factory = array( '\FreeFormCertificate\Core\PublicDesign', 'from_settings' );
		if ( ! is_callable( $factory ) ) {
			return null;
		}
		$design = call_user_func( $factory );
		$values = is_object( $design ) ? get_object_vars( $design ) : array();
		if ( empty( $values['enabled'] ) ) {
			return null;
		}
		return array(
			'accent'         => $values['accent'] ?? null,
			'radius'         => $values['card_radius'] ?? null,
			'control_radius' => $values['radius'] ?? null,
		);
	}

	/**
	 * The custom properties that carry the look, for the app's stylesheet.
	 * Every value is validated in current(), which is what makes them safe
	 * to print.
	 */
	public static function css(): string {
		$look = self::current();
		return sprintf(
			':root{--site-pwa-radius:%1$dpx;--site-pwa-control-radius:%2$dpx}'
			. '@media not all and (prefers-color-scheme:dark){:root{--site-pwa-accent:%3$s;--site-pwa-on-accent:%4$s}}',
			$look['radius'],
			$look['control_radius'],
			$look['accent'],
			self::onAccent( $look['accent'] )
		);
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

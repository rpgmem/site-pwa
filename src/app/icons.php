<?php
/**
 * The app's icons.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

/**
 * A closed set of 24×24 stroke drawings in `currentColor`, so an icon takes
 * the colour of the text beside it in both themes.
 */
final class Icons {

	/**
	 * Drawings by name: only path data, so nothing pasted can bring a script
	 * or a colour of its own.
	 */
	public const PATHS = array(
		'home'   => array( 'M3 10.5 12 3l9 7.5', 'M5 9.5V21h5v-6h4v6h5V9.5' ),
		'user'   => array( 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 21a8 8 0 0 1 16 0' ),
		'logout' => array( 'M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4', 'M10 17l5-5-5-5', 'M15 12H3' ),
		'edit'   => array( 'M4 20h4L19 9l-4-4L4 16v4z', 'M14 6l4 4' ),
	);

	/**
	 * An icon as inline SVG, hidden from assistive technology because the
	 * text beside it carries the meaning. An unknown name draws nothing.
	 *
	 * @param string $name Icon name.
	 */
	public static function svg( string $name ): string {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		$paths = '';
		foreach ( self::PATHS[ $name ] as $d ) {
			$paths .= '<path d="' . esc_attr( $d ) . '"/>';
		}
		return '<svg class="site-pwa-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}
}

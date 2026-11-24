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
		'home'     => array( 'M3 10.5 12 3l9 7.5', 'M5 9.5V21h5v-6h4v6h5V9.5' ),
		'user'     => array( 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 21a8 8 0 0 1 16 0' ),
		'logout'   => array( 'M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4', 'M10 17l5-5-5-5', 'M15 12H3' ),
		'edit'     => array( 'M4 20h4L19 9l-4-4L4 16v4z', 'M14 6l4 4' ),
		'share'    => array( 'M12 3v12', 'M8 7l4-4 4 4', 'M5 12v7a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-7' ),
		'link'     => array( 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1', 'M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1' ),
		'open'     => array( 'M14 4h6v6', 'M20 4l-9 9', 'M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5' ),
		'more'     => array( 'M5 12h.01', 'M12 12h.01', 'M19 12h.01' ),
		'grid'     => array( 'M4 4h6v6H4z', 'M14 4h6v6h-6z', 'M4 14h6v6H4z', 'M14 14h6v6h-6z' ),
		'calendar' => array( 'M4 6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z', 'M4 10h16', 'M8 3v4', 'M16 3v4' ),
		'check'    => array( 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M8 12l3 3 5-6' ),
		'document' => array( 'M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z', 'M14 3v5h5', 'M9 13h6', 'M9 17h6' ),
		'chevron'  => array( 'M9 6l6 6-6 6' ),
		'add'      => array( 'M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z', 'M12 8v8', 'M8 12h8' ),
		'close'    => array( 'M6 6l12 12', 'M18 6L6 18' ),
		'download' => array( 'M12 4v11', 'M7 10l5 5 5-5', 'M5 20h14' ),
		'news'     => array( 'M5 5h11v14H6a1 1 0 0 1-1-1V5z', 'M16 9h3v9a1 1 0 0 1-1 1h-2', 'M8 9h5', 'M8 13h5', 'M8 16h3' ),
		'qr'       => array( 'M4 4h6v6H4z', 'M14 4h6v6h-6z', 'M4 14h6v6H4z', 'M14 14h3v3h-3z', 'M20 14v.01', 'M14 20h.01', 'M17 20h3' ),
		'bell'     => array( 'M6 9a6 6 0 0 1 12 0c0 6 2.5 8 2.5 8h-17S6 15 6 9z', 'M10.3 20a1.9 1.9 0 0 0 3.4 0' ),
		'back'     => array( 'M15 6l-6 6 6 6' ),
		'award'    => array( 'M12 14a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11z', 'M15 13l1.5 8L12 18.5 7.5 21 9 13' ),
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

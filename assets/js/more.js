/**
 * More's dark theme switch (#23): kept on this device only, in this
 * browser's storage. On, the frame and the screens inside it turn dark
 * before the first paint (see Frame::darkScript()); off, the site's own
 * choice applies again, so the page reloads to let it.
 */
( function () {
	'use strict';

	var KEY = 'site-pwa-dark';

	/**
	 * Wire the switch.
	 *
	 * @param {HTMLInputElement} input   The switch.
	 * @param {Storage|null}     storage Where the choice is kept.
	 * @param {Element}          root    The document's root element.
	 * @param {Function}         reload  Reloads the page.
	 */
	function init( input, storage, root, reload ) {
		input.checked = read( storage );
		input.addEventListener( 'change', function () {
			try {
				if ( input.checked ) {
					storage.setItem( KEY, '1' );
				} else {
					storage.removeItem( KEY );
				}
			} catch {
				// Storage refused: the choice lasts until the next page.
			}
			if ( input.checked ) {
				root.setAttribute( 'data-site-pwa-scheme', 'dark' );
				classes( input ).forEach( function ( name ) {
					root.classList.add( name );
				} );
				return;
			}
			reload();
		} );
	}

	/**
	 * The root classes other plugins name for their dark screens (#48).
	 *
	 * @param {HTMLInputElement} input The switch, which carries them as JSON.
	 * @return {string[]} Class names; none when the list is missing or broken.
	 */
	function classes( input ) {
		try {
			var list = JSON.parse( input.getAttribute( 'data-site-pwa-dark-classes' ) || '[]' );
			return Array.isArray( list ) ? list.filter( function ( name ) {
				return 'string' === typeof name && /^-?[A-Za-z_][A-Za-z0-9_-]*$/.test( name );
			} ) : [];
		} catch {
			return [];
		}
	}

	function read( storage ) {
		try {
			return !! storage && '1' === storage.getItem( KEY );
		} catch {
			return false;
		}
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { init: init };
		return;
	}

	var input = document.querySelector( '[data-site-pwa-dark]' );
	if ( ! input ) {
		return;
	}
	var storage = null;
	try {
		storage = window.localStorage;
	} catch {
		storage = null;
	}
	init( input, storage, document.documentElement, function () {
		window.location.reload();
	} );
}() );

/**
 * The home screen's banners: one the person dismissed stays hidden on this
 * device. The choice lives in this browser only, keyed by the banner's id,
 * so a new banner from the same plugin shows again.
 */
( function () {
	'use strict';

	var PREFIX = 'site-pwa-banner:';

	/**
	 * Hide the dismissed banners and wire the Dismiss buttons.
	 *
	 * @param {ParentNode} root    Where the banners are.
	 * @param {Storage}    storage Where dismissals are kept (injected for tests).
	 */
	function init( root, storage ) {
		var banners = root.querySelectorAll( '[data-banner]' );
		Array.prototype.forEach.call( banners, function ( banner ) {
			var key = PREFIX + banner.getAttribute( 'data-banner' );
			if ( read( storage, key ) ) {
				banner.hidden = true;
				return;
			}
			var button = banner.querySelector( '[data-dismiss]' );
			if ( ! button ) {
				return;
			}
			button.addEventListener( 'click', function () {
				banner.hidden = true;
				write( storage, key );
			} );
		} );
	}

	function read( storage, key ) {
		try {
			return !! storage && null !== storage.getItem( key );
		} catch {
			return false;
		}
	}

	function write( storage, key ) {
		try {
			if ( storage ) {
				storage.setItem( key, String( Date.now() ) );
			}
		} catch {
			// Storage refused (private mode, quota): it is hidden for this visit.
		}
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { init: init };
		return;
	}

	var storage = null;
	try {
		storage = window.localStorage;
	} catch {
		storage = null;
	}
	init( document, storage );
}() );

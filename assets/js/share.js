/**
 * Share and Copy link on a post inside the app. Both hand out the post's
 * permalink (`data-url`), never the app URL, so a shared link opens on the
 * normal site.
 */
( function () {
	'use strict';

	/**
	 * Wire one share box.
	 *
	 * @param {HTMLElement} box Element carrying `data-url`, `data-title`,
	 *                          `data-copied` and `data-copy-failed`.
	 * @param {Navigator}   nav The navigator (injected for tests).
	 */
	function init( box, nav ) {
		var url = box.getAttribute( 'data-url' );
		var title = box.getAttribute( 'data-title' ) || '';
		var share = box.querySelector( '[data-share]' );
		var copy = box.querySelector( '[data-copy]' );
		var status = box.querySelector( '[data-status]' );

		if ( share && typeof nav.share === 'function' ) {
			share.hidden = false;
			share.addEventListener( 'click', function () {
				// The share sheet takes the app out of sight: not a reason to lock it.
				if ( typeof window !== 'undefined' && window.SitePwaLock ) {
					window.SitePwaLock.allowExit();
				}
				nav.share( { title: title, url: url } ).catch( function () {
					// Cancelled by the user, or refused: nothing to report.
				} );
			} );
		}

		if ( copy ) {
			copy.addEventListener( 'click', function () {
				var failed = function () {
					// The link as text, so it can be selected by hand.
					status.textContent = box.getAttribute( 'data-copy-failed' ) + ' ' + url;
				};
				if ( ! nav.clipboard || typeof nav.clipboard.writeText !== 'function' ) {
					failed();
					return;
				}
				nav.clipboard.writeText( url ).then( function () {
					status.textContent = box.getAttribute( 'data-copied' );
				}, failed );
			} );
		}
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { init: init };
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var boxes = document.querySelectorAll( '.site-pwa-share' );
		for ( var i = 0; i < boxes.length; i++ ) {
			init( boxes[ i ], navigator );
		}
	} );
}() );

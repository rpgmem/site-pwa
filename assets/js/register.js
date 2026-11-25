/**
 * Registers the app's service worker. The script tag carries the worker's
 * URL and scope in `data-sw` and `data-scope`.
 *
 * Also sends a link to another site to the phone's own browser (#40): inside
 * an installed app a plain link to another origin opens an in-app browser tab,
 * so it gets `target="_blank"` the moment it is followed. Links to this site,
 * downloads and `mailto:`/`tel:` links are left as they are.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		if ( event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}
		var link = event.target && event.target.closest ? event.target.closest( 'a[href]' ) : null;
		if ( ! link || link.hasAttribute( 'download' ) ) {
			return;
		}
		var url;
		try {
			url = new URL( link.getAttribute( 'href' ), window.location.href );
		} catch {
			return;
		}
		if ( ( 'http:' !== url.protocol && 'https:' !== url.protocol ) || url.origin === window.location.origin ) {
			return;
		}
		link.setAttribute( 'target', '_blank' );
		link.setAttribute( 'rel', 'noopener noreferrer' );
	} );

	var tag = document.currentScript;
	if ( ! tag || ! ( 'serviceWorker' in navigator ) ) {
		return;
	}
	var sw = tag.getAttribute( 'data-sw' );
	var scope = tag.getAttribute( 'data-scope' );

	window.addEventListener( 'load', function () {
		navigator.serviceWorker.register( sw, { scope: scope } ).catch( function () {
			// The app still works as a web page without its worker.
		} );
	} );
}() );

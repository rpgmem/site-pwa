/**
 * Registers the app's service worker. The script tag carries the worker's
 * URL and scope in `data-sw` and `data-scope`.
 */
( function () {
	'use strict';

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

/**
 * Settings → Site PWA: lists the service workers this browser already holds
 * for the site that are not the app's, and then requires the replacement to
 * be confirmed before the app can be turned on.
 */
( function () {
	'use strict';

	function check( box, registrations ) {
		var own = box.getAttribute( 'data-own' );
		var foreign = registrations.filter( function ( registration ) {
			var worker = registration.active || registration.waiting || registration.installing;
			return worker && worker.scriptURL.split( '?' )[ 0 ] !== own;
		} );
		if ( foreign.length === 0 ) {
			return 0;
		}

		var list = box.querySelector( '.site-pwa-sw-list' );
		foreign.forEach( function ( registration ) {
			var worker = registration.active || registration.waiting || registration.installing;
			var item = document.createElement( 'li' );
			var code = document.createElement( 'code' );
			code.textContent = worker.scriptURL;
			item.appendChild( code );
			list.appendChild( item );
		} );
		box.hidden = false;

		var enabled = document.getElementById( 'site-pwa-enabled' );
		var replace = document.getElementById( 'site-pwa-replace' );
		if ( enabled && replace ) {
			var sync = function () {
				replace.required = enabled.checked;
			};
			enabled.addEventListener( 'change', sync );
			sync();
		}
		return foreign.length;
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { check: check };
	}

	var box = typeof document !== 'undefined' && document.getElementById( 'site-pwa-sw-check' );
	if ( box && typeof navigator !== 'undefined' && navigator.serviceWorker && navigator.serviceWorker.getRegistrations ) {
		navigator.serviceWorker.getRegistrations().then( function ( registrations ) {
			check( box, registrations );
		} ).catch( function () {} );
	}
}() );

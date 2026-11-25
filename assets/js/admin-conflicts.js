/**
 * Settings → Site PWA: lists the service workers this browser already holds
 * for the site that would compete with the app's, and then requires the
 * replacement to be confirmed before the app can be turned on.
 *
 * A worker competes only when its scope reaches the app: the same scope as
 * the app's worker (the browser keeps one per scope, so one replaces the
 * other), or a narrower one that still covers the app's pages (the longest
 * matching scope controls a page). A worker scoped to another part of the
 * site, such as `/terminal/`, controls none of the app's pages and is left
 * out of the list.
 */
( function () {
	'use strict';

	function competes( registration, scope, app ) {
		if ( ! scope || ! app || typeof registration.scope !== 'string' ) {
			return true;
		}
		return registration.scope.indexOf( scope ) === 0 && app.indexOf( registration.scope ) === 0;
	}

	function check( box, registrations ) {
		var own = box.getAttribute( 'data-own' );
		var scope = box.getAttribute( 'data-scope' );
		var app = box.getAttribute( 'data-app' );
		var foreign = registrations.filter( function ( registration ) {
			var worker = registration.active || registration.waiting || registration.installing;
			return worker && worker.scriptURL.split( '?' )[ 0 ] !== own && competes( registration, scope, app );
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

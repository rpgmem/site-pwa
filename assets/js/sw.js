/**
 * The app's service worker. Site PWA serves it from the site root with
 * `self.SITE_PWA_CONFIG` in front of this file (App\ServiceWorker::config()).
 *
 * It acts on two kinds of request and lets every other one go to the network
 * untouched:
 * - a navigation to an app page: network first, the offline page if the
 *   network fails; the page itself is never cached, because it may carry
 *   personal data or a nonce;
 * - one of the files it precached (the offline page, the app's stylesheet):
 *   served from the cache.
 */
( function ( scope ) {
	'use strict';

	// Paths that always go to the network, even under the app's path.
	var NEVER = [ /\/wp-admin(\/|$)/, /\/wp-login\.php$/, /\/wp-json(\/|$)/, /\/admin-ajax\.php$/, /\/xmlrpc\.php$/ ];

	/**
	 * How the worker answers a request.
	 *
	 * @param {URL}    url    Request URL.
	 * @param {string} method HTTP method.
	 * @param {string} mode   Request mode (`navigate` for a page load).
	 * @param {Object} config `origin`, `appPath` and `precache` (a list of URLs).
	 * @return {string} `app`, `static` or `network`.
	 */
	function route( url, method, mode, config ) {
		if ( method !== 'GET' || url.origin !== config.origin ) {
			return 'network';
		}
		if ( url.searchParams.has( 'rest_route' ) ) {
			return 'network';
		}
		for ( var i = 0; i < NEVER.length; i++ ) {
			if ( NEVER[ i ].test( url.pathname ) ) {
				return 'network';
			}
		}
		var inApp = url.pathname === config.appPath || url.pathname + '/' === config.appPath || url.pathname.indexOf( config.appPath ) === 0;
		if ( mode === 'navigate' ) {
			return inApp ? 'app' : 'network';
		}
		for ( var j = 0; j < config.precache.length; j++ ) {
			if ( new URL( config.precache[ j ], url.href ).pathname === url.pathname ) {
				return 'static';
			}
		}
		return 'network';
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { route: route };
	}

	var config = scope && scope.SITE_PWA_CONFIG;
	if ( ! config || typeof scope.addEventListener !== 'function' ) {
		return;
	}
	config.origin = scope.location.origin;

	scope.addEventListener( 'install', function ( event ) {
		event.waitUntil(
			scope.caches.open( config.cache ).then( function ( cache ) {
				return cache.addAll( config.precache );
			} ).then( function () {
				return scope.skipWaiting();
			} )
		);
	} );

	scope.addEventListener( 'activate', function ( event ) {
		event.waitUntil(
			scope.caches.keys().then( function ( keys ) {
				return Promise.all( keys.filter( function ( key ) {
					return key.indexOf( config.cachePrefix ) === 0 && key !== config.cache;
				} ).map( function ( key ) {
					return scope.caches.delete( key );
				} ) );
			} ).then( function () {
				return scope.clients.claim();
			} )
		);
	} );

	scope.addEventListener( 'fetch', function ( event ) {
		var request = event.request;
		var kind = route( new URL( request.url ), request.method, request.mode, config );
		if ( kind === 'app' ) {
			event.respondWith( fetch( request ).catch( function () {
				return scope.caches.match( config.offlineUrl );
			} ) );
		} else if ( kind === 'static' ) {
			event.respondWith( scope.caches.match( request, { ignoreSearch: true } ).then( function ( cached ) {
				return cached || fetch( request );
			} ) );
		}
	} );
}( typeof self !== 'undefined' ? self : undefined ) );

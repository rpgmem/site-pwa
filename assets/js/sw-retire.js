/**
 * The worker served while the app is off: it deletes the app's caches and
 * unregisters itself. Site PWA puts `self.SITE_PWA_CACHE_PREFIX` in front of
 * this file.
 */
( function ( scope ) {
	'use strict';

	scope.addEventListener( 'install', function () {
		scope.skipWaiting();
	} );

	scope.addEventListener( 'activate', function ( event ) {
		event.waitUntil(
			scope.caches.keys().then( function ( keys ) {
				return Promise.all( keys.filter( function ( key ) {
					return key.indexOf( scope.SITE_PWA_CACHE_PREFIX ) === 0;
				} ).map( function ( key ) {
					return scope.caches.delete( key );
				} ) );
			} ).then( function () {
				return scope.registration.unregister();
			} )
		);
	} );
}( self ) );

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { route } = require( '../../assets/js/sw.js' );

const config = {
	origin: 'https://example.org',
	appPath: '/app/',
	precache: [ 'https://example.org/app/offline/', 'https://example.org/wp-content/plugins/site-pwa/assets/css/app.min.css?ver=0.1.0.1' ],
};
const at = ( path ) => new URL( path, 'https://example.org' );

describe( 'route()', () => {
	it( 'answers a navigation to an app page network-first', () => {
		expect( route( at( '/app/' ), 'GET', 'navigate', config ) ).toBe( 'app' );
		expect( route( at( '/app' ), 'GET', 'navigate', config ) ).toBe( 'app' );
		expect( route( at( '/app/offline/' ), 'GET', 'navigate', config ) ).toBe( 'app' );
		// A post drawn in the app: still a navigation network-first, never cached.
		expect( route( at( '/app/?p=42' ), 'GET', 'navigate', config ) ).toBe( 'app' );
	} );

	it( 'serves a precached file from the cache, whatever its version query', () => {
		expect( route( at( '/wp-content/plugins/site-pwa/assets/css/app.min.css?ver=9' ), 'GET', 'no-cors', config ) ).toBe( 'static' );
	} );

	it.each( [
		[ 'a post', '/2026/11/hello/' ],
		[ 'the front page', '/' ],
		[ 'wp-admin', '/wp-admin/' ],
		[ 'wp-login', '/wp-login.php' ],
		[ 'the REST API', '/wp-json/wp/v2/posts' ],
		[ 'admin-ajax', '/wp-admin/admin-ajax.php' ],
		[ 'a page that starts like the app', '/apply/' ],
	] )( 'leaves a navigation to %s to the network', ( label, path ) => {
		expect( route( at( path ), 'GET', 'navigate', config ) ).toBe( 'network' );
	} );

	it( 'never answers an admin, login or API path even under the app path', () => {
		expect( route( at( '/app/wp-admin/' ), 'GET', 'navigate', config ) ).toBe( 'network' );
		expect( route( at( '/app/wp-login.php' ), 'GET', 'navigate', config ) ).toBe( 'network' );
		expect( route( at( '/app/wp-json/x' ), 'GET', 'navigate', config ) ).toBe( 'network' );
		expect( route( at( '/app/?rest_route=/wp/v2/users/me' ), 'GET', 'navigate', config ) ).toBe( 'network' );
	} );

	it( 'leaves a non-GET request and another origin to the network', () => {
		expect( route( at( '/app/' ), 'POST', 'navigate', config ) ).toBe( 'network' );
		expect( route( new URL( 'https://cdn.example.com/app/' ), 'GET', 'navigate', config ) ).toBe( 'network' );
	} );

	it( 'does not cache an app request that is not a navigation', () => {
		expect( route( at( '/app/manifest.webmanifest' ), 'GET', 'cors', config ) ).toBe( 'network' );
	} );

	it( 'follows a subdirectory install', () => {
		const sub = { ...config, appPath: '/blog/app/', precache: [] };
		expect( route( at( '/blog/app/' ), 'GET', 'navigate', sub ) ).toBe( 'app' );
		expect( route( at( '/app/' ), 'GET', 'navigate', sub ) ).toBe( 'network' );
	} );
} );

describe( 'the worker', () => {
	let listeners;
	let cacheStore;
	let scope;

	beforeEach( () => {
		vi.resetModules();
		listeners = {};
		cacheStore = { addAll: vi.fn( () => Promise.resolve() ) };
		scope = {
			SITE_PWA_CONFIG: { ...config, cachePrefix: 'site-pwa-', cache: 'site-pwa-new', offlineUrl: 'https://example.org/app/offline/' },
			location: { origin: 'https://example.org' },
			addEventListener: ( type, fn ) => {
				listeners[ type ] = fn;
			},
			skipWaiting: vi.fn( () => Promise.resolve() ),
			clients: { claim: vi.fn( () => Promise.resolve() ) },
			caches: {
				open: vi.fn( () => Promise.resolve( cacheStore ) ),
				keys: vi.fn( () => Promise.resolve( [ 'site-pwa-old', 'site-pwa-new', 'other-plugin' ] ) ),
				delete: vi.fn( () => Promise.resolve( true ) ),
				match: vi.fn( () => Promise.resolve( 'offline page' ) ),
			},
		};
		globalThis.self = scope;
		delete require.cache[ require.resolve( '../../assets/js/sw.js' ) ];
		require( '../../assets/js/sw.js' );
	} );

	it( 'precaches the offline page and the stylesheet on install', async () => {
		let done;
		listeners.install( { waitUntil: ( p ) => ( done = p ) } );
		await done;
		expect( cacheStore.addAll ).toHaveBeenCalledWith( config.precache );
		expect( scope.skipWaiting ).toHaveBeenCalled();
	} );

	it( 'deletes only its own old caches on activate', async () => {
		let done;
		listeners.activate( { waitUntil: ( p ) => ( done = p ) } );
		await done;
		expect( scope.caches.delete ).toHaveBeenCalledTimes( 1 );
		expect( scope.caches.delete ).toHaveBeenCalledWith( 'site-pwa-old' );
		expect( scope.clients.claim ).toHaveBeenCalled();
	} );

	it( 'shows the offline page when an app page cannot load, and never stores the page', async () => {
		globalThis.fetch = vi.fn( () => Promise.reject( new Error( 'offline' ) ) );
		let answer;
		listeners.fetch( {
			request: { url: 'https://example.org/app/', method: 'GET', mode: 'navigate' },
			respondWith: ( p ) => ( answer = p ),
		} );
		expect( await answer ).toBe( 'offline page' );
		expect( scope.caches.match ).toHaveBeenCalledWith( 'https://example.org/app/offline/' );
		expect( scope.caches.open ).not.toHaveBeenCalled();
	} );

	it( 'serves a precached file from the cache, or from the network when missing', async () => {
		scope.caches.match = vi.fn( () => Promise.resolve( undefined ) );
		globalThis.fetch = vi.fn( () => Promise.resolve( 'from network' ) );
		let answer;
		listeners.fetch( {
			request: { url: 'https://example.org/wp-content/plugins/site-pwa/assets/css/app.min.css?ver=2', method: 'GET', mode: 'no-cors' },
			respondWith: ( p ) => ( answer = p ),
		} );
		expect( await answer ).toBe( 'from network' );
	} );

	it( 'does not answer a request it leaves to the network', () => {
		const respondWith = vi.fn();
		listeners.fetch( { request: { url: 'https://example.org/wp-admin/', method: 'GET', mode: 'navigate' }, respondWith } );
		expect( respondWith ).not.toHaveBeenCalled();
	} );
} );

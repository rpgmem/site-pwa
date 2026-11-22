import { describe, it, expect, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { runInThisContext } from 'node:vm';

const file = resolve( process.cwd(), 'assets/js/sw-retire.js' );

describe( 'sw-retire.js', () => {
	it( 'takes over at once, deletes only the app caches and unregisters itself', async () => {
		const listeners = {};
		globalThis.self = {
			SITE_PWA_CACHE_PREFIX: 'site-pwa-',
			addEventListener: ( type, fn ) => {
				listeners[ type ] = fn;
			},
			skipWaiting: vi.fn(),
			caches: {
				keys: vi.fn( () => Promise.resolve( [ 'site-pwa-abc', 'onesignal' ] ) ),
				delete: vi.fn( () => Promise.resolve( true ) ),
			},
			registration: { unregister: vi.fn( () => Promise.resolve( true ) ) },
		};
		runInThisContext( readFileSync( file, 'utf8' ), { filename: file } );

		listeners.install();
		expect( self.skipWaiting ).toHaveBeenCalled();

		let done;
		listeners.activate( { waitUntil: ( p ) => ( done = p ) } );
		await done;
		expect( self.caches.delete ).toHaveBeenCalledTimes( 1 );
		expect( self.caches.delete ).toHaveBeenCalledWith( 'site-pwa-abc' );
		expect( self.registration.unregister ).toHaveBeenCalled();
	} );
} );

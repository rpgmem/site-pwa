import { describe, it, expect, vi, afterEach } from 'vitest';
import { readFileSync } from 'node:fs';
import { runInThisContext } from 'node:vm';
import { resolve } from 'node:path';

const file = resolve( process.cwd(), 'assets/js/register.js' );
const run = () => runInThisContext( readFileSync( file, 'utf8' ), { filename: file } );

describe( 'register.js', () => {
	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'registers the worker named on its script tag, at its scope, once the page loads', async () => {
		const tag = document.createElement( 'script' );
		tag.setAttribute( 'data-sw', '/site-pwa-sw.js' );
		tag.setAttribute( 'data-scope', '/' );
		vi.spyOn( document, 'currentScript', 'get' ).mockReturnValue( tag );
		const register = vi.fn( () => Promise.reject( new Error( 'blocked' ) ) );
		Object.defineProperty( navigator, 'serviceWorker', { value: { register }, configurable: true } );

		run();
		window.dispatchEvent( new Event( 'load' ) );

		expect( register ).toHaveBeenCalledWith( '/site-pwa-sw.js', { scope: '/' } );
		await Promise.resolve();
	} );

	it( 'does nothing in a browser without service workers', () => {
		vi.spyOn( document, 'currentScript', 'get' ).mockReturnValue( document.createElement( 'script' ) );
		delete navigator.serviceWorker;
		expect( () => run() ).not.toThrow();
	} );
} );

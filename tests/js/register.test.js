import { describe, it, expect, vi, afterEach, beforeAll } from 'vitest';
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

	describe( 'links to another site (#40)', () => {
		beforeAll( () => {
			vi.spyOn( document, 'currentScript', 'get' ).mockReturnValue( null );
			run();
			// jsdom cannot navigate; the default action is not what is under test.
			document.addEventListener( 'click', ( e ) => e.preventDefault() );
		} );

		const click = ( href, attrs = {}, init = {} ) => {
			document.body.innerHTML = '';
			const link = document.createElement( 'a' );
			link.setAttribute( 'href', href );
			Object.entries( attrs ).forEach( ( [ name, value ] ) => link.setAttribute( name, value ) );
			const span = document.createElement( 'span' );
			link.appendChild( span );
			document.body.appendChild( link );
			span.dispatchEvent( new MouseEvent( 'click', Object.assign( { bubbles: true, cancelable: true, button: 0 }, init ) ) );
			return link;
		};

		it( 'opens another site in the phone\'s own browser', () => {
			const link = click( 'https://other.test/page' );

			expect( link.getAttribute( 'target' ) ).toBe( '_blank' );
			expect( link.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
		} );

		it( 'leaves this site, downloads, mail and phone links, and modified clicks alone', () => {
			const same = window.location.origin + '/app/?p=4';
			for ( const [ href, attrs, init ] of [
				[ same, {}, {} ],
				[ '/agendar/', {}, {} ],
				[ 'https://other.test/file.pdf', { download: '' }, {} ],
				[ 'mailto:a@example.org', {}, {} ],
				[ 'tel:+5511999999999', {}, {} ],
				[ 'https://other.test/', {}, { ctrlKey: true } ],
				[ 'https://other.test/', {}, { button: 1 } ],
				[ 'http://[bad', {}, {} ],
			] ) {
				expect( click( href, attrs, init ).hasAttribute( 'target' ) ).toBe( false );
			}
		} );

		it( 'leaves a click another script already handled', () => {
			document.body.addEventListener( 'click', ( e ) => e.preventDefault(), { once: true } );
			expect( click( 'https://other.test/' ).hasAttribute( 'target' ) ).toBe( false );
		} );
	} );
} );

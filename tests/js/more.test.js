import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { init } = require( '../../assets/js/more.js' );

function memory( initial = {} ) {
	const data = new Map( Object.entries( initial ) );
	return {
		data,
		getItem: ( k ) => ( data.has( k ) ? data.get( k ) : null ),
		setItem: ( k, v ) => data.set( k, String( v ) ),
		removeItem: ( k ) => data.delete( k ),
	};
}

describe( 'more.js', () => {
	let input;
	let root;

	beforeEach( () => {
		document.body.innerHTML = '<input type="checkbox" data-site-pwa-dark>';
		input = document.querySelector( 'input' );
		root = document.createElement( 'html' );
	} );

	it( 'shows the stored choice', () => {
		init( input, memory( { 'site-pwa-dark': '1' } ), root, vi.fn() );
		expect( input.checked ).toBe( true );
		init( input, memory(), root, vi.fn() );
		expect( input.checked ).toBe( false );
	} );

	it( 'turning it on darkens the page at once and remembers it on this device', () => {
		const storage = memory();
		const reload = vi.fn();
		init( input, storage, root, reload );
		input.checked = true;
		input.dispatchEvent( new Event( 'change' ) );

		expect( storage.data.get( 'site-pwa-dark' ) ).toBe( '1' );
		expect( root.getAttribute( 'data-site-pwa-scheme' ) ).toBe( 'dark' );
		expect( root.classList.contains( 'ffc-dark-mode' ) ).toBe( true );
		expect( reload ).not.toHaveBeenCalled();
	} );

	it( 'turning it on turns the phone bars dark too', () => {
		input.setAttribute( 'data-site-pwa-dark-bar', '#10161d' );
		root.innerHTML = '<head><meta name="theme-color" content="#841d8c" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#10161d" media="(prefers-color-scheme: dark)"></head>';
		init( input, memory(), root, vi.fn() );
		input.checked = true;
		input.dispatchEvent( new Event( 'change' ) );

		const metas = Array.from( root.querySelectorAll( 'meta[name=theme-color]' ) );
		expect( metas.map( ( m ) => m.getAttribute( 'content' ) ) ).toEqual( [ '#10161d', '#10161d' ] );
		expect( metas.some( ( m ) => m.hasAttribute( 'media' ) ) ).toBe( false );
	} );

	it( 'turning it off forgets it and reloads so the site choice applies', () => {
		const storage = memory( { 'site-pwa-dark': '1' } );
		const reload = vi.fn();
		init( input, storage, root, reload );
		input.checked = false;
		input.dispatchEvent( new Event( 'change' ) );

		expect( storage.data.has( 'site-pwa-dark' ) ).toBe( false );
		expect( reload ).toHaveBeenCalledOnce();
	} );

	it( 'works when storage is missing or refuses', () => {
		const refusing = { getItem() { throw new Error( 'denied' ); }, setItem() { throw new Error( 'denied' ); }, removeItem() {} };
		init( input, refusing, root, vi.fn() );
		expect( input.checked ).toBe( false );
		input.checked = true;
		input.dispatchEvent( new Event( 'change' ) );
		expect( root.getAttribute( 'data-site-pwa-scheme' ) ).toBe( 'dark' );

		init( input, null, root, vi.fn() );
		expect( input.checked ).toBe( false );
	} );
} );

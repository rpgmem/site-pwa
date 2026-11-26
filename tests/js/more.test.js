import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { init, read } = require( '../../assets/js/more.js' );

function memory( initial = {} ) {
	const data = new Map( Object.entries( initial ) );
	return {
		data,
		getItem: ( k ) => ( data.has( k ) ? data.get( k ) : null ),
		setItem: ( k, v ) => data.set( k, String( v ) ),
		removeItem: ( k ) => data.delete( k ),
	};
}

function checked( group ) {
	const radio = group.querySelector( 'input:checked' );
	return radio ? radio.value : null;
}

function choose( group, value ) {
	const radio = group.querySelector( `input[value="${ value }"]` );
	radio.checked = true;
	radio.dispatchEvent( new Event( 'change' ) );
}

describe( 'more.js', () => {
	let group;

	beforeEach( () => {
		document.body.innerHTML = '<fieldset data-site-pwa-theme data-site-pwa-theme-default="light">'
			+ '<input type="radio" name="t" value="auto"><input type="radio" name="t" value="dark"><input type="radio" name="t" value="light">'
			+ '</fieldset>';
		group = document.querySelector( 'fieldset' );
	} );

	it( 'shows the site\'s scheme until this device chooses one', () => {
		init( group, memory(), vi.fn() );
		expect( checked( group ) ).toBe( 'light' );

		init( group, memory( { 'site-pwa-scheme': 'auto' } ), vi.fn() );
		expect( checked( group ) ).toBe( 'auto' );
	} );

	it( 'reads the old dark switch as Dark', () => {
		expect( read( memory( { 'site-pwa-dark': '1' } ) ) ).toBe( 'dark' );
		expect( read( memory( { 'site-pwa-scheme': 'light', 'site-pwa-dark': '1' } ) ) ).toBe( 'light' );
		expect( read( memory( { 'site-pwa-scheme': 'sepia' } ) ) ).toBeNull();
		expect( read( null ) ).toBeNull();
	} );

	it( 'a choice is kept on this device, the old key forgotten, and the page reloads to apply it', () => {
		const storage = memory( { 'site-pwa-dark': '1' } );
		const reload = vi.fn();
		init( group, storage, reload );
		expect( checked( group ) ).toBe( 'dark' );

		choose( group, 'auto' );

		expect( storage.data.get( 'site-pwa-scheme' ) ).toBe( 'auto' );
		expect( storage.data.has( 'site-pwa-dark' ) ).toBe( false );
		expect( reload ).toHaveBeenCalledOnce();
	} );

	it( 'does not reload when storage refuses, since nothing would survive it', () => {
		const refusing = { getItem() { throw new Error( 'denied' ); }, setItem() { throw new Error( 'denied' ); }, removeItem() {} };
		const reload = vi.fn();
		init( group, refusing, reload );
		expect( checked( group ) ).toBe( 'light' );
		choose( group, 'dark' );
		expect( reload ).not.toHaveBeenCalled();

		init( group, null, reload );
		choose( group, 'auto' );
		expect( reload ).not.toHaveBeenCalled();
	} );

	it( 'ignores a value that is not one of the three', () => {
		group.insertAdjacentHTML( 'beforeend', '<input type="radio" name="t" value="sepia">' );
		const storage = memory();
		const reload = vi.fn();
		init( group, storage, reload );
		choose( group, 'sepia' );
		expect( storage.data.size ).toBe( 0 );
		expect( reload ).not.toHaveBeenCalled();
	} );
} );

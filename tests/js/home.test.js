import { describe, it, expect, beforeEach } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { init } = require( '../../assets/js/home.js' );

function memory( initial = {} ) {
	const data = new Map( Object.entries( initial ) );
	return {
		data,
		getItem: ( k ) => ( data.has( k ) ? data.get( k ) : null ),
		setItem: ( k, v ) => data.set( k, String( v ) ),
	};
}

describe( 'home.js', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<div data-banner="ffc:attendance:42"><span>Attendance confirmed</span><button type="button" data-dismiss>x</button></div>
			<div data-banner="ffc:attendance:43"><span>Another</span><button type="button" data-dismiss>x</button></div>`;
	} );

	const banner = ( id ) => document.querySelector( `[data-banner="${ id }"]` );

	it( 'hides a banner once dismissed and remembers it on this device', () => {
		const storage = memory();
		init( document, storage );
		banner( 'ffc:attendance:42' ).querySelector( '[data-dismiss]' ).click();

		expect( banner( 'ffc:attendance:42' ).hidden ).toBe( true );
		expect( banner( 'ffc:attendance:43' ).hidden ).toBe( false );
		expect( storage.data.has( 'site-pwa-banner:ffc:attendance:42' ) ).toBe( true );
	} );

	it( 'keeps a dismissed banner hidden on the next visit', () => {
		init( document, memory( { 'site-pwa-banner:ffc:attendance:43': '1' } ) );
		expect( banner( 'ffc:attendance:43' ).hidden ).toBe( true );
		expect( banner( 'ffc:attendance:42' ).hidden ).toBe( false );
	} );

	it( 'still hides on click when storage is unavailable or refuses', () => {
		init( document, null );
		banner( 'ffc:attendance:42' ).querySelector( '[data-dismiss]' ).click();
		expect( banner( 'ffc:attendance:42' ).hidden ).toBe( true );

		document.body.innerHTML = '<div data-banner="x"><button type="button" data-dismiss>x</button></div>';
		const refusing = { getItem() { throw new Error( 'denied' ); }, setItem() { throw new Error( 'denied' ); } };
		init( document, refusing );
		document.querySelector( '[data-dismiss]' ).click();
		expect( document.querySelector( '[data-banner]' ).hidden ).toBe( true );
	} );
} );

import { describe, it, expect, beforeEach } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );

const OWN = 'https://example.org/site-pwa-sw.js';
const reg = ( url, slot = 'active' ) => ( { [ slot ]: { scriptURL: url } } );

describe( 'admin-conflicts.js', () => {
	let check;
	let box;

	beforeEach( () => {
		document.body.innerHTML = `
			<div id="site-pwa-sw-check" data-own="${ OWN }" hidden><ul class="site-pwa-sw-list"></ul></div>
			<input type="checkbox" id="site-pwa-enabled">
			<input type="checkbox" id="site-pwa-replace">`;
		box = document.getElementById( 'site-pwa-sw-check' );
		delete require.cache[ require.resolve( '../../assets/js/admin-conflicts.js' ) ];
		( { check } = require( '../../assets/js/admin-conflicts.js' ) );
	} );

	it( 'stays hidden when the only worker is the app’s own, whatever its version query', () => {
		expect( check( box, [ reg( OWN + '?v=2' ) ] ) ).toBe( 0 );
		expect( box.hidden ).toBe( true );
	} );

	it( 'lists a foreign worker as text and requires the replacement while the app is being turned on', () => {
		const found = check( box, [ reg( OWN ), reg( 'https://example.org/OneSignalSDKWorker.js?x=<b>' , 'waiting' ) ] );

		expect( found ).toBe( 1 );
		expect( box.hidden ).toBe( false );
		expect( box.querySelector( 'code' ).textContent ).toBe( 'https://example.org/OneSignalSDKWorker.js?x=<b>' );
		expect( box.querySelector( 'b' ) ).toBeNull();

		const enabled = document.getElementById( 'site-pwa-enabled' );
		const replace = document.getElementById( 'site-pwa-replace' );
		expect( replace.required ).toBe( false );
		enabled.checked = true;
		enabled.dispatchEvent( new Event( 'change' ) );
		expect( replace.required ).toBe( true );
	} );

	it( 'ignores a registration with no worker yet', () => {
		expect( check( box, [ {} ] ) ).toBe( 0 );
	} );
} );

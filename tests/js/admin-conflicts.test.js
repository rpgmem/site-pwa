import { describe, it, expect, beforeEach } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );

const OWN = 'https://example.org/site-pwa-sw.js';
const SCOPE = 'https://example.org/';
const APP = 'https://example.org/app/';
const reg = ( url, slot = 'active', scope = SCOPE ) => ( { scope, [ slot ]: { scriptURL: url } } );

describe( 'admin-conflicts.js', () => {
	let check;
	let box;

	beforeEach( () => {
		document.body.innerHTML = `
			<div id="site-pwa-sw-check" data-own="${ OWN }" data-scope="${ SCOPE }" data-app="${ APP }" hidden><ul class="site-pwa-sw-list"></ul></div>
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

	it( 'leaves out a worker scoped to another part of the site', () => {
		expect( check( box, [ reg( 'https://example.org/terminal/sw.js', 'active', 'https://example.org/terminal/' ) ] ) ).toBe( 0 );
		expect( box.hidden ).toBe( true );
	} );

	it( 'lists a worker whose narrower scope still covers the app’s pages', () => {
		expect( check( box, [ reg( 'https://example.org/app/other-sw.js', 'active', APP ) ] ) ).toBe( 1 );
	} );

	it( 'leaves out a worker above the app’s scope on a site in a subdirectory', () => {
		box.setAttribute( 'data-scope', 'https://example.org/blog/' );
		box.setAttribute( 'data-app', 'https://example.org/blog/app/' );
		expect( check( box, [ reg( 'https://example.org/sw.js' ) ] ) ).toBe( 0 );
	} );

	it( 'lists every foreign worker when the screen does not say where the app lives', () => {
		box.removeAttribute( 'data-scope' );
		expect( check( box, [ reg( 'https://example.org/terminal/sw.js', 'active', 'https://example.org/terminal/' ) ] ) ).toBe( 1 );
	} );
} );

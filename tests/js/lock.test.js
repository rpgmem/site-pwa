import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { start, boot, toB64url, fromB64url } = require( '../../assets/js/lock.js' );

function memory( initial = {} ) {
	const data = new Map( Object.entries( initial ) );
	return {
		data,
		getItem: ( k ) => ( data.has( k ) ? data.get( k ) : null ),
		setItem: ( k, v ) => data.set( k, String( v ) ),
		removeItem: ( k ) => data.delete( k ),
	};
}

const CONFIG = {
	key: 'k1',
	required: false,
	grace: 10,
	nudgeDays: 7,
	name: 'Example',
	logout: 'https://example.org/logout',
	strings: {
		unlocking: 'unlocking',
		failed: 'failed',
		turningOn: 'turning on',
		turnedOn: 'turned on',
		notTurned: 'not turned',
		noLock: 'no lock',
		turnedOff: 'turned off',
	},
};

const ON = { 'site-pwa-lock:k1': JSON.stringify( { id: 'AQID' } ) };

const MARKUP = `
	<div data-site-pwa-lock="overlay" data-mode="unlock" hidden>
		<p data-site-pwa-lock="status"></p>
		<button data-site-pwa-lock="unlock"></button>
		<button data-site-pwa-lock="setup" id="setup"></button>
		<button data-site-pwa-lock="setup" id="retry"></button>
		<a href="#" data-site-pwa-lock="signout">out</a>
	</div>
	<main>
		<a href="/file.pdf" target="_blank" id="pdf">PDF</a>
		<a href="/app/" id="inside">Inside</a>
		<input type="file" id="picker">
	</main>`;

function env( overrides = {} ) {
	let clock = 1_000_000;
	return {
		doc: document,
		local: memory(),
		session: memory(),
		now: () => clock,
		tick: ( ms ) => {
			clock += ms;
		},
		credentials: {
			get: vi.fn( () => Promise.resolve( { rawId: new Uint8Array( [ 1 ] ).buffer } ) ),
			create: vi.fn( () => Promise.resolve( { rawId: new Uint8Array( [ 1, 2, 3 ] ).buffer } ) ),
		},
		random: ( size ) => new Uint8Array( size ),
		available: () => Promise.resolve( true ),
		go: vi.fn(),
		...overrides,
	};
}

const overlay = () => document.querySelector( '[data-site-pwa-lock="overlay"]' );
const status = () => document.querySelector( '[data-site-pwa-lock="status"]' ).textContent;
const locked = () => document.documentElement.classList.contains( 'site-pwa-locked' );
const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

function setVisibility( state ) {
	Object.defineProperty( document, 'visibilityState', { value: state, configurable: true } );
	document.dispatchEvent( new Event( 'visibilitychange' ) );
}

describe( 'lock.js', () => {
	beforeEach( () => {
		document.body.innerHTML = MARKUP;
		document.documentElement.className = '';
	} );

	it( 'round-trips a credential id through base64url', () => {
		const bytes = new Uint8Array( [ 250, 251, 252, 0, 1 ] );
		expect( Array.from( fromB64url( toB64url( bytes.buffer ) ) ) ).toEqual( Array.from( bytes ) );
		expect( toB64url( bytes.buffer ) ).not.toMatch( /[+/=]/ );
	} );

	it( 'asks to unlock when the head check locked the page, and releases it on the phone\'s answer', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const e = env( { local: memory( ON ) } );
		const lock = start( CONFIG, e );
		expect( await lock.ready ).toBe( 'unlock' );
		expect( overlay().hidden ).toBe( false );

		document.querySelector( '[data-site-pwa-lock="unlock"]' ).click();
		await flush();
		const asked = e.credentials.get.mock.calls[ 0 ][ 0 ].publicKey;
		expect( asked.userVerification ).toBe( 'required' );
		expect( Array.from( asked.allowCredentials[ 0 ].id ) ).toEqual( [ 1, 2, 3 ] );
		expect( locked() ).toBe( false );
		expect( overlay().hidden ).toBe( true );
		expect( e.session.data.get( 'site-pwa-lock-seen:k1' ) ).toBe( String( e.now() ) );
	} );

	it( 'stays locked and says so when the phone refuses', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const e = env( { local: memory( ON ) } );
		e.credentials.get.mockImplementation( () => Promise.reject( new Error( 'NotAllowedError' ) ) );
		const lock = start( CONFIG, e );
		await lock.ready;
		expect( await lock.unlock() ).toBe( false );
		expect( locked() ).toBe( true );
		expect( status() ).toBe( 'failed' );
	} );

	it( 'never leaves the page hidden when there is nothing to guard', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const lock = start( CONFIG, env() );
		await lock.ready;
		expect( locked() ).toBe( false );
	} );

	it( 'asks a required account to turn the lock on, and lets it in once it is', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const e = env();
		const lock = start( { ...CONFIG, required: true }, e );
		expect( await lock.ready ).toBe( 'setup' );

		document.getElementById( 'setup' ).click();
		await flush();
		await flush();
		const made = e.credentials.create.mock.calls[ 0 ][ 0 ].publicKey;
		expect( made.authenticatorSelection.authenticatorAttachment ).toBe( 'platform' );
		expect( made.authenticatorSelection.userVerification ).toBe( 'required' );
		expect( JSON.parse( e.local.data.get( 'site-pwa-lock:k1' ) ) ).toEqual( { id: 'AQID' } );
		expect( locked() ).toBe( false );
		expect( lock.turnOff() ).toBe( false );
	} );

	it( 'warns a phone without a screen lock that it is unsafe', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const e = env( { available: () => Promise.resolve( false ) } );
		const lock = start( { ...CONFIG, required: true }, e );
		expect( await lock.ready ).toBe( 'nolock' );
		expect( overlay().getAttribute( 'data-mode' ) ).toBe( 'nolock' );
		expect( status() ).toBe( 'no lock' );
		expect( await lock.turnOn() ).toBe( 'nolock' );
		expect( e.credentials.create ).not.toHaveBeenCalled();
	} );

	it( 'reads a phone that cannot make the credential as one without a lock', async () => {
		const e = env();
		e.credentials.create.mockImplementation( () => Promise.reject( Object.assign( new Error( 'x' ), { name: 'NotSupportedError' } ) ) );
		const lock = start( CONFIG, e );
		expect( await lock.turnOn() ).toBe( 'nolock' );
		e.credentials.create.mockImplementation( () => Promise.reject( new Error( 'cancelled' ) ) );
		expect( await lock.turnOn() ).toBe( 'failed' );
		expect( status() ).toBe( 'not turned' );
	} );

	it( 'locks on return after the grace, and not before', async () => {
		const e = env( { local: memory( ON ) } );
		const lock = start( CONFIG, e );
		await lock.ready;

		setVisibility( 'hidden' );
		e.tick( 9000 );
		setVisibility( 'visible' );
		await flush();
		expect( locked() ).toBe( false );

		setVisibility( 'hidden' );
		e.tick( 11000 );
		setVisibility( 'visible' );
		await flush();
		expect( locked() ).toBe( true );
	} );

	it( 'stays locked when the lock screen is reloaded or left (#42)', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const e = env( { local: memory( ON ), session: memory( { 'site-pwa-lock-seen:k1': '999000' } ) } );
		const lock = start( CONFIG, e );
		await lock.ready;
		expect( e.session.data.has( 'site-pwa-lock-seen:k1' ) ).toBe( false, 'locking forgets the last use' );

		// Pull to refresh hides the page before it reloads.
		setVisibility( 'hidden' );
		expect( e.session.data.has( 'site-pwa-lock-seen:k1' ) ).toBe( false, 'the lock screen out of sight is not a use' );

		// The head check of the reloaded page reads that "seen" and locks again.
		setVisibility( 'visible' );
		await flush();
		expect( locked() ).toBe( true );
	} );

	it( 'locking on return also forgets the last use', async () => {
		const e = env( { local: memory( ON ) } );
		const lock = start( CONFIG, e );
		await lock.ready;
		expect( e.session.data.has( 'site-pwa-lock-seen:k1' ) ).toBe( true );

		setVisibility( 'hidden' );
		e.tick( 11000 );
		setVisibility( 'visible' );
		await flush();
		expect( locked() ).toBe( true );
		expect( e.session.data.has( 'site-pwa-lock-seen:k1' ) ).toBe( false );

		setVisibility( 'hidden' );
		expect( e.session.data.has( 'site-pwa-lock-seen:k1' ) ).toBe( false );
	} );

	it( 'does not lock on return from something the app opened', async () => {
		const e = env( { local: memory( ON ) } );
		const lock = start( CONFIG, e );
		await lock.ready;

		document.getElementById( 'pdf' ).dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
		setVisibility( 'hidden' );
		e.tick( 60000 );
		setVisibility( 'visible' );
		await flush();
		expect( locked() ).toBe( false );

		document.getElementById( 'inside' ).dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
		setVisibility( 'hidden' );
		e.tick( 60000 );
		setVisibility( 'visible' );
		await flush();
		expect( locked() ).toBe( true, 'a link inside the app is not an exit' );
	} );

	it( 'treats the file picker and the share sheet as the app\'s own exits', async () => {
		const e = env( { local: memory( ON ) } );
		const lock = start( CONFIG, e );
		document.getElementById( 'picker' ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
		expect( e.session.data.has( 'site-pwa-lock-exit:k1' ) ).toBe( true );
		e.session.data.delete( 'site-pwa-lock-exit:k1' );
		lock.allowExit();
		expect( e.session.data.has( 'site-pwa-lock-exit:k1' ) ).toBe( true );
	} );

	it( 'ignores the page going out of sight while the phone asks for its lock', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const e = env( { local: memory( ON ) } );
		let answer;
		e.credentials.get.mockImplementation( () => new Promise( ( resolve ) => {
			answer = resolve;
		} ) );
		const lock = start( CONFIG, e );
		await lock.ready;
		const pending = lock.unlock();
		await flush();
		setVisibility( 'hidden' );
		e.tick( 30000 );
		setVisibility( 'visible' );
		answer( { rawId: new Uint8Array( [ 1 ] ).buffer } );
		expect( await pending ).toBe( true );
		expect( locked() ).toBe( false );
	} );

	it( 'signing out from the lock screen forgets this phone\'s lock', async () => {
		document.documentElement.classList.add( 'site-pwa-locked' );
		const e = env( { local: memory( ON ) } );
		start( CONFIG, e );
		document.querySelector( '[data-site-pwa-lock="signout"]' ).click();
		expect( e.local.data.has( 'site-pwa-lock:k1' ) ).toBe( false );
		expect( e.go ).toHaveBeenCalledWith( 'https://example.org/logout' );
	} );

	it( 'wires More\'s switch both ways, and keeps a required lock on', async () => {
		document.body.innerHTML = MARKUP + '<input type="checkbox" data-site-pwa-lock-switch><span data-site-pwa-lock="required-note" hidden></span>';
		const e = env();
		start( CONFIG, e );
		const toggle = document.querySelector( '[data-site-pwa-lock-switch]' );
		expect( toggle.checked ).toBe( false );
		toggle.checked = true;
		toggle.dispatchEvent( new Event( 'change' ) );
		await flush();
		await flush();
		expect( toggle.checked ).toBe( true );
		expect( e.local.data.has( 'site-pwa-lock:k1' ) ).toBe( true );
		toggle.checked = false;
		toggle.dispatchEvent( new Event( 'change' ) );
		expect( e.local.data.has( 'site-pwa-lock:k1' ) ).toBe( false );
		expect( status() ).toBe( 'turned off' );

		document.body.innerHTML = MARKUP + '<input type="checkbox" data-site-pwa-lock-switch><span data-site-pwa-lock="required-note" hidden></span>';
		start( { ...CONFIG, required: true }, env( { local: memory( ON ) } ) );
		expect( document.querySelector( '[data-site-pwa-lock-switch]' ).disabled ).toBe( true );
		expect( document.querySelector( '[data-site-pwa-lock="required-note"]' ).hidden ).toBe( false );
	} );

	it( 'invites on Home until dismissed for a week, and warns a phone without a lock', async () => {
		const nudge = '<div data-site-pwa-lock-nudge data-mode="invite" hidden><button data-site-pwa-lock-on></button><button data-dismiss></button></div>';
		document.body.innerHTML = MARKUP + nudge;
		const e = env();
		start( CONFIG, e );
		await flush();
		const banner = document.querySelector( '[data-site-pwa-lock-nudge]' );
		expect( banner.hidden ).toBe( false );
		expect( banner.getAttribute( 'data-mode' ) ).toBe( 'invite' );
		banner.querySelector( '[data-dismiss]' ).click();
		expect( banner.hidden ).toBe( true );

		document.body.innerHTML = MARKUP + nudge;
		start( CONFIG, { ...e, tick: e.tick } );
		await flush();
		expect( document.querySelector( '[data-site-pwa-lock-nudge]' ).hidden ).toBe( true, 'dismissed this week' );

		document.body.innerHTML = MARKUP + nudge;
		start( CONFIG, env( { available: () => Promise.resolve( false ) } ) );
		await flush();
		expect( document.querySelector( '[data-site-pwa-lock-nudge]' ).getAttribute( 'data-mode' ) ).toBe( 'nolock' );

		document.body.innerHTML = MARKUP + nudge;
		const on = env();
		start( CONFIG, on );
		await flush();
		document.querySelector( '[data-site-pwa-lock-on]' ).click();
		await flush();
		await flush();
		expect( document.querySelector( '[data-site-pwa-lock-nudge]' ).hidden ).toBe( true );
		expect( on.local.data.has( 'site-pwa-lock:k1' ) ).toBe( true );
	} );

	it( 'boots in a browser window with its storages, clock and platform check', async () => {
		expect( boot( { document } ) ).toBeNull();

		document.documentElement.classList.add( 'site-pwa-locked' );
		const local = memory( ON );
		const assign = vi.fn();
		const win = {
			sitePwaLock: CONFIG,
			document,
			localStorage: local,
			sessionStorage: memory(),
			navigator: { credentials: { get: vi.fn( () => Promise.resolve( { rawId: new Uint8Array( [ 1 ] ).buffer } ) ) } },
			crypto: { getRandomValues: ( bytes ) => bytes },
			isSecureContext: true,
			PublicKeyCredential: { isUserVerifyingPlatformAuthenticatorAvailable: () => Promise.resolve( true ) },
			location: { assign },
		};
		const lock = boot( win );
		expect( win.SitePwaLock ).toBe( lock );
		expect( await lock.ready ).toBe( 'unlock' );
		expect( await lock.unlock() ).toBe( true );
		expect( win.navigator.credentials.get.mock.calls[ 0 ][ 0 ].publicKey.challenge ).toHaveLength( 32 );

		document.querySelector( '[data-site-pwa-lock="signout"]' ).click();
		expect( assign ).toHaveBeenCalledWith( CONFIG.logout );

		document.documentElement.classList.add( 'site-pwa-locked' );
		document.body.innerHTML = MARKUP;
		const plain = boot( { ...win, localStorage: memory(), isSecureContext: false } );
		expect( await plain.turnOn() ).toBe( 'nolock', 'no platform check outside a secure page' );
	} );
} );

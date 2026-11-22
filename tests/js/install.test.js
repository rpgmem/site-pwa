import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { isMobile, isStandalone, isIosSafari, isQuiet, card, banner } = require( '../../assets/js/install.js' );

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';
const IPHONE_CHROME = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/124.0 Mobile/15E148 Safari/604.1';
const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36';
const DAY = 24 * 60 * 60 * 1000;

/**
 * A window double: which media queries match, the navigator, and listeners.
 */
function fakeWindow( { media = [], nav = {} } = {} ) {
	const listeners = {};
	return {
		navigator: { userAgent: ANDROID, ...nav },
		matchMedia: ( query ) => ( { matches: media.includes( query ) } ),
		addEventListener: ( type, fn ) => {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
		fire: ( type, event ) => ( listeners[ type ] || [] ).forEach( ( fn ) => fn( event ) ),
	};
}

const PHONE = [ '(pointer: coarse)', '(max-width: 1024px)' ];

function memoryStorage( initial = {} ) {
	const data = { ...initial };
	return {
		getItem: ( k ) => ( k in data ? data[ k ] : null ),
		setItem: ( k, v ) => {
			data[ k ] = String( v );
		},
		data,
	};
}

describe( 'install.js decisions', () => {
	it( 'treats a coarse pointer on a narrow screen, or the mobile hint, as mobile', () => {
		expect( isMobile( fakeWindow( { media: PHONE } ) ) ).toBe( true );
		expect( isMobile( fakeWindow( { media: [ '(pointer: coarse)' ] } ) ) ).toBe( false );
		expect( isMobile( fakeWindow( { media: [ '(max-width: 1024px)' ] } ) ) ).toBe( false );
		expect( isMobile( fakeWindow( { nav: { userAgentData: { mobile: true } } } ) ) ).toBe( true );
		expect( isMobile( fakeWindow( { nav: { userAgentData: { mobile: false } } } ) ) ).toBe( false );
		expect( isMobile( { navigator: {} } ) ).toBe( false );
	} );

	it( 'knows when the app already runs installed', () => {
		expect( isStandalone( fakeWindow( { media: [ '(display-mode: standalone)' ] } ) ) ).toBe( true );
		expect( isStandalone( fakeWindow( { nav: { standalone: true } } ) ) ).toBe( true );
		expect( isStandalone( fakeWindow() ) ).toBe( false );
	} );

	it( 'recognises Safari on iPhone and iPad, and not Chrome on iOS or Android', () => {
		expect( isIosSafari( { userAgent: IPHONE } ) ).toBe( true );
		expect( isIosSafari( { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15', platform: 'MacIntel', maxTouchPoints: 5 } ) ).toBe( true );
		expect( isIosSafari( { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Version/17.4 Safari/605.1.15', platform: 'MacIntel', maxTouchPoints: 0 } ) ).toBe( false );
		expect( isIosSafari( { userAgent: IPHONE_CHROME } ) ).toBe( false );
		expect( isIosSafari( { userAgent: ANDROID } ) ).toBe( false );
	} );

	it( 'stays quiet for 30 days after a dismissal and for good after installing', () => {
		const now = 100 * DAY;
		expect( isQuiet( memoryStorage(), 'k', now ) ).toBe( false );
		expect( isQuiet( memoryStorage( { k: String( now - 29 * DAY ) } ), 'k', now ) ).toBe( true );
		expect( isQuiet( memoryStorage( { k: String( now - 31 * DAY ) } ), 'k', now ) ).toBe( false );
		expect( isQuiet( memoryStorage( { k: 'installed' } ), 'k', now ) ).toBe( true );
		expect( isQuiet( memoryStorage( { k: 'garbage' } ), 'k', now ) ).toBe( false );
		expect( isQuiet( null, 'k', now ) ).toBe( false );
		expect( isQuiet( { getItem: () => {
			throw new Error( 'blocked' );
		} }, 'k', now ) ).toBe( false );
	} );
} );

describe( 'install.js card', () => {
	let el;
	const now = () => 50 * DAY;

	beforeEach( () => {
		document.body.innerHTML = `
			<section class="site-pwa-install" data-key="site-pwa-install" hidden>
				<button data-dismiss>x</button>
				<div data-install-prompt hidden><button data-install>Install</button></div>
				<div data-install-ios hidden>steps</div>
			</section>`;
		el = document.querySelector( '.site-pwa-install' );
	} );

	it( 'shows the install button only once the browser offers to install, and opens its dialog', async () => {
		const win = fakeWindow( { media: PHONE } );
		const storage = memoryStorage();
		card( el, win, storage, now );
		expect( el.hidden ).toBe( true );

		const event = { preventDefault: vi.fn(), prompt: vi.fn(), userChoice: Promise.resolve( { outcome: 'accepted' } ) };
		win.fire( 'beforeinstallprompt', event );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect( el.hidden ).toBe( false );
		expect( el.querySelector( '[data-install-prompt]' ).hidden ).toBe( false );
		expect( el.querySelector( '[data-install-ios]' ).hidden ).toBe( true );

		el.querySelector( '[data-install]' ).click();
		expect( event.prompt ).toHaveBeenCalledTimes( 1 );
		await event.userChoice;
		await Promise.resolve();
		expect( el.hidden ).toBe( true );
		expect( storage.data[ 'site-pwa-install' ] ).toBe( 'installed' );

		el.querySelector( '[data-install]' ).click();
		expect( event.prompt ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'keeps the card after a declined install', async () => {
		const win = fakeWindow( { media: PHONE } );
		const storage = memoryStorage();
		card( el, win, storage, now );
		const event = { preventDefault: vi.fn(), prompt: vi.fn(), userChoice: Promise.resolve( { outcome: 'dismissed' } ) };
		win.fire( 'beforeinstallprompt', event );
		el.querySelector( '[data-install]' ).click();
		await event.userChoice;
		await Promise.resolve();

		expect( el.hidden ).toBe( false );
		expect( storage.data[ 'site-pwa-install' ] ).toBeUndefined();
	} );

	it( 'shows the two steps on iOS Safari, which has no install event', () => {
		card( el, fakeWindow( { media: PHONE, nav: { userAgent: IPHONE } } ), memoryStorage(), now );

		expect( el.hidden ).toBe( false );
		expect( el.querySelector( '[data-install-ios]' ).hidden ).toBe( false );
		expect( el.querySelector( '[data-install-prompt]' ).hidden ).toBe( true );
	} );

	it.each( [
		[ 'on a desktop', { media: [] } ],
		[ 'inside the installed app', { media: [ ...PHONE, '(display-mode: standalone)' ] } ],
	] )( 'never shows %s, even when the browser offers to install', ( label, opts ) => {
		const win = fakeWindow( opts );
		card( el, win, memoryStorage(), now );
		win.fire( 'beforeinstallprompt', { preventDefault: vi.fn() } );
		expect( el.hidden ).toBe( true );
	} );

	it( 'shows nothing in a phone browser that neither offers to install nor is iOS Safari', () => {
		card( el, fakeWindow( { media: PHONE, nav: { userAgent: IPHONE_CHROME } } ), memoryStorage(), now );
		expect( el.hidden ).toBe( true );
	} );

	it( 'stays hidden while dismissed, and a dismissal is remembered with its time', () => {
		const quiet = fakeWindow( { media: PHONE } );
		card( el, quiet, memoryStorage( { 'site-pwa-install': String( now() - DAY ) } ), now );
		quiet.fire( 'beforeinstallprompt', { preventDefault: vi.fn() } );
		expect( el.hidden ).toBe( true );

		const win = fakeWindow( { media: PHONE, nav: { userAgent: IPHONE } } );
		const storage = memoryStorage();
		card( el, win, storage, now );
		el.querySelector( '[data-dismiss]' ).click();
		expect( el.hidden ).toBe( true );
		expect( storage.data[ 'site-pwa-install' ] ).toBe( String( now() ) );
	} );

	it( 'hides for good when the browser reports the app installed', () => {
		const win = fakeWindow( { media: PHONE, nav: { userAgent: IPHONE } } );
		const storage = memoryStorage();
		card( el, win, storage, now );
		win.fire( 'appinstalled' );
		expect( el.hidden ).toBe( true );
		expect( storage.data[ 'site-pwa-install' ] ).toBe( 'installed' );
	} );
} );

describe( 'install.js site banner', () => {
	let el;
	const now = () => 50 * DAY;

	beforeEach( () => {
		document.body.innerHTML = '<aside class="site-pwa-site-banner" data-key="site-pwa-banner" hidden><a href="/app/">Use the app</a><button data-dismiss>x</button></aside>';
		el = document.querySelector( '.site-pwa-site-banner' );
	} );

	it( 'shows on a phone browser, without waiting for any install event', () => {
		banner( el, fakeWindow( { media: PHONE } ), memoryStorage(), now );
		expect( el.hidden ).toBe( false );
	} );

	it.each( [
		[ 'on a desktop', fakeWindow(), memoryStorage() ],
		[ 'inside the installed app', fakeWindow( { media: [ ...PHONE, '(display-mode: standalone)' ] } ), memoryStorage() ],
		[ 'while dismissed', fakeWindow( { media: PHONE } ), memoryStorage( { 'site-pwa-banner': String( 49 * DAY ) } ) ],
	] )( 'stays hidden %s', ( label, win, storage ) => {
		banner( el, win, storage, now );
		expect( el.hidden ).toBe( true );
	} );

	it( 'remembers a dismissal under its own key', () => {
		const storage = memoryStorage();
		banner( el, fakeWindow( { media: PHONE } ), storage, now );
		el.querySelector( '[data-dismiss]' ).click();
		expect( el.hidden ).toBe( true );
		expect( storage.data ).toEqual( { 'site-pwa-banner': String( now() ) } );
	} );
} );

describe( 'install.js init', () => {
	it( 'wires every card and banner in the document, and survives storage that throws', () => {
		document.body.innerHTML = `
			<section class="site-pwa-install" data-key="a" hidden><button data-dismiss></button><div data-install-prompt hidden><button data-install></button></div><div data-install-ios hidden></div></section>
			<aside class="site-pwa-site-banner" data-key="b" hidden><button data-dismiss></button></aside>`;
		const { init } = require( '../../assets/js/install.js' );
		const win = fakeWindow( { media: PHONE, nav: { userAgent: IPHONE } } );
		Object.defineProperty( win, 'localStorage', { get() {
			throw new Error( 'blocked' );
		} } );

		init( document, win );

		expect( document.querySelector( '.site-pwa-install' ).hidden ).toBe( false );
		expect( document.querySelector( '.site-pwa-site-banner' ).hidden ).toBe( false );
		document.querySelector( '.site-pwa-site-banner [data-dismiss]' ).click();
		expect( document.querySelector( '.site-pwa-site-banner' ).hidden ).toBe( true );
	} );
} );

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { init } = require( '../../assets/js/share.js' );

const PERMALINK = 'https://example.org/2026/11/reuniao/';

describe( 'share.js', () => {
	let box;

	beforeEach( () => {
		document.body.innerHTML = `
			<div class="site-pwa-share" data-url="${ PERMALINK }" data-title="Reunião" data-copied="Link copied." data-copy-failed="Copy this link:">
				<button type="button" data-share hidden>Share</button>
				<button type="button" data-copy>Copy link</button>
				<p data-status></p>
			</div>`;
		box = document.querySelector( '.site-pwa-share' );
	} );

	const flush = () => new Promise( ( done ) => setTimeout( done, 0 ) );

	it( 'keeps Share hidden where the browser cannot share', () => {
		init( box, {} );
		expect( box.querySelector( '[data-share]' ).hidden ).toBe( true );
	} );

	it( 'shares the permalink, never the app URL, and ignores a cancelled share', async () => {
		const share = vi.fn().mockRejectedValue( new Error( 'AbortError' ) );
		init( box, { share } );

		const button = box.querySelector( '[data-share]' );
		expect( button.hidden ).toBe( false );
		button.click();
		await flush();

		expect( share ).toHaveBeenCalledWith( { title: 'Reunião', url: PERMALINK } );
		expect( box.querySelector( '[data-status]' ).textContent ).toBe( '' );
	} );

	it( 'copies the permalink and says so', async () => {
		const writeText = vi.fn().mockResolvedValue( undefined );
		init( box, { clipboard: { writeText } } );

		box.querySelector( '[data-copy]' ).click();
		await flush();

		expect( writeText ).toHaveBeenCalledWith( PERMALINK );
		expect( box.querySelector( '[data-status]' ).textContent ).toBe( 'Link copied.' );
	} );

	it( 'shows the link as text when the clipboard refuses or is missing', async () => {
		init( box, { clipboard: { writeText: vi.fn().mockRejectedValue( new Error( 'denied' ) ) } } );
		box.querySelector( '[data-copy]' ).click();
		await flush();
		expect( box.querySelector( '[data-status]' ).textContent ).toBe( 'Copy this link: ' + PERMALINK );

		box.querySelector( '[data-status]' ).textContent = '';
		const other = box.cloneNode( true );
		document.body.appendChild( other );
		init( other, {} );
		other.querySelector( '[data-copy]' ).click();
		expect( other.querySelector( '[data-status]' ).textContent ).toBe( 'Copy this link: ' + PERMALINK );
	} );
} );

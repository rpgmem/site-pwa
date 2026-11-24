import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { init } = require( '../../assets/js/admin-icon.js' );

function fakeFrame( picked ) {
	const handlers = {};
	const media = {
		open: vi.fn( () => handlers.select && handlers.select() ),
		on: ( name, fn ) => {
			handlers[ name ] = fn;
		},
		state: () => ( { get: () => ( { first: () => ( { toJSON: () => picked } ) } ) } ),
	};
	return { media, frame: vi.fn( () => media ) };
}

describe( 'admin-icon.js', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<input type="radio" name="site_pwa_settings[icon_source]" value="site" checked>
			<input type="radio" name="site_pwa_settings[icon_source]" value="custom">
			<div data-site-pwa-icon>
				<input type="hidden" value="0" data-site-pwa-icon-id>
				<img alt="" hidden data-site-pwa-icon-preview>
				<button type="button" data-site-pwa-icon-choose>Choose</button>
			</div>`;
	} );

	it( 'fills the field, shows the thumbnail and selects the custom icon', () => {
		const { frame } = fakeFrame( { id: 42, url: 'https://x.test/full.png', sizes: { thumbnail: { url: 'https://x.test/thumb.png' } } } );
		init( document.querySelector( '[data-site-pwa-icon]' ), frame, { title: 'T', button: 'B' } );
		document.querySelector( '[data-site-pwa-icon-choose]' ).click();

		expect( frame ).toHaveBeenCalledWith( expect.objectContaining( { title: 'T', library: { type: 'image/png' }, multiple: false } ) );
		expect( document.querySelector( '[data-site-pwa-icon-id]' ).value ).toBe( '42' );
		const preview = document.querySelector( '[data-site-pwa-icon-preview]' );
		expect( preview.src ).toBe( 'https://x.test/thumb.png' );
		expect( preview.hidden ).toBe( false );
		expect( document.querySelector( 'input[value="custom"]' ).checked ).toBe( true );
	} );

	it( 'opens one frame however often the button is pressed, and uses the full image without a thumbnail', () => {
		const { frame } = fakeFrame( { id: 7, url: 'https://x.test/full.png' } );
		init( document.querySelector( '[data-site-pwa-icon]' ), frame, {} );
		const button = document.querySelector( '[data-site-pwa-icon-choose]' );
		button.click();
		button.click();
		expect( frame ).toHaveBeenCalledOnce();
		expect( document.querySelector( '[data-site-pwa-icon-preview]' ).src ).toBe( 'https://x.test/full.png' );
	} );

	it( 'does nothing on a picker missing its parts', () => {
		document.body.innerHTML = '<div data-site-pwa-icon></div>';
		const { frame } = fakeFrame( {} );
		init( document.querySelector( '[data-site-pwa-icon]' ), frame, {} );
		expect( frame ).not.toHaveBeenCalled();
	} );
} );

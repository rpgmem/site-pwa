/**
 * More's appearance choice (#23, #62): Automatic, Dark or Light, kept on this
 * device only, in this browser's storage. Without a stored choice the site's
 * scheme applies, and the choice shows as the site's. The head script
 * (Frame::darkScript()) applies the stored choice before the first paint, so
 * a change saves and reloads. When storage refuses, the choice cannot be kept,
 * so nothing reloads and the page stays as it is.
 */
( function () {
	'use strict';

	var KEY = 'site-pwa-scheme';
	var LEGACY_KEY = 'site-pwa-dark';
	var CHOICES = [ 'auto', 'dark', 'light' ];

	/**
	 * Wire the choice.
	 *
	 * @param {Element}      group   The fieldset holding the radios.
	 * @param {Storage|null} storage Where the choice is kept.
	 * @param {Function}     reload  Reloads the page.
	 */
	function init( group, storage, reload ) {
		var radios = Array.prototype.slice.call( group.querySelectorAll( 'input[type=radio]' ) );
		var current = read( storage ) || group.getAttribute( 'data-site-pwa-theme-default' ) || 'auto';
		radios.forEach( function ( radio ) {
			radio.checked = radio.value === current;
			radio.addEventListener( 'change', function () {
				if ( ! radio.checked || -1 === CHOICES.indexOf( radio.value ) ) {
					return;
				}
				if ( save( storage, radio.value ) ) {
					reload();
				}
			} );
		} );
	}

	/**
	 * The stored choice, the legacy dark switch read as "dark", or null.
	 *
	 * @param {Storage|null} storage Where the choice is kept.
	 * @return {string|null} A choice.
	 */
	function read( storage ) {
		try {
			if ( ! storage ) {
				return null;
			}
			var value = storage.getItem( KEY );
			if ( -1 !== CHOICES.indexOf( value ) ) {
				return value;
			}
			return '1' === storage.getItem( LEGACY_KEY ) ? 'dark' : null;
		} catch {
			return null;
		}
	}

	/**
	 * Keep the choice and forget the legacy key.
	 *
	 * @param {Storage|null} storage Where the choice is kept.
	 * @param {string}       value   The choice.
	 * @return {boolean} Whether it was kept.
	 */
	function save( storage, value ) {
		try {
			if ( ! storage ) {
				return false;
			}
			storage.setItem( KEY, value );
			storage.removeItem( LEGACY_KEY );
			return true;
		} catch {
			return false;
		}
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { init: init, read: read };
		return;
	}

	var group = document.querySelector( '[data-site-pwa-theme]' );
	if ( ! group ) {
		return;
	}
	var storage = null;
	try {
		storage = window.localStorage;
	} catch {
		storage = null;
	}
	init( group, storage, function () {
		window.location.reload();
	} );
}() );

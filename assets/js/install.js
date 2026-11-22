/**
 * The invite to install the app, on phones only: a card on the app's home
 * screen (`.site-pwa-install`) and a banner on the site's own pages
 * (`.site-pwa-site-banner`). Both start hidden and stay hidden unless every
 * condition holds:
 * - the device is a phone or tablet, never a desktop;
 * - the app is not already running installed;
 * - the visitor has not dismissed it on this device in the last 30 days, or
 *   installed the app.
 * The card then shows the browser's own install button where the browser has
 * offered one (`beforeinstallprompt`), or the two steps on iOS Safari, which
 * has no install event. Any other browser gets nothing, never a dead button.
 */
( function () {
	'use strict';

	var DAY = 24 * 60 * 60 * 1000;
	var QUIET_DAYS = 30;
	var INSTALLED = 'installed';

	/**
	 * Whether the device is a phone or tablet. The client hint answers where
	 * the browser offers it; otherwise a coarse pointer on a narrow screen.
	 *
	 * @param {Window} win The window.
	 * @return {boolean} Whether to treat it as mobile.
	 */
	function isMobile( win ) {
		var hint = win.navigator && win.navigator.userAgentData;
		if ( hint && typeof hint.mobile === 'boolean' && hint.mobile ) {
			return true;
		}
		if ( typeof win.matchMedia !== 'function' ) {
			return false;
		}
		return win.matchMedia( '(pointer: coarse)' ).matches && win.matchMedia( '(max-width: 1024px)' ).matches;
	}

	/**
	 * Whether the app is running installed.
	 *
	 * @param {Window} win The window.
	 * @return {boolean} Whether it runs as an installed app.
	 */
	function isStandalone( win ) {
		var standalone = typeof win.matchMedia === 'function' && win.matchMedia( '(display-mode: standalone)' ).matches;
		return standalone || ( win.navigator && win.navigator.standalone === true );
	}

	/**
	 * Whether this is Safari on an iPhone or iPad, where Add to Home Screen
	 * is the only way to install. An iPad reports itself as a Mac with touch.
	 *
	 * @param {Navigator} nav The navigator.
	 * @return {boolean} Whether to show the iOS steps.
	 */
	function isIosSafari( nav ) {
		var ua = nav.userAgent || '';
		var ios = /iPad|iPhone|iPod/.test( ua ) || ( nav.platform === 'MacIntel' && nav.maxTouchPoints > 1 );
		return ios && /Safari\//.test( ua ) && ! /CriOS|FxiOS|EdgiOS|OPiOS/.test( ua );
	}

	/**
	 * Read a stored value, or null when storage is unavailable.
	 *
	 * @param {Storage|null} storage Local storage.
	 * @param {string}       key     Key.
	 * @return {string|null} The value.
	 */
	function read( storage, key ) {
		try {
			return storage ? storage.getItem( key ) : null;
		} catch {
			return null;
		}
	}

	/**
	 * Store a value, ignoring a storage that refuses it.
	 *
	 * @param {Storage|null} storage Local storage.
	 * @param {string}       key     Key.
	 * @param {string}       value   Value.
	 */
	function write( storage, key, value ) {
		try {
			if ( storage ) {
				storage.setItem( key, value );
			}
		} catch {
			// Private mode or full storage: the invite simply shows again.
		}
	}

	/**
	 * Whether the invite is quiet: dismissed less than 30 days ago, or the
	 * app installed.
	 *
	 * @param {Storage|null} storage Local storage.
	 * @param {string}       key     Key.
	 * @param {number}       now     Current time, ms.
	 * @return {boolean} Whether to keep it hidden.
	 */
	function isQuiet( storage, key, now ) {
		var value = read( storage, key );
		if ( value === INSTALLED ) {
			return true;
		}
		var when = parseInt( value, 10 );
		return ! isNaN( when ) && now - when < QUIET_DAYS * DAY;
	}

	/**
	 * Wire the card on the app's home screen.
	 *
	 * @param {HTMLElement} card    The card.
	 * @param {Window}      win     The window.
	 * @param {Storage}     storage Local storage.
	 * @param {Function}    now     Returns the current time, ms.
	 */
	function card( card, win, storage, now ) {
		var key = card.getAttribute( 'data-key' );
		var prompt = card.querySelector( '[data-install-prompt]' );
		var ios = card.querySelector( '[data-install-ios]' );
		var button = card.querySelector( '[data-install]' );
		var deferred = null;

		var hide = function ( value ) {
			card.hidden = true;
			write( storage, key, value );
		};

		card.querySelector( '[data-dismiss]' ).addEventListener( 'click', function () {
			hide( String( now() ) );
		} );
		win.addEventListener( 'appinstalled', function () {
			hide( INSTALLED );
		} );

		if ( ! isMobile( win ) || isStandalone( win ) || isQuiet( storage, key, now() ) ) {
			return;
		}

		if ( isIosSafari( win.navigator ) ) {
			ios.hidden = false;
			card.hidden = false;
			return;
		}

		win.addEventListener( 'beforeinstallprompt', function ( event ) {
			event.preventDefault();
			deferred = event;
			prompt.hidden = false;
			card.hidden = false;
		} );
		button.addEventListener( 'click', function () {
			if ( ! deferred ) {
				return;
			}
			deferred.prompt();
			deferred.userChoice.then( function ( choice ) {
				if ( choice && choice.outcome === 'accepted' ) {
					hide( INSTALLED );
				}
			} );
			deferred = null;
		} );
	}

	/**
	 * Wire the banner on the site's own pages: a link to the app.
	 *
	 * @param {HTMLElement} banner  The banner.
	 * @param {Window}      win     The window.
	 * @param {Storage}     storage Local storage.
	 * @param {Function}    now     Returns the current time, ms.
	 */
	function banner( banner, win, storage, now ) {
		var key = banner.getAttribute( 'data-key' );
		banner.querySelector( '[data-dismiss]' ).addEventListener( 'click', function () {
			banner.hidden = true;
			write( storage, key, String( now() ) );
		} );
		if ( isMobile( win ) && ! isStandalone( win ) && ! isQuiet( storage, key, now() ) ) {
			banner.hidden = false;
		}
	}

	/**
	 * Wire every invite in a document.
	 *
	 * @param {Document} doc The document.
	 * @param {Window}   win The window.
	 */
	function init( doc, win ) {
		var storage = null;
		try {
			storage = win.localStorage;
		} catch {
			storage = null;
		}
		var now = function () {
			return Date.now();
		};
		Array.prototype.forEach.call( doc.querySelectorAll( '.site-pwa-install' ), function ( el ) {
			card( el, win, storage, now );
		} );
		Array.prototype.forEach.call( doc.querySelectorAll( '.site-pwa-site-banner' ), function ( el ) {
			banner( el, win, storage, now );
		} );
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { isMobile: isMobile, isStandalone: isStandalone, isIosSafari: isIosSafari, isQuiet: isQuiet, card: card, banner: banner, init: init };
		return;
	}

	// beforeinstallprompt can fire early, so wire as soon as the markup
	// exists; the script is loaded in the footer, after it.
	init( document, window );
}() );

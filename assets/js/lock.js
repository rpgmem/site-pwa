/**
 * The app lock: the phone's own screen lock in front of the app (see
 * App\Lock). A platform credential (WebAuthn) kept on this phone is the
 * only way a web app can ask the phone for its fingerprint, face or PIN;
 * the phone decides which.
 *
 * - On: the head check (Lock::headScript) marks the document locked before
 *   the first paint when the app opens, or comes back after the grace,
 *   and this script asks the phone to unlock it.
 * - Required for the account and not on yet: the lock screen asks to turn
 *   it on, and shows nothing else until it is.
 * - Off: More has a switch, and Home invites the person to turn it on,
 *   or warns that the phone has no screen lock at all.
 *
 * Leaving the app for something the app itself opened (a link in a new
 * tab, a download, a file picker, the share sheet) does not lock it.
 */
( function () {
	'use strict';

	var STATE = 'site-pwa-lock:';
	var SEEN = 'site-pwa-lock-seen:';
	var EXIT = 'site-pwa-lock-exit:';
	var NUDGE = 'site-pwa-lock-nudge:';
	var LOCKED = 'site-pwa-locked';
	var EXIT_WINDOW = 10 * 60 * 1000;

	function read( storage, key ) {
		try {
			return storage ? storage.getItem( key ) : null;
		} catch {
			return null;
		}
	}

	function write( storage, key, value ) {
		try {
			if ( storage ) {
				if ( null === value ) {
					storage.removeItem( key );
				} else {
					storage.setItem( key, value );
				}
			}
		} catch {
			// Storage refused: the lock falls back to locking every time.
		}
	}

	function toB64url( buffer ) {
		var bytes = new Uint8Array( buffer );
		var text = '';
		for ( var i = 0; i < bytes.length; i++ ) {
			text += String.fromCharCode( bytes[ i ] );
		}
		return btoa( text ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	}

	function fromB64url( text ) {
		var plain = atob( String( text ).replace( /-/g, '+' ).replace( /_/g, '/' ) );
		var bytes = new Uint8Array( plain.length );
		for ( var i = 0; i < plain.length; i++ ) {
			bytes[ i ] = plain.charCodeAt( i );
		}
		return bytes;
	}

	/**
	 * Start the lock.
	 *
	 * @param {Object} config The localized `sitePwaLock`.
	 * @param {Object} env    {doc, local, session, now, credentials, random, available, go}.
	 * @return {Object} The lock's controls, for the tests and for other scripts.
	 */
	function start( config, env ) {
		var doc = env.doc;
		var root = doc.documentElement;
		var strings = config.strings || {};
		var grace = ( config.grace || 10 ) * 1000;
		var busy = false;
		var hiddenAt = 0;

		function part( name ) {
			return doc.querySelector( '[data-site-pwa-lock="' + name + '"]' );
		}

		function credential() {
			var state = null;
			try {
				state = JSON.parse( read( env.local, STATE + config.key ) || 'null' );
			} catch {
				state = null;
			}
			return state && state.id ? state.id : '';
		}

		function enabled() {
			return '' !== credential();
		}

		function guarded() {
			return enabled() || !! config.required;
		}

		function seen() {
			write( env.session, SEEN + config.key, String( env.now() ) );
		}

		function say( text ) {
			var statuses = doc.querySelectorAll( '[data-site-pwa-lock="status"]' );
			Array.prototype.forEach.call( statuses, function ( status ) {
				status.textContent = text || '';
			} );
		}

		function available() {
			return Promise.resolve( env.available() ).then( function ( yes ) {
				return !! yes;
			}, function () {
				return false;
			} );
		}

		/**
		 * Show the lock screen: Unlock when the lock is on, Turn on when it
		 * is required and not on yet, the no-screen-lock warning when the
		 * phone has no lock to offer.
		 */
		function lock() {
			root.classList.add( LOCKED );
			var overlay = part( 'overlay' );
			if ( ! overlay ) {
				return Promise.resolve( null );
			}
			overlay.hidden = false;
			say( '' );
			if ( enabled() ) {
				overlay.setAttribute( 'data-mode', 'unlock' );
				return Promise.resolve( 'unlock' );
			}
			return available().then( function ( yes ) {
				var mode = yes ? 'setup' : 'nolock';
				overlay.setAttribute( 'data-mode', mode );
				if ( ! yes ) {
					say( strings.noLock );
				}
				return mode;
			} );
		}

		function release() {
			root.classList.remove( LOCKED );
			var overlay = part( 'overlay' );
			if ( overlay ) {
				overlay.hidden = true;
			}
			seen();
		}

		function unlock() {
			if ( busy ) {
				return Promise.resolve( false );
			}
			busy = true;
			say( strings.unlocking );
			return Promise.resolve().then( function () {
				return env.credentials.get( {
					publicKey: {
						challenge: env.random( 32 ),
						allowCredentials: [ { type: 'public-key', id: fromB64url( credential() ), transports: [ 'internal' ] } ],
						userVerification: 'required',
						timeout: 60000,
					},
				} );
			} ).then( function ( result ) {
				busy = false;
				if ( ! result ) {
					say( strings.failed );
					return false;
				}
				release();
				return true;
			}, function () {
				busy = false;
				say( strings.failed );
				return false;
			} );
		}

		/**
		 * Create this phone's credential: the phone asks for its screen lock
		 * once, and keeps the credential for this site.
		 *
		 * @return {Promise<string>} 'on', 'nolock' or 'failed'.
		 */
		function turnOn() {
			if ( busy ) {
				return Promise.resolve( 'failed' );
			}
			return available().then( function ( yes ) {
				if ( ! yes ) {
					return 'nolock';
				}
				busy = true;
				say( strings.turningOn );
				return Promise.resolve().then( function () {
					return env.credentials.create( {
						publicKey: {
							challenge: env.random( 32 ),
							rp: { name: config.name || '' },
							user: { id: env.random( 16 ), name: config.name || 'app', displayName: config.name || 'app' },
							pubKeyCredParams: [ { type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 } ],
							authenticatorSelection: {
								authenticatorAttachment: 'platform',
								userVerification: 'required',
								residentKey: 'discouraged',
								requireResidentKey: false,
							},
							attestation: 'none',
							timeout: 60000,
						},
					} );
				} ).then( function ( made ) {
					busy = false;
					if ( ! made || ! made.rawId ) {
						return 'failed';
					}
					write( env.local, STATE + config.key, JSON.stringify( { id: toB64url( made.rawId ) } ) );
					return 'on';
				}, function ( error ) {
					busy = false;
					return error && 'NotSupportedError' === error.name ? 'nolock' : 'failed';
				} );
			} ).then( function ( outcome ) {
				say( 'on' === outcome ? strings.turnedOn : ( 'nolock' === outcome ? strings.noLock : strings.notTurned ) );
				return outcome;
			} );
		}

		function turnOff() {
			if ( config.required ) {
				return false;
			}
			write( env.local, STATE + config.key, null );
			say( strings.turnedOff );
			return true;
		}

		/** The app sends the person out on purpose: do not lock on the way back. */
		function allowExit() {
			write( env.session, EXIT + config.key, String( env.now() ) );
		}

		function exitAllowed() {
			var at = parseInt( read( env.session, EXIT + config.key ) || '0', 10 );
			write( env.session, EXIT + config.key, null );
			return at > 0 && env.now() - at <= EXIT_WINDOW;
		}

		function hide() {
			if ( busy ) {
				// The phone's own unlock sheet can take the page out of sight.
				return;
			}
			hiddenAt = env.now();
			seen();
		}

		function show() {
			if ( busy || ! guarded() || root.classList.contains( LOCKED ) ) {
				return Promise.resolve( false );
			}
			var away = hiddenAt ? env.now() - hiddenAt : 0;
			hiddenAt = 0;
			if ( exitAllowed() || away <= grace ) {
				seen();
				return Promise.resolve( false );
			}
			return lock().then( function () {
				return true;
			} );
		}

		/**
		 * The More switch: on asks the phone, off forgets the credential.
		 *
		 * @param {HTMLInputElement} input The switch.
		 */
		function wireSwitch( input ) {
			var note = part( 'required-note' );
			input.checked = enabled();
			if ( config.required && enabled() ) {
				input.disabled = true;
				if ( note ) {
					note.hidden = false;
				}
			}
			input.addEventListener( 'change', function () {
				if ( input.checked ) {
					turnOn().then( function ( outcome ) {
						input.checked = 'on' === outcome;
						if ( 'on' === outcome ) {
							seen();
						}
					} );
					return;
				}
				if ( ! turnOff() ) {
					input.checked = true;
				}
			} );
		}

		/**
		 * The invitation on Home: shown while the lock is off and not
		 * dismissed in the last days; on a phone without a screen lock it
		 * becomes the warning.
		 *
		 * @param {HTMLElement} nudge The banner.
		 */
		function wireNudge( nudge ) {
			if ( enabled() || config.required ) {
				return Promise.resolve( false );
			}
			var dismissed = parseInt( read( env.local, NUDGE + config.key ) || '0', 10 );
			if ( dismissed > 0 && env.now() - dismissed < ( config.nudgeDays || 7 ) * 86400000 ) {
				return Promise.resolve( false );
			}
			var close = nudge.querySelector( '[data-dismiss]' );
			if ( close ) {
				close.addEventListener( 'click', function () {
					nudge.hidden = true;
					write( env.local, NUDGE + config.key, String( env.now() ) );
				} );
			}
			var action = nudge.querySelector( '[data-site-pwa-lock-on]' );
			if ( action ) {
				action.addEventListener( 'click', function () {
					turnOn().then( function ( outcome ) {
						if ( 'on' === outcome ) {
							nudge.hidden = true;
							seen();
						}
					} );
				} );
			}
			return available().then( function ( yes ) {
				nudge.setAttribute( 'data-mode', yes ? 'invite' : 'nolock' );
				nudge.hidden = false;
				return true;
			} );
		}

		function wire() {
			var go = part( 'unlock' );
			if ( go ) {
				go.addEventListener( 'click', unlock );
			}
			var setups = doc.querySelectorAll( '[data-site-pwa-lock="setup"]' );
			Array.prototype.forEach.call( setups, function ( setup ) {
				setup.addEventListener( 'click', function () {
					turnOn().then( function ( outcome ) {
						if ( 'on' === outcome ) {
							release();
						} else if ( 'nolock' === outcome ) {
							lock();
						}
					} );
				} );
			} );
			var out = part( 'signout' );
			if ( out ) {
				out.addEventListener( 'click', function ( event ) {
					// Signing out forgets this phone's lock, so a lost
					// credential never leaves the account locked out.
					event.preventDefault();
					write( env.local, STATE + config.key, null );
					write( env.session, SEEN + config.key, null );
					env.go( config.logout );
				} );
			}
			doc.addEventListener( 'click', function ( event ) {
				var target = event.target && event.target.closest ? event.target.closest( 'a, input' ) : null;
				if ( ! target ) {
					return;
				}
				var external = 'A' === target.tagName && ( '_blank' === target.getAttribute( 'target' ) || target.hasAttribute( 'download' ) || /^blob:/.test( target.getAttribute( 'href' ) || '' ) );
				var picker = 'INPUT' === target.tagName && 'file' === target.getAttribute( 'type' );
				if ( external || picker ) {
					allowExit();
				}
			}, true );
			doc.addEventListener( 'visibilitychange', function () {
				if ( 'hidden' === doc.visibilityState ) {
					hide();
				} else {
					show();
				}
			} );
			var toggle = doc.querySelector( '[data-site-pwa-lock-switch]' );
			if ( toggle ) {
				wireSwitch( toggle );
			}
			var nudge = doc.querySelector( '[data-site-pwa-lock-nudge]' );
			if ( nudge ) {
				wireNudge( nudge );
			}
		}

		var ready = Promise.resolve( null );
		if ( root.classList.contains( LOCKED ) && guarded() ) {
			ready = lock();
		} else {
			// Nothing to guard, or unlocked recently: never leave the page hidden.
			release();
		}
		wire();

		return {
			ready: ready,
			lock: lock,
			unlock: unlock,
			turnOn: turnOn,
			turnOff: turnOff,
			allowExit: allowExit,
			hide: hide,
			show: show,
			enabled: enabled,
		};
	}

	/**
	 * Start the lock in a browser window: its storages, its clock, its
	 * credentials and whether it has a platform authenticator.
	 *
	 * @param {Window} win The window.
	 * @return {Object|null} The lock's controls, also as `win.SitePwaLock`.
	 */
	function boot( win ) {
		var config = win.sitePwaLock;
		if ( ! config ) {
			return null;
		}
		var storage = function ( name ) {
			try {
				return win[ name ];
			} catch {
				return null;
			}
		};
		var platform = win.PublicKeyCredential;
		win.SitePwaLock = start( config, {
			doc: win.document,
			local: storage( 'localStorage' ),
			session: storage( 'sessionStorage' ),
			now: function () {
				return Date.now();
			},
			credentials: win.navigator.credentials,
			random: function ( size ) {
				return win.crypto.getRandomValues( new Uint8Array( size ) );
			},
			available: function () {
				return win.isSecureContext && platform && typeof platform.isUserVerifyingPlatformAuthenticatorAvailable === 'function'
					? platform.isUserVerifyingPlatformAuthenticatorAvailable()
					: false;
			},
			go: function ( url ) {
				win.location.assign( url );
			},
		} );
		return win.SitePwaLock;
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { start: start, boot: boot, toB64url: toB64url, fromB64url: fromB64url };
		return;
	}

	boot( window );
}() );

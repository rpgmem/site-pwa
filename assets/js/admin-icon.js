/**
 * Settings → Site PWA: choose the custom app icon in the Media Library
 * (#26). The server checks the image on save; this only fills the field,
 * shows the choice and selects "A custom icon".
 */
( function () {
	'use strict';

	/**
	 * Wire one picker.
	 *
	 * @param {Element}  root    The picker.
	 * @param {Function} frame   Opens the media frame: (options) => frame.
	 * @param {Object}   strings Title and button label.
	 */
	function init( root, frame, strings ) {
		var input = root.querySelector( '[data-site-pwa-icon-id]' );
		var preview = root.querySelector( '[data-site-pwa-icon-preview]' );
		var choose = root.querySelector( '[data-site-pwa-icon-choose]' );
		if ( ! input || ! preview || ! choose ) {
			return;
		}
		var media = null;
		choose.addEventListener( 'click', function () {
			if ( ! media ) {
				media = frame( {
					title: strings.title,
					button: { text: strings.button },
					library: { type: 'image/png' },
					multiple: false,
				} );
				media.on( 'select', function () {
					var picked = media.state().get( 'selection' ).first().toJSON();
					var sizes = picked.sizes || {};
					input.value = String( picked.id );
					preview.src = ( sizes.thumbnail || picked ).url;
					preview.hidden = false;
					var custom = document.querySelector( 'input[type="radio"][value="custom"][name$="[icon_source]"]' );
					if ( custom ) {
						custom.checked = true;
					}
				} );
			}
			media.open();
		} );
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { init: init };
		return;
	}

	var root = document.querySelector( '[data-site-pwa-icon]' );
	if ( root && window.wp && window.wp.media ) {
		init( root, window.wp.media, window.sitePwaIcon || {} );
	}
}() );

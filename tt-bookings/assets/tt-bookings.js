/**
 * Booking form behaviour.
 *
 * Progressive enhancement only: without JavaScript the form still posts to
 * admin-post.php and the visitor is redirected back with the result. This file
 * adds tabs, conditional fields, inline validation and an AJAX submit.
 */
( function () {
	'use strict';

	var settings = window.TTBookings || { ajaxUrl: '', i18n: {} };
	var i18n = settings.i18n || {};

	/* ---------- Tabs ---------- */

	function initTabs( wrapper ) {
		var tabs = Array.prototype.slice.call( wrapper.querySelectorAll( '[data-ttb-tab]' ) );
		var panels = Array.prototype.slice.call( wrapper.querySelectorAll( '[data-ttb-panel]' ) );

		function activate( type ) {
			tabs.forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-ttb-tab' ) === type;
				tab.classList.toggle( 'is-active', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );

			panels.forEach( function ( panel ) {
				var on = panel.getAttribute( 'data-ttb-panel' ) === type;
				panel.classList.toggle( 'is-active', on );
				if ( on ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', '' );
				}
			} );
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				activate( tab.getAttribute( 'data-ttb-tab' ) );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				if ( event.key !== 'ArrowRight' && event.key !== 'ArrowLeft' ) {
					return;
				}
				event.preventDefault();
				var step = event.key === 'ArrowRight' ? 1 : -1;
				var next = tabs[ ( index + step + tabs.length ) % tabs.length ];
				next.focus();
				activate( next.getAttribute( 'data-ttb-tab' ) );
			} );
		} );
	}

	/* ---------- Conditional fields ---------- */

	function initConditions( form ) {
		var conditional = Array.prototype.slice.call( form.querySelectorAll( '[data-ttb-showif]' ) );

		if ( ! conditional.length ) {
			return;
		}

		function currentValue( key ) {
			var named = form.querySelectorAll( '[name="ttb[' + key + ']"]' );

			for ( var i = 0; i < named.length; i++ ) {
				var input = named[ i ];
				if ( input.type === 'radio' || input.type === 'checkbox' ) {
					if ( input.checked ) {
						return input.value;
					}
				} else {
					return input.value;
				}
			}

			return '';
		}

		function apply() {
			conditional.forEach( function ( field ) {
				var condition;

				try {
					condition = JSON.parse( field.getAttribute( 'data-ttb-showif' ) );
				} catch ( error ) {
					return;
				}

				var visible = Object.keys( condition ).every( function ( key ) {
					return String( currentValue( key ) ) === String( condition[ key ] );
				} );

				if ( visible ) {
					field.removeAttribute( 'hidden' );
				} else {
					field.setAttribute( 'hidden', '' );
					// A hidden answer must not be submitted.
					Array.prototype.forEach.call( field.querySelectorAll( 'input, select, textarea' ), function ( input ) {
						if ( input.type === 'checkbox' || input.type === 'radio' ) {
							input.checked = false;
						} else {
							input.value = '';
						}
					} );
				}
			} );
		}

		form.addEventListener( 'change', apply );
		apply();
	}

	/* ---------- Validation ---------- */

	function fieldWrapper( input ) {
		return input.closest( '.ttb-field' ) || input.closest( '.ttb-consent' );
	}

	function setError( input, message ) {
		var wrapper = fieldWrapper( input );

		if ( ! wrapper ) {
			return;
		}

		var target = wrapper.querySelector( '.ttb-field__error' );
		wrapper.classList.toggle( 'has-error', !! message );

		if ( ! target ) {
			return;
		}

		target.textContent = message || '';
		if ( message ) {
			target.removeAttribute( 'hidden' );
		} else {
			target.setAttribute( 'hidden', '' );
		}
	}

	function validateInput( input ) {
		var wrapper = fieldWrapper( input );

		// Anything the visitor cannot see is not their problem.
		if ( wrapper && wrapper.hasAttribute( 'hidden' ) ) {
			setError( input, '' );
			return true;
		}

		var value = input.value ? input.value.trim() : '';

		if ( input.type === 'radio' ) {
			var group = input.form.querySelectorAll( '[name="' + input.name + '"]' );
			var picked = Array.prototype.some.call( group, function ( radio ) {
				return radio.checked;
			} );
			setError( input, picked ? '' : i18n.required );
			return picked;
		}

		if ( input.type === 'checkbox' ) {
			setError( input, input.checked ? '' : i18n.required );
			return input.checked;
		}

		if ( ! value ) {
			setError( input, i18n.required );
			return false;
		}

		if ( input.type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( value ) ) {
			setError( input, i18n.email );
			return false;
		}

		setError( input, '' );
		return true;
	}

	function validateForm( form ) {
		var required = Array.prototype.slice.call( form.querySelectorAll( '[data-ttb-required]' ) );
		var firstBad = null;
		var seenRadioGroups = {};

		required.forEach( function ( input ) {
			// A radio group carries the attribute on every option; check it once.
			if ( input.type === 'radio' ) {
				if ( seenRadioGroups[ input.name ] ) {
					return;
				}
				seenRadioGroups[ input.name ] = true;
			}

			if ( ! validateInput( input ) && ! firstBad ) {
				firstBad = input;
			}
		} );

		return firstBad;
	}

	/* ---------- Submit ---------- */

	function showAlert( form, kind, html ) {
		var alert = form.querySelector( '[data-ttb-' + kind + ']' );

		if ( ! alert ) {
			return;
		}

		var text = alert.querySelector( '[data-ttb-' + kind + '-text]' );

		if ( text ) {
			text.innerHTML = html;
		}

		alert.removeAttribute( 'hidden' );
	}

	function hideAlert( form, kind ) {
		var alert = form.querySelector( '[data-ttb-' + kind + ']' );

		if ( alert ) {
			alert.setAttribute( 'hidden', '' );
		}
	}

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value == null ? '' : String( value );
		return div.innerHTML;
	}

	function initSubmit( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if ( ! settings.ajaxUrl ) {
				return; // Fall back to the normal POST.
			}

			event.preventDefault();
			hideAlert( form, 'error' );

			var firstBad = validateForm( form );

			if ( firstBad ) {
				showAlert( form, 'error', escapeHtml( i18n.checkFields ) );
				firstBad.focus();
				firstBad.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				return;
			}

			var button = form.querySelector( '.ttb-submit' );
			form.classList.add( 'is-sending' );

			if ( button ) {
				button.disabled = true;
			}

			fetch( settings.ajaxUrl, {
				method: 'POST',
				body: new FormData( form ),
				credentials: 'same-origin'
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( payload ) {
					if ( payload && payload.success ) {
						form.classList.add( 'ttb-form--done' );
						showAlert( form, 'success', escapeHtml( payload.data.message ) );
						form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
						form.reset();
						return;
					}

					var data = ( payload && payload.data ) || {};
					var fields = data.fields || {};

					Object.keys( fields ).forEach( function ( key ) {
						var input = form.querySelector( '[name="ttb[' + key + ']"]' )
							|| form.querySelector( '[name="ttb[' + key + '][]"]' )
							|| form.querySelector( '[name="' + key + '"]' );

						if ( input ) {
							setError( input, fields[ key ] );
						}
					} );

					showAlert( form, 'error', escapeHtml( data.message || i18n.failed ) );
				} )
				.catch( function () {
					showAlert( form, 'error', escapeHtml( i18n.failed ) );
				} )
				.finally( function () {
					form.classList.remove( 'is-sending' );

					if ( button ) {
						button.disabled = false;
					}
				} );
		} );

		// Clear an error as soon as the visitor fixes it.
		form.addEventListener( 'input', function ( event ) {
			if ( event.target.hasAttribute( 'data-ttb-required' ) ) {
				validateInput( event.target );
			}
		} );
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.ttb-tabs' ), initTabs );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-ttb-form]' ), function ( form ) {
			initConditions( form );
			initSubmit( form );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

/* Antibot Email Shield — front end.
 * The email is never in the page. On click: Turnstile check (if configured) →
 * the server verifies it → the email is shown for a limited time → hidden again.
 */
( function () {
	'use strict';
	if ( typeof window.ANTIBOT_EMAIL_SHIELD === 'undefined' ) {
		return;
	}
	var cfg = window.ANTIBOT_EMAIL_SHIELD;

	function setMsg( el, text ) {
		el.querySelector( '.abes-msg' ).textContent = text || '';
	}

	function reset( el ) {
		var btn = el.querySelector( '.abes-btn' );
		var check = el.querySelector( '.abes-check' );
		var result = el.querySelector( '.abes-result' );
		if ( el._abesTimer ) {
			clearInterval( el._abesTimer );
			el._abesTimer = null;
		}
		if ( el._abesWidget !== undefined && window.turnstile ) {
			try {
				window.turnstile.remove( el._abesWidget );
			} catch ( e ) {}
			el._abesWidget = undefined;
		}
		result.textContent = '';
		result.hidden = true;
		check.textContent = '';
		check.hidden = true;
		btn.hidden = false;
		btn.disabled = false;
		el.classList.remove( 'is-revealed' );
	}

	function show( el, email ) {
		var btn = el.querySelector( '.abes-btn' );
		var check = el.querySelector( '.abes-check' );
		var result = el.querySelector( '.abes-result' );

		check.hidden = true;
		btn.hidden = true;
		setMsg( el, '' );

		// Built with DOM methods and textContent only: no HTML injection possible.
		var link = document.createElement( 'a' );
		link.href = 'mailto:' + encodeURIComponent( email ).replace( /%40/g, '@' );
		link.textContent = email;
		link.className = 'abes-link';
		result.textContent = '';
		result.appendChild( link );

		var secs = parseInt( cfg.hideAfter, 10 ) || 0;
		if ( secs > 0 ) {
			var count = document.createElement( 'span' );
			count.className = 'abes-count';
			result.appendChild( count );
			var left = secs;
			var tick = function () {
				count.textContent = String( cfg.i18n.hidesIn ).replace( '%d', left );
			};
			tick();
			el._abesTimer = setInterval( function () {
				left -= 1;
				if ( left <= 0 ) {
					reset( el );
					return;
				}
				tick();
			}, 1000 );
		}
		result.hidden = false;
		el.classList.add( 'is-revealed' );
	}

	function request( el, token ) {
		return fetch( cfg.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'omit',
			cache: 'no-store',
			body: JSON.stringify( { id: el.getAttribute( 'data-id' ), token: token || '' } )
		} ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( res.ok && data && typeof data.email === 'string' ) {
					show( el, data.email );
					return;
				}
				reset( el );
				setMsg( el, res.status === 429 ? cfg.i18n.tooMany : cfg.i18n.failed );
			} );
		} ).catch( function () {
			reset( el );
			setMsg( el, cfg.i18n.failed );
		} );
	}

	var tsWaiting = null;
	function loadTurnstile( done ) {
		if ( window.turnstile ) {
			done( true );
			return;
		}
		if ( tsWaiting ) {
			tsWaiting.push( done );
			return;
		}
		tsWaiting = [ done ];
		var finish = function ( ok ) {
			var list = tsWaiting;
			tsWaiting = null;
			list.forEach( function ( fn ) {
				fn( ok );
			} );
		};
		var s = document.createElement( 'script' );
		s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
		s.async = true;
		s.onload = function () {
			finish( true );
		};
		s.onerror = function () {
			finish( false );
		};
		document.head.appendChild( s );
	}

	function start( el ) {
		var btn = el.querySelector( '.abes-btn' );
		btn.disabled = true;
		setMsg( el, '' );

		if ( ! cfg.siteKey ) {
			request( el );
			return;
		}
		if ( ! window.turnstile ) {
			// Turnstile wasn't loaded with the page (for example, a cached Elementor widget): load it now.
			setMsg( el, cfg.i18n.verifying );
			loadTurnstile( function ( ok ) {
				if ( ok && window.turnstile ) {
					start( el );
				} else {
					reset( el );
					setMsg( el, cfg.i18n.failed );
				}
			} );
			return;
		}

		var check = el.querySelector( '.abes-check' );
		btn.hidden = true;
		check.hidden = false;
		setMsg( el, cfg.i18n.verifying );
		el._abesWidget = window.turnstile.render( check, {
			sitekey: cfg.siteKey,
			action: cfg.action,
			callback: function ( token ) {
				request( el, token );
			},
			'error-callback': function () {
				reset( el );
				setMsg( el, cfg.i18n.failed );
			},
			'expired-callback': function () {
				reset( el );
			}
		} );
	}

	function init() {
		document.querySelectorAll( '.abes' ).forEach( function ( el ) {
			var btn = el.querySelector( '.abes-btn' );
			if ( btn ) {
				btn.addEventListener( 'click', function () {
					start( el );
				} );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );

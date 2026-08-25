/**
 * Onboarding wizard.
 *
 * Two things, both optional. The fields are the kit's, and so is everything
 * that used to be here about them — the searchable selects, the conditional
 * visibility, and the four hundred lines that went with them.
 *
 * @package ArrayPress\RegisterOnboarding
 */

( function () {
	'use strict';

	var form = document.querySelector( '.onboarding__form' );

	if ( ! form ) {
		return;
	}

	var config = form.dataset;

	/* ---------------------------------------------------------------------
	 * Errors
	 *
	 * A submission that came back with something to say puts the focus on
	 * it. role="alert" announces it; this is so it is also on screen and so
	 * the next Tab starts from the message rather than the top of the page.
	 * ------------------------------------------------------------------ */

	var errors = form.querySelector( '.onboarding__errors' );

	if ( errors ) {
		errors.focus();
	}

	/* ---------------------------------------------------------------------
	 * Sync step
	 *
	 * Continue waits until the import has run, unless the step is skippable.
	 * The events are wp-inline-sync's.
	 * ------------------------------------------------------------------ */

	if ( '1' === config.sync ) {
		var next = form.querySelector( '.onboarding__next' );
		var trigger = form.querySelector( '.inline-sync-trigger' );

		if ( next ) {
			next.disabled = true;
		}

		document.addEventListener( 'inline-sync:complete', function () {
			if ( next ) {
				next.disabled = false;
			}

			if ( trigger ) {
				trigger.hidden = true;
			}
		} );

		// A run that failed still lets you past: a wizard that cannot be
		// finished because an import failed is a plugin that cannot be set
		// up at all.
		document.addEventListener( 'inline-sync:error', function () {
			if ( next ) {
				next.disabled = false;
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * Celebration
	 *
	 * Only on a step that asked for it, and only for someone who has not
	 * asked the browser for less movement.
	 * ------------------------------------------------------------------ */

	if ( '1' !== config.celebrate || window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}

	var canvas = document.createElement( 'canvas' );

	canvas.className = 'onboarding__confetti';
	canvas.setAttribute( 'aria-hidden', 'true' );
	document.body.appendChild( canvas );

	var context = canvas.getContext( '2d' );
	var pieces = [];
	var colours = [ '#2271b1', '#00a32a', '#dba617', '#d63638', '#8c8f94' ];
	var started = null;

	function size() {
		canvas.width = window.innerWidth;
		canvas.height = window.innerHeight;
	}

	size();
	window.addEventListener( 'resize', size );

	for ( var i = 0; i < 90; i++ ) {
		pieces.push( {
			x: Math.random() * canvas.width,
			y: -20 - Math.random() * canvas.height * 0.5,
			w: 6 + Math.random() * 6,
			h: 8 + Math.random() * 8,
			spin: Math.random() * Math.PI,
			drift: -1 + Math.random() * 2,
			fall: 2 + Math.random() * 3,
			colour: colours[ Math.floor( Math.random() * colours.length ) ]
		} );
	}

	function frame( now ) {
		if ( null === started ) {
			started = now;
		}

		var elapsed = now - started;

		context.clearRect( 0, 0, canvas.width, canvas.height );

		pieces.forEach( function ( piece ) {
			piece.y += piece.fall;
			piece.x += piece.drift;
			piece.spin += 0.05;

			context.save();
			context.translate( piece.x, piece.y );
			context.rotate( piece.spin );
			context.fillStyle = piece.colour;
			context.globalAlpha = Math.max( 0, 1 - elapsed / 4000 );
			context.fillRect( -piece.w / 2, -piece.h / 2, piece.w, piece.h );
			context.restore();
		} );

		if ( elapsed < 4000 ) {
			window.requestAnimationFrame( frame );
		} else {
			canvas.remove();
		}
	}

	window.requestAnimationFrame( frame );
}() );

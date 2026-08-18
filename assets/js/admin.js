/**
 * WP Custom SEO — admin shell behaviour.
 *
 * Four small things: the sidebar collapse, the theme switch, the command
 * palette and the toast queue. No framework and no build step — the whole file
 * is under 300 lines and shipping a runtime to manage a class toggle would cost
 * more than everything it manages.
 *
 * Preferences are held in localStorage rather than user meta. They are a
 * per-device display choice, not site data: a round trip to save "the sidebar
 * is narrow" would be a request nobody asked for, and the value is worthless on
 * another machine anyway.
 */
( function () {
	'use strict';

	var config = window.wpcseoShell || {};
	var strings = config.i18n || {};
	var app = document.querySelector( '[data-wpcseo-app]' );

	if ( ! app ) {
		return;
	}

	var STORE_NAV = 'wpcseo:nav-collapsed';
	var STORE_THEME = 'wpcseo:theme';

	/**
	 * localStorage, but never fatal.
	 *
	 * Private browsing and locked-down profiles both make it throw on read as
	 * well as write, and a display preference is not worth taking the whole
	 * screen down for.
	 */
	function store( key, value ) {
		try {
			if ( value === undefined ) {
				return window.localStorage.getItem( key );
			}

			window.localStorage.setItem( key, value );
		} catch ( e ) {
			return null;
		}

		return value;
	}

	/* --- Sidebar ---------------------------------------------------------- */

	var navToggle = app.querySelector( '[data-wpcseo-toggle-nav]' );

	function setCollapsed( collapsed ) {
		app.classList.toggle( 'is-collapsed', collapsed );

		if ( navToggle ) {
			navToggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		}

		store( STORE_NAV, collapsed ? '1' : '0' );
	}

	if ( navToggle ) {
		setCollapsed( store( STORE_NAV ) === '1' );

		navToggle.addEventListener( 'click', function () {
			setCollapsed( ! app.classList.contains( 'is-collapsed' ) );
		} );
	}

	/* --- Theme ------------------------------------------------------------- */

	var themeButton = app.querySelector( '[data-wpcseo-theme]' );
	var themeOrder = [ 'system', 'light', 'dark' ];

	function applyTheme( theme ) {
		if ( themeOrder.indexOf( theme ) === -1 ) {
			theme = 'system';
		}

		app.setAttribute( 'data-wpcseo-theme', theme );
		store( STORE_THEME, theme );

		if ( ! themeButton ) {
			return;
		}

		// Which glyph to show is about what the eye currently sees, not what the
		// setting is called: on "system" that means asking the OS.
		var dark = theme === 'dark' || ( theme === 'system' &&
			window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches );

		var light = themeButton.querySelector( '[data-wpcseo-theme-icon="light"]' );
		var moon = themeButton.querySelector( '[data-wpcseo-theme-icon="dark"]' );

		if ( light && moon ) {
			light.hidden = dark;
			moon.hidden = ! dark;
		}

		themeButton.setAttribute(
			'title',
			( strings.theme || 'Theme' ) + ': ' + ( strings[ 'theme_' + theme ] || theme )
		);
	}

	applyTheme( store( STORE_THEME ) || 'system' );

	if ( themeButton ) {
		themeButton.addEventListener( 'click', function () {
			var next = themeOrder[ ( themeOrder.indexOf( app.getAttribute( 'data-wpcseo-theme' ) ) + 1 ) % themeOrder.length ];

			applyTheme( next );
			toast( ( strings.theme || 'Theme' ) + ': ' + ( strings[ 'theme_' + next ] || next ) );
		} );
	}

	/* --- Toasts ------------------------------------------------------------ */

	var toastHost = app.querySelector( '[data-wpcseo-toasts]' );

	function toast( message, tone ) {
		if ( ! toastHost || ! message ) {
			return;
		}

		var el = document.createElement( 'div' );

		el.className = 'wpcseo-toast is-' + ( tone || 'good' );
		el.setAttribute( 'role', 'status' );
		el.textContent = message;

		toastHost.appendChild( el );

		window.setTimeout( function () {
			el.remove();
		}, 4000 );
	}

	// Exposed so other plugin scripts can report without importing anything.
	window.wpcseoToast = toast;

	/* --- Command palette ---------------------------------------------------- */

	var palette = app.querySelector( '[data-wpcseo-palette]' );
	var input = app.querySelector( '[data-wpcseo-search-input]' );
	var results = app.querySelector( '[data-wpcseo-search-results]' );
	var emptyNote = app.querySelector( '[data-wpcseo-search-empty]' );
	var index = Array.isArray( config.index ) ? config.index : [];
	var active = -1;
	var lastFocus = null;

	// Mac reads Ctrl as a right-click modifier, so the hint has to match the key
	// the listener below actually accepts on that platform.
	var isMac = /Mac|iPhone|iPad/.test( window.navigator.platform || '' );
	var hint = app.querySelector( '[data-wpcseo-shortcut]' );

	if ( hint && isMac ) {
		hint.textContent = '⌘ K';
	}

	function openPalette() {
		if ( ! palette ) {
			return;
		}

		lastFocus = document.activeElement;
		palette.hidden = false;

		if ( input ) {
			input.value = '';
			input.focus();
		}

		render( index.slice( 0, 8 ) );
	}

	function closePalette() {
		if ( ! palette || palette.hidden ) {
			return;
		}

		palette.hidden = true;
		active = -1;

		if ( lastFocus && typeof lastFocus.focus === 'function' ) {
			lastFocus.focus();
		}
	}

	function score( entry, query ) {
		var label = entry.label.toLowerCase();
		var at = label.indexOf( query );

		if ( at === 0 ) {
			return 0;
		}

		if ( at > 0 ) {
			return 1;
		}

		return entry.group.toLowerCase().indexOf( query ) > -1 ? 2 : -1;
	}

	function search( query ) {
		query = query.trim().toLowerCase();

		if ( ! query ) {
			return index.slice( 0, 8 );
		}

		return index
			.map( function ( entry ) {
				return { entry: entry, rank: score( entry, query ) };
			} )
			.filter( function ( row ) {
				return row.rank > -1;
			} )
			.sort( function ( a, b ) {
				return a.rank - b.rank;
			} )
			.slice( 0, 10 )
			.map( function ( row ) {
				return row.entry;
			} );
	}

	function render( rows ) {
		if ( ! results ) {
			return;
		}

		results.innerHTML = '';
		active = rows.length ? 0 : -1;

		if ( emptyNote ) {
			emptyNote.hidden = rows.length > 0;
		}

		rows.forEach( function ( entry, i ) {
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );
			var group = document.createElement( 'span' );

			a.className = 'wpcseo-palette__item' + ( i === 0 ? ' is-active' : '' );
			a.href = entry.url;
			a.setAttribute( 'role', 'option' );
			a.setAttribute( 'aria-selected', i === 0 ? 'true' : 'false' );
			a.appendChild( document.createTextNode( entry.label ) );

			group.className = 'wpcseo-palette__group';
			group.textContent = entry.group;
			a.appendChild( group );

			li.appendChild( a );
			results.appendChild( li );
		} );
	}

	function move( delta ) {
		if ( ! results ) {
			return;
		}

		var items = results.querySelectorAll( '.wpcseo-palette__item' );

		if ( ! items.length ) {
			return;
		}

		active = ( active + delta + items.length ) % items.length;

		items.forEach( function ( item, i ) {
			item.classList.toggle( 'is-active', i === active );
			item.setAttribute( 'aria-selected', i === active ? 'true' : 'false' );
		} );

		items[ active ].scrollIntoView( { block: 'nearest' } );
	}

	app.querySelectorAll( '[data-wpcseo-open-search]' ).forEach( function ( trigger ) {
		trigger.addEventListener( 'click', openPalette );
	} );

	app.querySelectorAll( '[data-wpcseo-close-search]' ).forEach( function ( trigger ) {
		trigger.addEventListener( 'click', closePalette );
	} );

	if ( input ) {
		input.addEventListener( 'input', function () {
			render( search( input.value ) );
		} );
	}

	document.addEventListener( 'keydown', function ( event ) {
		var open = palette && ! palette.hidden;

		if ( ( event.metaKey || event.ctrlKey ) && event.key.toLowerCase() === 'k' ) {
			event.preventDefault();
			open ? closePalette() : openPalette();

			return;
		}

		if ( ! open ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closePalette();

			return;
		}

		if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
			event.preventDefault();
			move( event.key === 'ArrowDown' ? 1 : -1 );

			return;
		}

		if ( event.key === 'Enter' && results ) {
			var current = results.querySelector( '.wpcseo-palette__item.is-active' );

			if ( current ) {
				event.preventDefault();
				window.location.href = current.href;
			}

			return;
		}

		// A modal owns the tab order while it is open, or focus wanders behind
		// the backdrop onto controls the user cannot see.
		if ( event.key === 'Tab' && palette ) {
			var focusable = palette.querySelectorAll( 'input, a[href], button' );

			if ( ! focusable.length ) {
				return;
			}

			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}
	} );

	/* --- Saved-settings confirmation ---------------------------------------- */

	// WordPress redirects back with settings-updated=true and prints its own
	// notice. The toast is a second, quieter confirmation near where the eye
	// already is, since the notice sits above the fold on a long settings tab.
	if ( window.location.search.indexOf( 'settings-updated=true' ) > -1 ) {
		toast( strings.saved || 'Settings saved', 'good' );
	}
}() );

/**
 * Editor SEO panel: tabs, character counters and live analysis.
 *
 * Deliberately framework-free. The panel is a classic meta box, so it must run
 * in the block editor, the classic editor and page builders alike.
 */
( function ( wp ) {
	'use strict';

	var config = window.wpcseoEditor || {};
	var __ = wp && wp.i18n ? wp.i18n.__ : function ( text ) { return text; };
	var sprintf = wp && wp.i18n ? wp.i18n.sprintf : function ( text ) { return text; };

	var panel = document.getElementById( 'wpcseo-panel' );

	if ( ! panel ) {
		return;
	}

	function $( selector ) {
		return panel.querySelector( selector );
	}

	/* Tabs. */
	var tabs = Array.prototype.slice.call( panel.querySelectorAll( '.wpcseo-tab' ) );

	function selectTab( tab ) {
		tabs.forEach( function ( item ) {
			var selected = item === tab;
			var target = document.getElementById( item.getAttribute( 'aria-controls' ) );

			item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			item.setAttribute( 'tabindex', selected ? '0' : '-1' );
			item.classList.toggle( 'is-active', selected );

			if ( target ) {
				target.hidden = ! selected;
			}
		} );
	}

	tabs.forEach( function ( tab, index ) {
		tab.addEventListener( 'click', function () {
			selectTab( tab );
		} );

		tab.addEventListener( 'keydown', function ( event ) {
			var offset = 0;

			if ( 'ArrowRight' === event.key ) {
				offset = 1;
			} else if ( 'ArrowLeft' === event.key ) {
				offset = -1;
			} else {
				return;
			}

			event.preventDefault();
			var next = tabs[ ( index + offset + tabs.length ) % tabs.length ];
			selectTab( next );
			next.focus();
		} );
	} );

	/* Character counters. */
	var counters = Array.prototype.slice.call( panel.querySelectorAll( '.wpcseo-counter' ) );

	function updateCounters() {
		counters.forEach( function ( counter ) {
			var field = document.getElementById( counter.dataset.counterFor );

			if ( ! field ) {
				return;
			}

			var length = field.value.length;
			var min = parseInt( counter.dataset.min, 10 );
			var max = parseInt( counter.dataset.max, 10 );
			var state = 'warn';

			if ( length >= min && length <= max ) {
				state = 'good';
			} else if ( length > max ) {
				state = 'bad';
			}

			counter.textContent = sprintf(
				/* translators: 1: current characters, 2: recommended minimum, 3: recommended maximum. */
				__( '%1$d characters (aim for %2$d–%3$d)', 'wp-custom-seo' ),
				length,
				min,
				max
			);
			counter.className = 'wpcseo-counter is-' + state;
		} );
	}

	/* Editor content, whichever editor is running. */
	function editorContent() {
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			var edited = wp.data.select( 'core/editor' ).getEditedPostContent();

			if ( 'string' === typeof edited ) {
				return edited;
			}
		}

		if ( window.tinymce ) {
			var tiny = window.tinymce.get( 'content' );

			if ( tiny && ! tiny.isHidden() ) {
				return tiny.getContent();
			}
		}

		var textarea = document.getElementById( 'content' );

		return textarea ? textarea.value : null;
	}

	function editorTitle() {
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			var edited = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );

			if ( 'string' === typeof edited ) {
				return edited;
			}
		}

		var field = document.getElementById( 'title' );

		return field ? field.value : null;
	}

	/* Preview card. */
	function renderPreview( preview ) {
		var url = $( '[data-wpcseo="preview-url"]' );
		var title = $( '[data-wpcseo="preview-title"]' );
		var description = $( '[data-wpcseo="preview-description"]' );

		if ( url ) {
			url.textContent = preview.url || '';
		}

		if ( title ) {
			title.textContent = preview.title || __( 'Untitled', 'wp-custom-seo' );
		}

		if ( description ) {
			description.textContent =
				preview.description || __( 'No description yet. Search engines will assemble their own.', 'wp-custom-seo' );
		}
	}

	/* Analysis. */
	var analysis = $( '[data-wpcseo="analysis"]' );
	var statusEl = $( '[data-wpcseo="status"]' );
	var pending = null;

	function setStatus( message ) {
		if ( statusEl ) {
			statusEl.textContent = message;
		}
	}

	function renderAnalysis( result ) {
		var scoreEl = $( '[data-wpcseo="score"]' );
		var list = $( '[data-wpcseo="checks"]' );

		if ( scoreEl ) {
			scoreEl.textContent = result.score + '/100';
			scoreEl.className = 'wpcseo-score is-' + result.grade;
		}

		if ( ! list ) {
			return;
		}

		list.textContent = '';

		result.checks.forEach( function ( check ) {
			var item = document.createElement( 'li' );
			item.className = 'wpcseo-check is-' + check.status;

			var badge = document.createElement( 'span' );
			badge.className = 'wpcseo-check-badge';
			badge.textContent = check.label;

			// A check set to weight zero still gives its advice; it just does
			// not move the number. Saying so is the difference between "we
			// ignored this" and "you chose not to count it".
			if ( false === check.counts ) {
				item.className += ' is-unscored';

				var excluded = document.createElement( 'span' );
				excluded.className = 'wpcseo-check-excluded';
				excluded.textContent = __( 'not counted', 'wp-custom-seo' );
				badge.appendChild( document.createTextNode( ' ' ) );
				badge.appendChild( excluded );
			}

			var issue = document.createElement( 'p' );
			issue.className = 'wpcseo-check-issue';
			issue.textContent = check.issue;

			var details = document.createElement( 'details' );
			var summary = document.createElement( 'summary' );
			summary.textContent = __( 'Why this matters', 'wp-custom-seo' );

			var why = document.createElement( 'p' );
			why.textContent = check.why;

			var recommendation = document.createElement( 'p' );
			recommendation.className = 'wpcseo-check-recommendation';
			recommendation.textContent = check.recommendation;

			details.appendChild( summary );
			details.appendChild( why );
			details.appendChild( recommendation );

			item.appendChild( badge );
			item.appendChild( issue );
			item.appendChild( details );
			list.appendChild( item );
		} );
	}

	function requestAnalysis() {
		if ( ! config.postId || ! wp || ! wp.apiFetch ) {
			return;
		}

		var body = {
			title: $( '#wpcseo_title' ).value || editorTitle() || '',
			description: $( '#wpcseo_description' ).value,
			keyword: $( '#wpcseo_focus_keyword' ).value,
		};

		var content = editorContent();

		if ( null !== content ) {
			body.content = content;
		}

		setStatus( __( 'Analysing…', 'wp-custom-seo' ) );

		wp.apiFetch( {
			path: config.restPath + config.postId,
			method: 'POST',
			data: body,
		} )
			.then( function ( result ) {
				setStatus( '' );
				renderPreview( result.preview );

				if ( analysis ) {
					renderAnalysis( result );
				}
			} )
			.catch( function ( error ) {
				setStatus(
					error && error.message
						? error.message
						: __( 'The analysis could not be completed. Save the post and try again.', 'wp-custom-seo' )
				);
			} );
	}

	function schedule() {
		updateCounters();
		window.clearTimeout( pending );
		pending = window.setTimeout( requestAnalysis, 700 );
	}

	[ '#wpcseo_title', '#wpcseo_description', '#wpcseo_focus_keyword' ].forEach( function ( selector ) {
		var field = $( selector );

		if ( field ) {
			field.addEventListener( 'input', schedule );
		}
	} );

	updateCounters();

	if ( config.postId ) {
		requestAnalysis();
	}
} )( window.wp );

/* AI suggestions. */
( function ( wp ) {
	'use strict';

	var config = window.wpcseoEditor || {};
	var __ = wp && wp.i18n ? wp.i18n.__ : function ( text ) { return text; };
	var panel = document.getElementById( 'wpcseo-panel' );

	if ( ! panel || ! wp || ! wp.apiFetch ) {
		return;
	}

	var statusEl = panel.querySelector( '[data-wpcseo="ai-status"]' );
	var listEl = panel.querySelector( '[data-wpcseo="ai-suggestions"]' );
	var buttons = Array.prototype.slice.call( panel.querySelectorAll( '[data-wpcseo-ai]' ) );

	if ( ! buttons.length || ! listEl ) {
		return;
	}

	function setStatus( message ) {
		if ( statusEl ) {
			statusEl.textContent = message;
		}
	}

	function targetField( action ) {
		return 'title' === action
			? panel.querySelector( '#wpcseo_title' )
			: panel.querySelector( '#wpcseo_description' );
	}

	function render( action, suggestions ) {
		listEl.textContent = '';

		suggestions.forEach( function ( suggestion ) {
			var item = document.createElement( 'li' );
			item.className = 'wpcseo-suggestion';

			var text = document.createElement( 'p' );
			text.className = 'wpcseo-suggestion-text';
			text.textContent = suggestion;

			var count = document.createElement( 'span' );
			count.className = 'wpcseo-suggestion-count';
			count.textContent = suggestion.length + ' ' + __( 'characters', 'wp-custom-seo' );

			var apply = document.createElement( 'button' );
			apply.type = 'button';
			apply.className = 'button button-small';
			apply.textContent = __( 'Apply', 'wp-custom-seo' );
			apply.addEventListener( 'click', function () {
				var field = targetField( action );

				if ( field ) {
					field.value = suggestion;
					// Let the counters and live analysis react.
					field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					field.focus();
					setStatus( __( 'Applied. Save the post to keep it.', 'wp-custom-seo' ) );
				}
			} );

			item.appendChild( text );
			item.appendChild( count );
			item.appendChild( apply );
			listEl.appendChild( item );
		} );
	}

	function editorContent() {
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			var edited = wp.data.select( 'core/editor' ).getEditedPostContent();

			if ( 'string' === typeof edited ) {
				return edited;
			}
		}

		if ( window.tinymce ) {
			var tiny = window.tinymce.get( 'content' );

			if ( tiny && ! tiny.isHidden() ) {
				return tiny.getContent();
			}
		}

		var textarea = document.getElementById( 'content' );

		return textarea ? textarea.value : null;
	}

	/* Search performance, fetched only when asked for. */
	( function () {
		var load = $( '[data-wpcseo="performance-load"]' );
		var out = $( '[data-wpcseo="performance"]' );
		var status = $( '[data-wpcseo="performance-status"]' );

		if ( ! load || ! out ) {
			return;
		}

		function cell( row, value ) {
			var td = document.createElement( 'td' );
			td.textContent = value;
			row.appendChild( td );
		}

		function render( data ) {
			out.textContent = '';

			if ( ! data.available ) {
				var note = document.createElement( 'p' );
				note.textContent = data.reason;
				out.appendChild( note );
				return;
			}

			if ( ! data.rows.length ) {
				var none = document.createElement( 'p' );
				none.textContent = __( 'Google reports no queries for this page in this period. That is normal for a page that is new, or one that is not yet being shown.', 'wp-custom-seo' );
				out.appendChild( none );
				return;
			}

			var table = document.createElement( 'table' );
			table.className = 'wp-list-table widefat striped wpcseo-ai-table';

			var head = document.createElement( 'tr' );
			[
				__( 'Query', 'wp-custom-seo' ),
				__( 'Clicks', 'wp-custom-seo' ),
				__( 'Impressions', 'wp-custom-seo' ),
				__( 'Position', 'wp-custom-seo' )
			].forEach( function ( label ) {
				var th = document.createElement( 'th' );
				th.setAttribute( 'scope', 'col' );
				th.textContent = label;
				head.appendChild( th );
			} );

			var thead = document.createElement( 'thead' );
			thead.appendChild( head );
			table.appendChild( thead );

			var body = document.createElement( 'tbody' );

			data.rows.forEach( function ( row ) {
				var tr = document.createElement( 'tr' );
				cell( tr, row.key );
				cell( tr, String( row.clicks ) );
				cell( tr, String( row.impressions ) );
				cell( tr, row.position.toFixed( 1 ) );
				body.appendChild( tr );
			} );

			table.appendChild( body );
			out.appendChild( table );

			// The comparison worth making: the phrase this page targets against
			// the phrases it is actually shown for.
			if ( data.keyword ) {
				var verdict = document.createElement( 'p' );
				verdict.className = 'description';
				if ( data.matched ) {
					/* translators: %s: the focus keyphrase set on this page. */
					verdict.textContent = sprintf( __( 'Your focus keyphrase “%s” appears among these queries.', 'wp-custom-seo' ), data.keyword );
				} else {
					/* translators: %s: the focus keyphrase set on this page. */
					verdict.textContent = sprintf( __( 'Your focus keyphrase “%s” is not among the queries listed. These are the top rows only, so this is an observation rather than proof it never appears.', 'wp-custom-seo' ), data.keyword );
				}
				out.appendChild( verdict );
			}

			var range = document.createElement( 'p' );
			range.className = 'description';
			range.textContent = sprintf(
				/* translators: 1: start date, 2: end date. */
				__( '%1$s to %2$s, as reported by Google. The range stops short of today because Search Console lags by a few days.', 'wp-custom-seo' ),
				data.range.start,
				data.range.end
			);
			out.appendChild( range );
		}

		load.addEventListener( 'click', function () {
			load.disabled = true;
			status.textContent = __( 'Asking Google…', 'wp-custom-seo' );

			wp.apiFetch( { path: '/wp-custom-seo/v1/performance/' + config.postId } )
				.then( function ( data ) {
					status.textContent = '';
					render( data );
				} )
				.catch( function ( error ) {
					status.textContent = error && error.message ? error.message : __( 'The request failed.', 'wp-custom-seo' );
				} )
				.finally( function () {
					load.disabled = false;
				} );
		} );
	} )();

	buttons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var action = button.dataset.wpcseoAi;
			var titleField = document.getElementById( 'title' );

			buttons.forEach( function ( other ) { other.disabled = true; } );
			listEl.textContent = '';
			setStatus( __( 'Asking the model…', 'wp-custom-seo' ) );

			var body = {
				post_id: config.postId,
				title: titleField ? titleField.value : '',
				keyword: panel.querySelector( '#wpcseo_focus_keyword' ).value,
				description: panel.querySelector( '#wpcseo_description' ).value,
			};

			var content = editorContent();

			if ( null !== content ) {
				body.content = content;
			}

			wp.apiFetch( { path: '/wp-custom-seo/v1/ai/' + action, method: 'POST', data: body } )
				.then( function ( result ) {
					setStatus( '' );

					// Structured actions render their own report instead of a
					// list of applicable suggestions.
					if ( window.wpcseoRenderReport && window.wpcseoRenderReport( action, result ) ) {
						listEl.textContent = '';
						return;
					}

					if ( ! result.suggestions || ! result.suggestions.length ) {
						setStatus( __( 'The model returned nothing usable. Try again.', 'wp-custom-seo' ) );
						return;
					}

					render( action, result.suggestions );
				} )
				.catch( function ( error ) {
					setStatus( error && error.message ? error.message : __( 'The request failed.', 'wp-custom-seo' ) );
				} )
				.finally( function () {
					buttons.forEach( function ( other ) { other.disabled = false; } );
				} );
		} );
	} );
} )( window.wp );

/**
 * Structured AI reports in the editor panel: keywords and content review.
 *
 * These two actions return typed data rather than a list of applicable
 * suggestions, so they render their own report. Every node is built with
 * textContent — model output is never inserted as HTML.
 */
( function ( wp ) {
	'use strict';

	var __ = wp && wp.i18n ? wp.i18n.__ : function ( text ) { return text; };
	var panel = document.getElementById( 'wpcseo-panel' );
	var report = panel ? panel.querySelector( '[data-wpcseo="ai-report"]' ) : null;

	if ( ! report ) {
		return;
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( undefined !== text && null !== text ) {
			node.textContent = text;
		}

		return node;
	}

	function section( title ) {
		var wrap = el( 'section', 'wpcseo-ai-section' );
		wrap.appendChild( el( 'h4', null, title ) );
		return wrap;
	}

	function stringList( items ) {
		var list = el( 'ul', 'wpcseo-ai-list' );

		items.forEach( function ( item ) {
			list.appendChild( el( 'li', null, item ) );
		} );

		return list;
	}

	function keywordTable( rows ) {
		var table = el( 'table', 'wp-list-table widefat striped wpcseo-ai-table' );
		var head = el( 'thead' );
		var headRow = el( 'tr' );

		[
			__( 'Keyword', 'wp-custom-seo' ),
			__( 'Intent', 'wp-custom-seo' ),
			__( 'How to use it', 'wp-custom-seo' ),
			__( 'Where', 'wp-custom-seo' )
		].forEach( function ( label ) {
			var th = el( 'th', null, label );
			th.setAttribute( 'scope', 'col' );
			headRow.appendChild( th );
		} );

		head.appendChild( headRow );
		table.appendChild( head );

		var body = el( 'tbody' );

		rows.forEach( function ( row ) {
			var tr = el( 'tr' );
			tr.appendChild( el( 'td', null, row.keyword ) );
			tr.appendChild( el( 'td', null, row.intent ) );
			tr.appendChild( el( 'td', null, row.usage ) );
			tr.appendChild( el( 'td', null, row.location ) );
			body.appendChild( tr );
		} );

		table.appendChild( body );

		return table;
	}

	function explainedList( rows ) {
		var list = el( 'ul', 'wpcseo-checks' );

		rows.forEach( function ( row ) {
			var item = el( 'li', 'wpcseo-check is-warn' );
			item.appendChild( el( 'p', 'wpcseo-check-issue', row.issue ) );

			var details = el( 'details' );
			details.appendChild( el( 'summary', null, __( 'Why this matters', 'wp-custom-seo' ) ) );
			details.appendChild( el( 'p', null, row.why ) );
			details.appendChild( el( 'p', 'wpcseo-check-recommendation', row.recommendation ) );

			item.appendChild( details );
			list.appendChild( item );
		} );

		return list;
	}

	function renderKeywords( data ) {
		report.textContent = '';

		if ( data.primary && data.primary.keyword ) {
			var primary = section( __( 'Suggested primary keyphrase', 'wp-custom-seo' ) );
			var line = el( 'p' );
			line.appendChild( el( 'strong', null, data.primary.keyword ) );

			if ( data.primary.intent ) {
				line.appendChild( document.createTextNode( ' — ' + data.primary.intent ) );
			}

			primary.appendChild( line );

			if ( data.primary.reason ) {
				primary.appendChild( el( 'p', 'description', data.primary.reason ) );
			}

			var use = el( 'button', 'button button-small', __( 'Use as focus keyphrase', 'wp-custom-seo' ) );
			use.type = 'button';
			use.addEventListener( 'click', function () {
				var field = panel.querySelector( '#wpcseo_focus_keyword' );

				if ( field ) {
					field.value = data.primary.keyword;
					field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
			} );

			primary.appendChild( use );
			report.appendChild( primary );
		}

		[
			[ 'secondary', __( 'Secondary keywords', 'wp-custom-seo' ) ],
			[ 'long_tail', __( 'Long-tail keywords', 'wp-custom-seo' ) ]
		].forEach( function ( pair ) {
			if ( data[ pair[ 0 ] ] && data[ pair[ 0 ] ].length ) {
				var wrap = section( pair[ 1 ] );
				wrap.appendChild( keywordTable( data[ pair[ 0 ] ] ) );
				report.appendChild( wrap );
			}
		} );

		[
			[ 'questions', __( 'Question keywords', 'wp-custom-seo' ) ],
			[ 'entities', __( 'Related entities', 'wp-custom-seo' ) ],
			[ 'semantic', __( 'Semantic terms', 'wp-custom-seo' ) ]
		].forEach( function ( pair ) {
			if ( data[ pair[ 0 ] ] && data[ pair[ 0 ] ].length ) {
				var wrap = section( pair[ 1 ] );
				wrap.appendChild( stringList( data[ pair[ 0 ] ] ) );
				report.appendChild( wrap );
			}
		} );

		report.appendChild(
			el(
				'p',
				'description',
				__( 'No search volume or difficulty is shown: a language model does not have that data, and an invented number would be worse than none.', 'wp-custom-seo' )
			)
		);
	}

	function renderAnalysis( data ) {
		report.textContent = '';

		if ( data.summary ) {
			report.appendChild( el( 'p', null, data.summary ) );
		}

		if ( data.intent && data.intent.type ) {
			var intent = section( __( 'Search intent', 'wp-custom-seo' ) );

			intent.appendChild(
				el( 'p', null, data.intent.type + ' — ' + data.intent.confidence + '% ' + __( 'confidence', 'wp-custom-seo' ) )
			);

			if ( data.intent.reason ) {
				intent.appendChild( el( 'p', 'description', data.intent.reason ) );
			}

			intent.appendChild(
				el(
					'p',
					'description',
					__( 'Confidence is the model’s own certainty, not a measurement of anything external.', 'wp-custom-seo' )
				)
			);

			var apply = el( 'button', 'button button-small', __( 'Set this as the search intent', 'wp-custom-seo' ) );
			apply.type = 'button';
			apply.addEventListener( 'click', function () {
				var field = panel.querySelector( '#wpcseo_search_intent' );

				if ( field ) {
					field.value = data.intent.type;
					intent.appendChild( el( 'p', 'description', __( 'Set. Save the post to keep it.', 'wp-custom-seo' ) ) );
				}
			} );

			intent.appendChild( apply );
			report.appendChild( intent );
		}

		[
			[ 'missing_topics', __( 'Missing topics', 'wp-custom-seo' ) ],
			[ 'weak_sections', __( 'Weak sections', 'wp-custom-seo' ) ],
			[ 'heading_suggestions', __( 'Heading improvements', 'wp-custom-seo' ) ]
		].forEach( function ( pair ) {
			if ( data[ pair[ 0 ] ] && data[ pair[ 0 ] ].length ) {
				var wrap = section( pair[ 1 ] );
				wrap.appendChild( explainedList( data[ pair[ 0 ] ] ) );
				report.appendChild( wrap );
			}
		} );

		[
			[ 'missing_questions', __( 'Questions not answered', 'wp-custom-seo' ) ],
			[ 'missing_entities', __( 'Entities not mentioned', 'wp-custom-seo' ) ],
			[ 'internal_link_ideas', __( 'Internal linking opportunities', 'wp-custom-seo' ) ],
			[ 'external_reference_ideas', __( 'Where a source would help', 'wp-custom-seo' ) ]
		].forEach( function ( pair ) {
			if ( data[ pair[ 0 ] ] && data[ pair[ 0 ] ].length ) {
				var wrap = section( pair[ 1 ] );
				wrap.appendChild( stringList( data[ pair[ 0 ] ] ) );
				report.appendChild( wrap );
			}
		} );

		report.appendChild(
			el(
				'p',
				'description',
				__( 'Recommendations only. Nothing here changes the page — you decide what to act on.', 'wp-custom-seo' )
			)
		);
	}

	function escapeHtml( value ) {
		return String( value ).replace( /[&<>"]/g, function ( character ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ character ];
		} );
	}

	/**
	 * A read-only field holding markup to paste, with a copy button.
	 *
	 * Nothing is written into the post: the editor decides where the text goes.
	 * Copying is the whole of "accept" here, which keeps a suggestion from ever
	 * silently changing a published page.
	 */
	function snippet( markup ) {
		var wrap = el( 'p', 'wpcseo-ai-snippet' );
		var field = document.createElement( 'textarea' );

		field.readOnly = true;
		field.rows = 2;
		field.value = markup;
		field.className = 'large-text code';

		var copy = el( 'button', 'button button-small', __( 'Copy', 'wp-custom-seo' ) );
		copy.type = 'button';
		copy.addEventListener( 'click', function () {
			field.select();

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( field.value );
			}

			copy.textContent = __( 'Copied', 'wp-custom-seo' );
		} );

		wrap.appendChild( field );
		wrap.appendChild( copy );

		return wrap;
	}

	function renderLinks( data ) {
		report.textContent = '';

		if ( ! data.links || ! data.links.length ) {
			report.appendChild(
				el( 'p', null, data.note || __( 'The model did not find a link worth suggesting on this page.', 'wp-custom-seo' ) )
			);

			return;
		}

		var wrap = section( __( 'Suggested internal links', 'wp-custom-seo' ) );

		data.links.forEach( function ( link ) {
			var card = el( 'div', 'wpcseo-ai-card' );

			var heading = el( 'p' );
			var target = el( 'a', null, link.title );
			target.href = link.url;
			target.target = '_blank';
			target.rel = 'noreferrer';
			heading.appendChild( el( 'strong', null, __( 'Link to: ', 'wp-custom-seo' ) ) );
			heading.appendChild( target );
			card.appendChild( heading );

			if ( link.reason ) {
				card.appendChild( el( 'p', 'description', link.reason ) );
			}

			var meta = [ link.confidence + '% ' + __( 'confidence', 'wp-custom-seo' ) ];

			if ( link.placement ) {
				meta.push( link.placement );
			}

			meta.push(
				link.in_content
					? __( 'this wording is already on the page', 'wp-custom-seo' )
					: __( 'this wording is not on the page yet — you will need to write it', 'wp-custom-seo' )
			);

			card.appendChild( el( 'p', 'description', meta.join( ' · ' ) ) );

			// The anchor is editable before it is copied, because the model's
			// phrasing is a starting point and the editor knows the sentence.
			var label = el( 'label', 'wpcseo-field', __( 'Anchor text', 'wp-custom-seo' ) );
			var anchor = document.createElement( 'input' );
			anchor.type = 'text';
			anchor.value = link.anchor;
			anchor.className = 'widefat';
			label.appendChild( anchor );
			card.appendChild( label );

			var markup = snippet( '<a href="' + escapeHtml( link.url ) + '">' + escapeHtml( link.anchor ) + '</a>' );
			var field = markup.querySelector( 'textarea' );

			anchor.addEventListener( 'input', function () {
				field.value = '<a href="' + escapeHtml( link.url ) + '">' + escapeHtml( anchor.value ) + '</a>';
			} );

			card.appendChild( markup );

			var dismiss = el( 'button', 'button-link', __( 'Dismiss', 'wp-custom-seo' ) );
			dismiss.type = 'button';
			dismiss.addEventListener( 'click', function () {
				card.remove();
			} );

			card.appendChild( dismiss );
			wrap.appendChild( card );
		} );

		report.appendChild( wrap );

		report.appendChild(
			el(
				'p',
				'description',
				__( 'Targets are pages that exist on this site — the model chose from a list, it did not invent them. No link is added until you paste one in.', 'wp-custom-seo' )
			)
		);
	}

	function renderFaq( data ) {
		report.textContent = '';

		var answered = data.answered || [];

		if ( answered.length ) {
			var wrap = section( __( 'Questions this page answers', 'wp-custom-seo' ) );
			var markup = '';

			answered.forEach( function ( row ) {
				var card = el( 'div', 'wpcseo-ai-card' );
				card.appendChild( el( 'p', null, row.question ) );
				card.appendChild( el( 'p', 'description', row.answer ) );

				if ( row.source ) {
					var details = el( 'details' );
					details.appendChild( el( 'summary', null, __( 'Where this came from', 'wp-custom-seo' ) ) );
					details.appendChild( el( 'p', 'description', row.source ) );
					card.appendChild( details );
				}

				if ( ! row.grounded ) {
					card.appendChild(
						el(
							'p',
							'wpcseo-check-issue',
							__( 'This answer could not be traced back to the page content. Check it before publishing it.', 'wp-custom-seo' )
						)
					);
				}

				wrap.appendChild( card );

				markup += '<h3>' + escapeHtml( row.question ) + '</h3>\n<p>' + escapeHtml( row.answer ) + '</p>\n';
			} );

			wrap.appendChild( snippet( markup.trim() ) );
			report.appendChild( wrap );
		}

		if ( data.unanswered && data.unanswered.length ) {
			var gaps = section( __( 'Questions the page does not answer', 'wp-custom-seo' ) );

			data.unanswered.forEach( function ( row ) {
				var item = el( 'div', 'wpcseo-ai-card' );
				item.appendChild( el( 'p', null, row.question ) );
				item.appendChild( el( 'p', 'description', row.why ) );
				gaps.appendChild( item );
			} );

			gaps.appendChild(
				el( 'p', 'description', __( 'These are deliberately unanswered: the page does not contain the answer, so writing one here would be invention.', 'wp-custom-seo' ) )
			);

			report.appendChild( gaps );
		}

		if ( ! answered.length && ( ! data.unanswered || ! data.unanswered.length ) ) {
			report.appendChild( el( 'p', null, __( 'The model did not find a question worth adding.', 'wp-custom-seo' ) ) );

			return;
		}

		report.appendChild(
			el(
				'p',
				'description',
				data.in_content
					? __( 'This page already shows an FAQ, so FAQ structured data is being output for it.', 'wp-custom-seo' )
					: __( 'FAQ structured data is only output once the questions and answers are visible on the page. Paste them in and save first.', 'wp-custom-seo' )
			)
		);
	}

	/**
	 * Called by the panel script. Returns true when this action was handled.
	 */
	window.wpcseoRenderReport = function ( action, data ) {
		if ( 'keywords' === action ) {
			renderKeywords( data );
			return true;
		}

		if ( 'content-analysis' === action ) {
			renderAnalysis( data );
			return true;
		}

		if ( 'internal-links' === action ) {
			renderLinks( data );
			return true;
		}

		if ( 'faq' === action ) {
			renderFaq( data );
			return true;
		}

		report.textContent = '';

		return false;
	};
} )( window.wp );

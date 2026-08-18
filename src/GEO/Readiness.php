<?php
/**
 * AI answer readiness analysis.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\GEO;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Entities\Authors;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Meta;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Scores how usable a page is as a source for an AI-generated answer.
 *
 * **What this is.** An assistant answering a question has to find a claim, see
 * what it is about, and decide whether to trust it. Pages that make those three
 * things easy — a direct answer near a question-shaped heading, named entities
 * rather than pronouns, dated authorship, cited sources — are easier to quote
 * than pages that bury the same information in prose. Everything measured here
 * is a property of the page's own structure, computed locally with no API call.
 *
 * **What this is not.** It is not a Google ranking score, it is not derived
 * from one, and no search engine or AI company publishes a metric like it. No
 * one outside this plugin has seen this number. It is a checklist of things
 * that make a page quotable, added up — useful as a prompt to look at a page,
 * worthless as a prediction of anything.
 *
 * **Why it is heuristic and says so.** "Does this page contain original
 * information" is not decidable by counting. What is countable is whether the
 * page contains figures, tables, first-hand phrasing and citations — the
 * surface features original work usually has. A page can have all of them and
 * be worthless, and a page can have none and be excellent. Every dimension
 * below states what it actually measured, so the number can be argued with.
 *
 * `analyze()` is a pure function of its input array, like the on-page analyzer,
 * so it can be exercised without a loaded WordPress.
 */
final class Readiness {

	/**
	 * The dimensions, in the order they are reported.
	 *
	 * @return array<string, string>
	 */
	public static function dimensions(): array {
		return array(
			'entity_clarity'      => __( 'Entity clarity', 'wp-custom-seo' ),
			'answer_completeness' => __( 'Answer completeness', 'wp-custom-seo' ),
			'original_info'       => __( 'Original information', 'wp-custom-seo' ),
			'topic_coverage'      => __( 'Topic coverage', 'wp-custom-seo' ),
			'structure'           => __( 'Structure', 'wp-custom-seo' ),
			'trust_signals'       => __( 'Trust signals', 'wp-custom-seo' ),
		);
	}

	/**
	 * Words that open a question, used to spot question-shaped headings.
	 */
	private const QUESTION_WORDS = array( 'what', 'why', 'how', 'when', 'where', 'who', 'which', 'is', 'are', 'can', 'do', 'does', 'should', 'will' );

	/**
	 * Phrasings that indicate the writer is reporting their own experience.
	 */
	private const EXPERIENCE_MARKERS = array( ' we tested', ' we measured', ' we found', ' we ran', ' i tested', ' i measured', ' i found', ' in our experience', ' in my experience', ' we compared', ' i compared', ' we built', ' i built', ' our testing', ' our research', ' we surveyed' );

	/**
	 * Run the analysis.
	 *
	 * @param array{content?: string, title?: string, description?: string, author_bio?: string, author_links?: int, has_org?: bool, has_dates?: bool, schema_type?: string} $input Page data.
	 *
	 * @return array{score: int, dimensions: array<int, array<string, mixed>>}
	 */
	public static function analyze( array $input ): array {
		$content = (string) ( $input['content'] ?? '' );
		$text    = Analyzer::to_text( $content );
		$words   = Analyzer::words( $text );

		$facts = array(
			'content'      => $content,
			'text'         => $text,
			'word_count'   => count( $words ),
			'title'        => (string) ( $input['title'] ?? '' ),
			'description'  => (string) ( $input['description'] ?? '' ),
			'headings'     => self::headings( $content ),
			'author_bio'   => (string) ( $input['author_bio'] ?? '' ),
			'author_links' => (int) ( $input['author_links'] ?? 0 ),
			'has_org'      => (bool) ( $input['has_org'] ?? false ),
			'has_dates'    => (bool) ( $input['has_dates'] ?? false ),
			'schema_type'  => (string) ( $input['schema_type'] ?? '' ),
		);

		$dimensions = array(
			self::entity_clarity( $facts ),
			self::answer_completeness( $facts ),
			self::original_info( $facts ),
			self::topic_coverage( $facts ),
			self::structure( $facts ),
			self::trust_signals( $facts ),
		);

		/**
		 * Filters the AI readiness dimensions before the overall score is taken.
		 *
		 * @param array $dimensions Dimension results.
		 * @param array $input      Page data.
		 */
		$dimensions = (array) apply_filters( 'wpcseo_geo_dimensions', $dimensions, $input );

		$total = 0;

		foreach ( $dimensions as $dimension ) {
			$total += (int) $dimension['score'];
		}

		$score = $dimensions ? (int) round( $total / count( $dimensions ) ) : 0;

		/**
		 * Filters the overall AI answer readiness score.
		 *
		 * @param int   $score      Score between 0 and 100.
		 * @param array $dimensions Dimension results.
		 * @param array $input      Page data.
		 */
		$score = (int) apply_filters( 'wpcseo_geo_score', $score, $dimensions, $input );

		return array(
			'score'      => max( 0, min( 100, $score ) ),
			'dimensions' => array_values( $dimensions ),
		);
	}

	/**
	 * Build the analysis input for a stored post.
	 *
	 * Lives here rather than in analyze() because it reads the database, which
	 * would make the scoring untestable without WordPress.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array<string, mixed>
	 */
	public static function input_for( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$author_id = (int) $post->post_author;
		$user      = get_userdata( $author_id );

		$profile_urls = $user ? preg_split( '/\R/', (string) get_user_meta( $author_id, Authors::SAME_AS, true ) ) : array();
		$profile_urls = array_filter( array_map( 'trim', is_array( $profile_urls ) ? $profile_urls : array() ) );

		return array(
			'content'      => (string) $post->post_content,
			'title'        => (string) get_the_title( $post ),
			'description'  => (string) Meta::get( $post_id, Meta::DESCRIPTION ),
			'author_bio'   => $user ? (string) $user->description : '',
			'author_links' => count( $profile_urls ),
			// The organization is only usable as a trust signal when it says
			// something beyond its name — a bare name is the site title again.
			'has_org'      => '' !== trim( (string) Settings::get( 'schema_org_description', '' ) )
				|| '' !== trim( (string) Settings::get( 'schema_org_sameas', '' ) ),
			'has_dates'    => '' !== $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt,
			'schema_type'  => (string) Meta::get( $post_id, Meta::SCHEMA_TYPE ),
		);
	}

	/**
	 * Score one post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array{score: int, dimensions: array<int, array<string, mixed>>}
	 */
	public static function for_post( int $post_id ): array {
		return self::analyze( self::input_for( $post_id ) );
	}

	/**
	 * Assemble one dimension result.
	 *
	 * @param string   $id       Dimension id.
	 * @param int      $score    Score between 0 and 100.
	 * @param string   $measured What was actually counted.
	 * @param string   $why      Why it matters for an AI answer.
	 * @param string[] $fixes    What to do about it, empty when nothing is wrong.
	 *
	 * @return array<string, mixed>
	 */
	private static function dimension( string $id, int $score, string $measured, string $why, array $fixes ): array {
		return array(
			'id'       => $id,
			'label'    => self::dimensions()[ $id ] ?? $id,
			'score'    => max( 0, min( 100, $score ) ),
			'measured' => $measured,
			'why'      => $why,
			'fixes'    => array_values( $fixes ),
		);
	}

	/**
	 * Whether the page names its subject plainly and early.
	 *
	 * @param array<string, mixed> $facts Page facts.
	 *
	 * @return array<string, mixed>
	 */
	private static function entity_clarity( array $facts ): array {
		$title   = (string) $facts['title'];
		$text    = (string) $facts['text'];
		$opening = implode( ' ', array_slice( Analyzer::words( $text ), 0, 80 ) );
		$score   = 0;
		$fixes   = array();

		// The significant words of the title, repeated near the top, are what
		// tell a reader arriving mid-document what this page is about.
		$title_words = array_values(
			array_filter(
				Analyzer::words( $title ),
				static fn ( string $word ): bool => mb_strlen( $word ) > 3
			)
		);

		$matched = $title_words ? count( array_intersect( $title_words, Analyzer::words( $opening ) ) ) : 0;
		$share   = $title_words ? $matched / count( $title_words ) : 0.0;

		if ( $share >= 0.5 ) {
			$score += 40;
		} elseif ( $share > 0.0 ) {
			$score  += 20;
			$fixes[] = __( 'Name the subject of the page in full in the opening paragraph, rather than referring back to the heading.', 'wp-custom-seo' );
		} else {
			$fixes[] = __( 'The opening paragraph does not restate what the page is about. An assistant quoting a passage does not carry the heading with it, so each section has to stand on its own.', 'wp-custom-seo' );
		}

		// A definition sentence — "X is a Y" — is the single most quotable shape
		// a paragraph can take, because it answers a question on its own.
		if ( preg_match( '/\b(is|are|refers to|means|stands for)\b\s+(a|an|the|when|any)\b/iu', $opening ) ) {
			$score += 25;
		} else {
			$fixes[] = __( 'Add a plain definition sentence near the top — “A heat pump is …”. It is the shape an assistant can lift whole.', 'wp-custom-seo' );
		}

		if ( '' !== trim( (string) $facts['description'] ) ) {
			$score += 15;
		} else {
			$fixes[] = __( 'Write a meta description. It is the page’s own one-sentence summary of itself.', 'wp-custom-seo' );
		}

		$schema_type = (string) $facts['schema_type'];

		if ( '' !== $schema_type && 'auto' !== $schema_type && 'none' !== $schema_type ) {
			$score += 20;
		} elseif ( 'none' === $schema_type ) {
			$fixes[] = __( 'Page-level schema is switched off for this page, so nothing states what kind of thing it is.', 'wp-custom-seo' );
		} else {
			$score += 10;
		}

		return self::dimension(
			'entity_clarity',
			$score,
			sprintf(
				/* translators: 1: matched title words, 2: total significant title words. */
				__( '%1$d of %2$d significant title words appear in the opening 80 words.', 'wp-custom-seo' ),
				$matched,
				count( $title_words )
			),
			__( 'An assistant quotes a passage, not a page. A paragraph that names its subject can be used on its own; one that says “it” and “this approach” cannot be quoted without the surrounding text.', 'wp-custom-seo' ),
			$fixes
		);
	}

	/**
	 * Whether questions the page raises are answered near where they are asked.
	 *
	 * @param array<string, mixed> $facts Page facts.
	 *
	 * @return array<string, mixed>
	 */
	private static function answer_completeness( array $facts ): array {
		$headings  = (array) $facts['headings'];
		$questions = array();

		foreach ( $headings as $heading ) {
			$words = Analyzer::words( (string) $heading['text'] );

			if ( ! $words ) {
				continue;
			}

			if ( str_contains( (string) $heading['text'], '?' ) || in_array( $words[0], self::QUESTION_WORDS, true ) ) {
				$questions[] = $heading;
			}
		}

		$score = 0;
		$fixes = array();

		if ( count( $questions ) >= 3 ) {
			$score += 45;
		} elseif ( $questions ) {
			$score  += 25;
			$fixes[] = __( 'Add more question-shaped headings for the things readers actually ask. They are the headings an assistant matches a query against.', 'wp-custom-seo' );
		} else {
			$fixes[] = __( 'No heading is phrased as a question. Rewriting a few section headings as the questions they answer costs nothing and makes each section addressable.', 'wp-custom-seo' );
		}

		// A short paragraph immediately after a question heading is the answer
		// in the form an assistant can quote. A long one is an essay it has to
		// summarise, which is where paraphrase drifts from what you wrote.
		$direct = self::direct_answers( (string) $facts['content'] );

		if ( $questions && $direct >= count( $questions ) * 0.6 ) {
			$score += 35;
		} elseif ( $direct > 0 ) {
			$score  += 18;
			$fixes[] = __( 'Some sections lead with a long passage. Answer in the first sentence or two, then explain underneath.', 'wp-custom-seo' );
		} elseif ( $questions ) {
			$fixes[] = __( 'Sections do not open with a short, direct answer. Put the answer first and the reasoning after it.', 'wp-custom-seo' );
		}

		if ( '' !== trim( (string) $facts['description'] ) ) {
			$score += 20;
		}

		return self::dimension(
			'answer_completeness',
			$score,
			sprintf(
				/* translators: 1: question-shaped headings, 2: sections opening with a short answer. */
				__( '%1$d question-shaped heading(s); %2$d section(s) open with a short, direct answer.', 'wp-custom-seo' ),
				count( $questions ),
				$direct
			),
			__( 'When a page answers a question in its first sentence, an assistant can quote it. When the answer is spread across three paragraphs, the assistant paraphrases — and a paraphrase is where your meaning drifts.', 'wp-custom-seo' ),
			$fixes
		);
	}

	/**
	 * Whether the page carries information that is its own.
	 *
	 * @param array<string, mixed> $facts Page facts.
	 *
	 * @return array<string, mixed>
	 */
	private static function original_info( array $facts ): array {
		$text    = ' ' . mb_strtolower( (string) $facts['text'] ) . ' ';
		$content = (string) $facts['content'];
		$score   = 0;
		$fixes   = array();

		$figures = preg_match_all( '/\b\d+(?:[.,]\d+)?\s*(?:%|per cent|percent|kg|km|mi|ms|gb|mb|hours?|minutes?|days?|weeks?|months?|years?)\b/iu', $text );
		$tables  = preg_match_all( '/<table\b/i', $content );

		if ( $figures >= 5 ) {
			$score += 30;
		} elseif ( $figures > 0 ) {
			$score  += 15;
			$fixes[] = __( 'Add the numbers behind your claims. A figure with a unit is quotable in a way that “significantly faster” is not.', 'wp-custom-seo' );
		} else {
			$fixes[] = __( 'The page contains no measurements or figures. Anything you have actually counted or measured is information no other page has.', 'wp-custom-seo' );
		}

		$experience = 0;

		foreach ( self::EXPERIENCE_MARKERS as $marker ) {
			$experience += substr_count( $text, $marker );
		}

		if ( $experience > 0 ) {
			$score += 30;
		} else {
			$fixes[] = __( 'Nothing here reads as first-hand. If you tested, measured, built or compared something yourself, say so plainly — that is the part no one else can restate.', 'wp-custom-seo' );
		}

		if ( $tables > 0 ) {
			$score += 20;
		} else {
			$fixes[] = __( 'A comparison table states relationships that prose only implies, and it survives being extracted from the page.', 'wp-custom-seo' );
		}

		// Images the site produced itself — diagrams, screenshots, photographs —
		// cannot be verified from here, so only their presence is counted.
		if ( preg_match( '/<img\b/i', $content ) ) {
			$score += 20;
		} else {
			$fixes[] = __( 'The page has no images. A diagram or screenshot you made is original material.', 'wp-custom-seo' );
		}

		return self::dimension(
			'original_info',
			$score,
			sprintf(
				/* translators: 1: figures with units, 2: first-hand phrasings, 3: tables. */
				__( '%1$d figure(s) with units, %2$d first-hand phrasing(s), %3$d table(s).', 'wp-custom-seo' ),
				$figures,
				$experience,
				$tables
			),
			__( 'An assistant summarising a topic has many pages saying the same general things. It cites the one that had a number, a measurement or an account of actually doing the thing. This check counts the surface features original work usually has — it cannot judge whether the work is original, and a page can score well here and still say nothing new.', 'wp-custom-seo' ),
			$fixes
		);
	}

	/**
	 * Whether the page covers its subject rather than touching it.
	 *
	 * @param array<string, mixed> $facts Page facts.
	 *
	 * @return array<string, mixed>
	 */
	private static function topic_coverage( array $facts ): array {
		$words    = (int) $facts['word_count'];
		$headings = (array) $facts['headings'];

		$sections = array_values(
			array_filter(
				$headings,
				static fn ( array $heading ): bool => $heading['level'] >= 2
			)
		);

		$score = 0;
		$fixes = array();

		if ( $words >= 900 ) {
			$score += 40;
		} elseif ( $words >= 400 ) {
			$score += 25;
		} elseif ( $words >= 150 ) {
			$score  += 12;
			$fixes[] = __( 'The page is short. Depth is not word count, but a subject worth citing usually needs more than this to cover.', 'wp-custom-seo' );
		} else {
			$fixes[] = __( 'There is very little content here for an assistant to draw on.', 'wp-custom-seo' );
		}

		if ( count( $sections ) >= 5 ) {
			$score += 35;
		} elseif ( count( $sections ) >= 2 ) {
			$score  += 20;
			$fixes[] = __( 'Add sections for the neighbouring questions a reader will have next — cost, alternatives, limitations, how to start.', 'wp-custom-seo' );
		} else {
			$fixes[] = __( 'The page has almost no sections, so it covers one angle. Assistants assemble answers from pages that address a subject from several.', 'wp-custom-seo' );
		}

		// Distinct section headings, rather than the same word repeated, are what
		// distinguish coverage from length.
		$distinct = count(
			array_unique(
				array_map(
					static fn ( array $heading ): string => mb_strtolower( trim( (string) $heading['text'] ) ),
					$sections
				)
			)
		);

		if ( $distinct >= 4 ) {
			$score += 25;
		} elseif ( $distinct >= 2 ) {
			$score += 12;
		}

		return self::dimension(
			'topic_coverage',
			$score,
			sprintf(
				/* translators: 1: word count, 2: distinct section headings. */
				__( '%1$d words across %2$d distinct section(s).', 'wp-custom-seo' ),
				$words,
				$distinct
			),
			__( 'An assistant answering a broad question pulls from pages that cover the neighbouring questions too. A page that answers exactly one thing is cited for exactly one thing.', 'wp-custom-seo' ),
			$fixes
		);
	}

	/**
	 * Whether the page's structure survives being read by a machine.
	 *
	 * @param array<string, mixed> $facts Page facts.
	 *
	 * @return array<string, mixed>
	 */
	private static function structure( array $facts ): array {
		$content  = (string) $facts['content'];
		$headings = (array) $facts['headings'];
		$score    = 0;
		$fixes    = array();

		$h1 = count(
			array_filter(
				$headings,
				static fn ( array $heading ): bool => 1 === $heading['level']
			)
		);

		if ( $h1 <= 1 ) {
			$score += 25;
		} else {
			$fixes[] = __( 'There is more than one H1. The outline has no single top, so nothing states what the whole page is about.', 'wp-custom-seo' );
		}

		// A jump from H2 straight to H4 leaves a gap in the outline, and the
		// nesting is the only thing saying which section a passage belongs to.
		$skips    = 0;
		$previous = 0;

		foreach ( $headings as $heading ) {
			$level = (int) $heading['level'];

			if ( $previous > 0 && $level > $previous + 1 ) {
				++$skips;
			}

			$previous = $level;
		}

		if ( 0 === $skips ) {
			$score += 25;
		} else {
			$fixes[] = __( 'Heading levels are skipped. Go one level at a time so the outline nests correctly.', 'wp-custom-seo' );
		}

		if ( preg_match( '/<(ul|ol)\b/i', $content ) ) {
			$score += 20;
		} else {
			$fixes[] = __( 'Steps, options and criteria extract far better as a list than as a sentence with semicolons.', 'wp-custom-seo' );
		}

		$paragraphs = preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $content, $matches ) ? $matches[1] : array();
		$long       = 0;

		foreach ( $paragraphs as $paragraph ) {
			if ( count( Analyzer::words( Analyzer::to_text( (string) $paragraph ) ) ) > 120 ) {
				++$long;
			}
		}

		if ( 0 === $long ) {
			$score += 30;
		} elseif ( $long <= 2 ) {
			$score  += 15;
			$fixes[] = __( 'A few paragraphs run very long. Split them so each makes one point.', 'wp-custom-seo' );
		} else {
			$fixes[] = __( 'Several paragraphs are very long. One idea per paragraph is what makes a passage quotable on its own.', 'wp-custom-seo' );
		}

		return self::dimension(
			'structure',
			$score,
			sprintf(
				/* translators: 1: H1 count, 2: skipped heading levels, 3: over-long paragraphs. */
				__( '%1$d H1, %2$d skipped heading level(s), %3$d paragraph(s) over 120 words.', 'wp-custom-seo' ),
				$h1,
				$skips,
				$long
			),
			__( 'Structure is how a machine works out which passage answers which question. A correct heading outline and short paragraphs mean the right two sentences get quoted instead of the wrong five.', 'wp-custom-seo' ),
			$fixes
		);
	}

	/**
	 * Whether the page says who wrote it and where its claims come from.
	 *
	 * @param array<string, mixed> $facts Page facts.
	 *
	 * @return array<string, mixed>
	 */
	private static function trust_signals( array $facts ): array {
		$content = (string) $facts['content'];
		$score   = 0;
		$fixes   = array();

		$outbound = preg_match_all( '/<a\b[^>]*\bhref\s*=\s*["\']https?:\/\/[^"\']+["\']/i', $content );

		if ( $outbound >= 3 ) {
			$score += 30;
		} elseif ( $outbound > 0 ) {
			$score  += 15;
			$fixes[] = __( 'Cite more of your sources. A claim with a link behind it can be checked; one without has to be taken on faith.', 'wp-custom-seo' );
		} else {
			$fixes[] = __( 'The page cites no external sources. Link to the standard, study or documentation behind any figure or claim you make.', 'wp-custom-seo' );
		}

		if ( '' !== trim( (string) $facts['author_bio'] ) ) {
			$score += 25;
		} else {
			$fixes[] = __( 'The author has no biography. Fill it in on their WordPress profile — it is what states why this person is worth reading on this subject.', 'wp-custom-seo' );
		}

		if ( (int) $facts['author_links'] > 0 ) {
			$score += 20;
		} else {
			$fixes[] = __( 'The author has no profile URLs set, so there is nothing connecting this byline to the same person elsewhere.', 'wp-custom-seo' );
		}

		if ( (bool) $facts['has_dates'] ) {
			$score += 10;
		}

		if ( (bool) $facts['has_org'] ) {
			$score += 15;
		} else {
			$fixes[] = __( 'The site publishes no organization description or profile URLs. Fill these in under Settings → Schema & Entities so there is a publisher behind the page.', 'wp-custom-seo' );
		}

		return self::dimension(
			'trust_signals',
			$score,
			sprintf(
				/* translators: %d: number of outbound links. */
				__( '%d outbound citation(s); author and publisher details as configured.', 'wp-custom-seo' ),
				$outbound
			),
			__( 'An assistant deciding between two pages that say the same thing has to prefer one. A named author with a stated background, a dated page and cited sources are the things that make one page the safer citation.', 'wp-custom-seo' ),
			$fixes
		);
	}

	/**
	 * How many sections open with a short, direct answer.
	 *
	 * @param string $content Raw HTML content.
	 */
	private static function direct_answers( string $content ): int {
		if ( ! preg_match_all( '#<h[2-6]\b[^>]*>.*?</h[2-6]>(.*?)(?=<h[2-6]\b|$)#is', $content, $matches ) ) {
			return 0;
		}

		$direct = 0;

		foreach ( $matches[1] as $section ) {
			if ( ! preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', (string) $section, $first ) ) {
				continue;
			}

			$count = count( Analyzer::words( Analyzer::to_text( (string) $first[1] ) ) );

			// Long enough to be an answer, short enough to be quoted whole.
			if ( $count >= 8 && $count <= 60 ) {
				++$direct;
			}
		}

		return $direct;
	}

	/**
	 * Extract headings from HTML.
	 *
	 * @param string $html Raw content.
	 *
	 * @return array<int, array{level: int, text: string}>
	 */
	private static function headings( string $html ): array {
		preg_match_all( '#<h([1-6])\b[^>]*>(.*?)</h\1>#is', $html, $matches, PREG_SET_ORDER );

		$headings = array();

		foreach ( $matches as $match ) {
			$headings[] = array(
				'level' => (int) $match[1],
				'text'  => wp_strip_all_tags( $match[2] ),
			);
		}

		return $headings;
	}
}

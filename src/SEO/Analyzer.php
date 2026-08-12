<?php
/**
 * On-page SEO analysis engine.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Scores a page against on-page SEO checks.
 *
 * The score is this plugin's own optimization score. It is not Google's
 * ranking algorithm and does not predict rankings; it measures how completely
 * a page follows well-established on-page practices.
 *
 * analyze() is a pure function of its input array so it can be exercised
 * without a loaded WordPress.
 */
final class Analyzer {

	public const GOOD = 'good';

	public const WARN = 'warn';

	public const BAD = 'bad';

	private const TITLE_MIN = 30;

	private const TITLE_MAX = 60;

	private const DESCRIPTION_MIN = 120;

	private const DESCRIPTION_MAX = 160;

	/**
	 * Run the analysis.
	 *
	 * @param array{title?: string, description?: string, keyword?: string, content?: string, slug?: string, host?: string} $input Page data.
	 *
	 * @return array{score: int, grade: string, checks: array<int, array<string, mixed>>}
	 */
	public static function analyze( array $input ): array {
		/**
		 * Fires before an analysis runs.
		 *
		 * @param array $input Page data.
		 */
		do_action( 'wpcseo_before_analysis', $input );

		$title       = self::normalize( (string) ( $input['title'] ?? '' ) );
		$description = self::normalize( (string) ( $input['description'] ?? '' ) );
		$keyword     = self::normalize( (string) ( $input['keyword'] ?? '' ) );
		$content     = (string) ( $input['content'] ?? '' );
		$slug        = (string) ( $input['slug'] ?? '' );
		$host        = (string) ( $input['host'] ?? '' );

		$text       = self::to_text( $content );
		$words      = self::words( $text );
		$word_count = count( $words );

		$checks = array_merge(
			self::check_keyword( $keyword ),
			self::check_title( $title, $keyword ),
			self::check_description( $description, $keyword ),
			self::check_content( $text, $word_count, $keyword ),
			self::check_structure( $content, $keyword ),
			self::check_media( $content ),
			self::check_links( $content, $host ),
			self::check_slug( $slug, $keyword )
		);

		/**
		 * Filters the individual analysis checks before scoring.
		 *
		 * @param array $checks Check results.
		 * @param array $input  Page data.
		 */
		$checks = (array) apply_filters( 'wpcseo_analysis_checks', $checks, $input );

		$score = self::score( $checks );

		/**
		 * Filters the final optimization score.
		 *
		 * @param int   $score  Score between 0 and 100.
		 * @param array $checks Check results.
		 * @param array $input  Page data.
		 */
		$score = (int) apply_filters( 'wpcseo_score', $score, $checks, $input );

		$result = array(
			'score'      => $score,
			'grade'      => self::grade( $score ),
			'word_count' => $word_count,
			'checks'     => array_values( $checks ),
		);

		/**
		 * Fires after an analysis completes.
		 *
		 * @param array $result Analysis result.
		 * @param array $input  Page data.
		 */
		do_action( 'wpcseo_after_analysis', $result, $input );

		return $result;
	}

	/**
	 * Weighted score across all checks.
	 *
	 * @param array<int, array<string, mixed>> $checks Check results.
	 */
	private static function score( array $checks ): int {
		$earned  = 0.0;
		$maximum = 0.0;

		foreach ( $checks as $check ) {
			$weight   = (float) ( $check['weight'] ?? 1 );
			$maximum += $weight;

			$earned += match ( $check['status'] ?? self::BAD ) {
				self::GOOD => $weight,
				self::WARN => $weight * 0.5,
				default    => 0.0,
			};
		}

		if ( $maximum <= 0.0 ) {
			return 0;
		}

		return (int) round( ( $earned / $maximum ) * 100 );
	}

	/**
	 * Bucket a score for display.
	 *
	 * @param int $score Score between 0 and 100.
	 */
	public static function grade( int $score ): string {
		if ( $score >= 80 ) {
			return self::GOOD;
		}

		return $score >= 50 ? self::WARN : self::BAD;
	}

	/**
	 * Build one check result.
	 *
	 * The weight and the label are read from the score model rather than passed
	 * in, so the two things a site owner can change about a check live in one
	 * table instead of being scattered across sixteen call sites.
	 *
	 * @param string $id             Check id.
	 * @param string $status         One of GOOD, WARN, BAD.
	 * @param string $label          Short name, used only if the check is not in the model.
	 * @param string $issue          What was detected.
	 * @param string $why            Why it matters.
	 * @param string $recommendation What to do about it.
	 *
	 * @return array<string, mixed>
	 */
	private static function result( string $id, string $status, string $label, string $issue, string $why, string $recommendation ): array {
		$weight = Weights::get( $id );

		return array(
			'id'             => $id,
			'status'         => $status,
			'weight'         => $weight,
			// A check worth nothing still says its piece; the editor marks it
			// as excluded rather than hiding it.
			'counts'         => $weight > 0.0,
			'label'          => Weights::label( $id, $label ),
			'issue'          => $issue,
			'why'            => $why,
			'recommendation' => $recommendation,
		);
	}

	/**
	 * Focus keyword presence.
	 *
	 * @param string $keyword Focus keyword.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_keyword( string $keyword ): array {
		$set = '' !== $keyword;

		return array(
			self::result(
				'focus_keyword',
				$set ? self::GOOD : self::BAD,
				__( 'Focus keyphrase', 'wp-custom-seo' ),
				$set
					? sprintf( /* translators: %s: focus keyphrase. */ __( 'Focus keyphrase set to "%s".', 'wp-custom-seo' ), $keyword )
					: __( 'No focus keyphrase has been set.', 'wp-custom-seo' ),
				__( 'A focus keyphrase states what the page is about, which is what the keyword-related checks below are measured against.', 'wp-custom-seo' ),
				$set
					? __( 'No change needed.', 'wp-custom-seo' )
					: __( 'Enter the phrase a reader would search for to find this page.', 'wp-custom-seo' )
			),
		);
	}

	/**
	 * SEO title checks.
	 *
	 * @param string $title   Effective SEO title.
	 * @param string $keyword Focus keyword.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_title( string $title, string $keyword ): array {
		$length = mb_strlen( $title );
		$checks = array();

		if ( 0 === $length ) {
			$status = self::BAD;
			$issue  = __( 'The SEO title is empty.', 'wp-custom-seo' );
		} elseif ( $length < self::TITLE_MIN ) {
			$status = self::WARN;
			/* translators: %d: title length in characters. */
			$issue = sprintf( __( 'The SEO title is %d characters, which is short.', 'wp-custom-seo' ), $length );
		} elseif ( $length > self::TITLE_MAX ) {
			$status = self::WARN;
			/* translators: %d: title length in characters. */
			$issue = sprintf( __( 'The SEO title is %d characters and will likely be truncated in results.', 'wp-custom-seo' ), $length );
		} else {
			$status = self::GOOD;
			/* translators: %d: title length in characters. */
			$issue = sprintf( __( 'The SEO title is %d characters.', 'wp-custom-seo' ), $length );
		}

		$checks[] = self::result(
			'title_length',
			$status,
			__( 'Title length', 'wp-custom-seo' ),
			$issue,
			__( 'Search engines shorten long titles, so the part that persuades someone to click can disappear.', 'wp-custom-seo' ),
			sprintf(
				/* translators: 1: minimum characters, 2: maximum characters. */
				__( 'Aim for roughly %1$d to %2$d characters.', 'wp-custom-seo' ),
				self::TITLE_MIN,
				self::TITLE_MAX
			)
		);

		if ( '' === $keyword ) {
			return $checks;
		}

		$position = self::position( $title, $keyword );

		if ( null === $position ) {
			$status = self::BAD;
			$issue  = __( 'The focus keyphrase does not appear in the SEO title.', 'wp-custom-seo' );
		} elseif ( $position > (int) ceil( $length / 2 ) ) {
			$status = self::WARN;
			$issue  = __( 'The focus keyphrase appears late in the SEO title.', 'wp-custom-seo' );
		} else {
			$status = self::GOOD;
			$issue  = __( 'The focus keyphrase appears near the start of the SEO title.', 'wp-custom-seo' );
		}

		$checks[] = self::result(
			'title_keyword',
			$status,
			__( 'Keyphrase in title', 'wp-custom-seo' ),
			$issue,
			__( 'The title is the strongest on-page signal of what a page covers, and it is what a searcher reads first.', 'wp-custom-seo' ),
			__( 'Work the keyphrase into the title, ideally near the beginning, without making it read awkwardly.', 'wp-custom-seo' )
		);

		return $checks;
	}

	/**
	 * Meta description checks.
	 *
	 * @param string $description Effective meta description.
	 * @param string $keyword     Focus keyword.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_description( string $description, string $keyword ): array {
		$length = mb_strlen( $description );
		$checks = array();

		if ( 0 === $length ) {
			$status = self::BAD;
			$issue  = __( 'No meta description has been written.', 'wp-custom-seo' );
		} elseif ( $length < self::DESCRIPTION_MIN ) {
			$status = self::WARN;
			/* translators: %d: description length in characters. */
			$issue = sprintf( __( 'The meta description is %d characters, which leaves space unused.', 'wp-custom-seo' ), $length );
		} elseif ( $length > self::DESCRIPTION_MAX ) {
			$status = self::WARN;
			/* translators: %d: description length in characters. */
			$issue = sprintf( __( 'The meta description is %d characters and will likely be cut off.', 'wp-custom-seo' ), $length );
		} else {
			$status = self::GOOD;
			/* translators: %d: description length in characters. */
			$issue = sprintf( __( 'The meta description is %d characters.', 'wp-custom-seo' ), $length );
		}

		$checks[] = self::result(
			'description_length',
			$status,
			__( 'Meta description', 'wp-custom-seo' ),
			$issue,
			__( 'Search engines often show this text under the title. Without one they assemble their own, which may not describe the page well.', 'wp-custom-seo' ),
			sprintf(
				/* translators: 1: minimum characters, 2: maximum characters. */
				__( 'Write %1$d to %2$d characters summarising the page and giving a reason to click.', 'wp-custom-seo' ),
				self::DESCRIPTION_MIN,
				self::DESCRIPTION_MAX
			)
		);

		if ( '' === $keyword || 0 === $length ) {
			return $checks;
		}

		$found = null !== self::position( $description, $keyword );

		$checks[] = self::result(
			'description_keyword',
			$found ? self::GOOD : self::WARN,
			__( 'Keyphrase in description', 'wp-custom-seo' ),
			$found
				? __( 'The focus keyphrase appears in the meta description.', 'wp-custom-seo' )
				: __( 'The focus keyphrase does not appear in the meta description.', 'wp-custom-seo' ),
			__( 'Matched words are highlighted in search results, which makes the entry easier to spot.', 'wp-custom-seo' ),
			__( 'Mention the keyphrase once, naturally, in the description.', 'wp-custom-seo' )
		);

		return $checks;
	}

	/**
	 * Content length, opening paragraph and keyphrase distribution.
	 *
	 * @param string $text       Plain text content.
	 * @param int    $word_count Word count.
	 * @param string $keyword    Focus keyword.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_content( string $text, int $word_count, string $keyword ): array {
		if ( $word_count >= 300 ) {
			$status = self::GOOD;
		} elseif ( $word_count >= 150 ) {
			$status = self::WARN;
		} else {
			$status = self::BAD;
		}

		$checks = array(
			self::result(
				'content_length',
				$status,
				__( 'Content length', 'wp-custom-seo' ),
				/* translators: %d: word count. */
				sprintf( __( 'The content is %d words long.', 'wp-custom-seo' ), $word_count ),
				__( 'Very short pages often fail to answer the question a visitor arrived with. Length is not a ranking factor in itself, but thin coverage usually shows.', 'wp-custom-seo' ),
				__( 'Cover the topic properly rather than padding to hit a number. Around 300 words is a reasonable floor for an indexable page.', 'wp-custom-seo' )
			),
		);

		if ( '' === $keyword || 0 === $word_count ) {
			return $checks;
		}

		$occurrences = self::count_occurrences( $text, $keyword );
		$density     = ( $occurrences / $word_count ) * 100;

		if ( $occurrences < 1 ) {
			$status = self::BAD;
			$issue  = __( 'The focus keyphrase does not appear in the content.', 'wp-custom-seo' );
		} elseif ( $density > 3.0 ) {
			$status = self::BAD;
			/* translators: 1: occurrence count, 2: density percentage. */
			$issue = sprintf( __( 'The keyphrase appears %1$d times (%2$.1f%%), which reads as stuffing.', 'wp-custom-seo' ), $occurrences, $density );
		} elseif ( $density < 0.5 ) {
			$status = self::WARN;
			/* translators: 1: occurrence count, 2: density percentage. */
			$issue = sprintf( __( 'The keyphrase appears %1$d time(s) (%2$.1f%%), which is sparse for this length.', 'wp-custom-seo' ), $occurrences, $density );
		} else {
			$status = self::GOOD;
			/* translators: 1: occurrence count, 2: density percentage. */
			$issue = sprintf( __( 'The keyphrase appears %1$d time(s) (%2$.1f%%).', 'wp-custom-seo' ), $occurrences, $density );
		}

		$checks[] = self::result(
			'keyword_density',
			$status,
			__( 'Keyphrase distribution', 'wp-custom-seo' ),
			$issue,
			__( 'The phrase should appear often enough to be clearly on topic, but repetition beyond natural use reads badly to people and is treated as spam.', 'wp-custom-seo' ),
			__( 'Aim for roughly 0.5% to 3% and prefer natural variations over exact repetition.', 'wp-custom-seo' )
		);

		$opening = self::first_paragraph( $text );
		$early   = null !== self::position( $opening, $keyword );

		$checks[] = self::result(
			'keyword_intro',
			$early ? self::GOOD : self::WARN,
			__( 'Keyphrase in introduction', 'wp-custom-seo' ),
			$early
				? __( 'The focus keyphrase appears in the opening paragraph.', 'wp-custom-seo' )
				: __( 'The focus keyphrase does not appear in the opening paragraph.', 'wp-custom-seo' ),
			__( 'Readers decide within a sentence or two whether a page answers their question. Stating the subject early confirms they are in the right place.', 'wp-custom-seo' ),
			__( 'Mention the topic in the first paragraph.', 'wp-custom-seo' )
		);

		return $checks;
	}

	/**
	 * Heading structure checks.
	 *
	 * @param string $content Raw HTML content.
	 * @param string $keyword Focus keyword.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_structure( string $content, string $keyword ): array {
		$headings = self::headings( $content );
		$subs     = array_filter(
			$headings,
			static fn ( array $heading ): bool => $heading['level'] >= 2
		);

		$checks = array(
			self::result(
				'subheadings',
				$subs ? self::GOOD : self::WARN,
				__( 'Subheadings', 'wp-custom-seo' ),
				$subs
					/* translators: %d: number of subheadings. */
					? sprintf( __( 'The content uses %d subheading(s).', 'wp-custom-seo' ), count( $subs ) )
					: __( 'The content has no subheadings.', 'wp-custom-seo' ),
				__( 'Subheadings let readers scan a page and find the section they need, and they give assistive technology a navigable outline.', 'wp-custom-seo' ),
				__( 'Break longer content into sections introduced by H2 and H3 headings.', 'wp-custom-seo' )
			),
		);

		$h1_count = count(
			array_filter(
				$headings,
				static fn ( array $heading ): bool => 1 === $heading['level']
			)
		);

		if ( $h1_count > 1 ) {
			$checks[] = self::result(
				'single_h1',
				self::WARN,
				__( 'Heading hierarchy', 'wp-custom-seo' ),
				/* translators: %d: number of H1 headings. */
				sprintf( __( 'The content contains %d H1 headings.', 'wp-custom-seo' ), $h1_count ),
				__( 'A page normally has one H1 naming its subject. Several competing H1s blur the outline for readers and assistive technology alike.', 'wp-custom-seo' ),
				__( 'Keep one H1 and demote the rest to H2 or H3.', 'wp-custom-seo' )
			);
		}

		if ( '' === $keyword || ! $subs ) {
			return $checks;
		}

		$in_subheading = false;

		foreach ( $subs as $heading ) {
			if ( null !== self::position( self::normalize( $heading['text'] ), $keyword ) ) {
				$in_subheading = true;
				break;
			}
		}

		$checks[] = self::result(
			'keyword_subheading',
			$in_subheading ? self::GOOD : self::WARN,
			__( 'Keyphrase in subheading', 'wp-custom-seo' ),
			$in_subheading
				? __( 'A subheading contains the focus keyphrase.', 'wp-custom-seo' )
				: __( 'No subheading contains the focus keyphrase.', 'wp-custom-seo' ),
			__( 'Subheadings summarise sections, so a relevant one reinforces what the page covers.', 'wp-custom-seo' ),
			__( 'Use the keyphrase or a close variation in at least one subheading, where it fits naturally.', 'wp-custom-seo' )
		);

		return $checks;
	}

	/**
	 * Image checks.
	 *
	 * @param string $content Raw HTML content.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_media( string $content ): array {
		preg_match_all( '/<img\b[^>]*>/i', $content, $matches );

		$images = $matches[0];

		if ( ! $images ) {
			return array(
				self::result(
					'images',
					self::WARN,
					__( 'Images', 'wp-custom-seo' ),
					__( 'The content contains no images.', 'wp-custom-seo' ),
					__( 'Diagrams, screenshots and photographs explain things text struggles with, and they give the page a chance to appear in image search.', 'wp-custom-seo' ),
					__( 'Add an image where it genuinely helps explain the content.', 'wp-custom-seo' )
				),
			);
		}

		$missing_alt = 0;

		foreach ( $images as $image ) {
			if ( ! preg_match( '/\balt\s*=\s*("[^"]*[^"\s][^"]*"|\'[^\']*[^\'\s][^\']*\')/i', $image ) ) {
				++$missing_alt;
			}
		}

		return array(
			self::result(
				'image_alt',
				0 === $missing_alt ? self::GOOD : self::BAD,
				__( 'Image alt text', 'wp-custom-seo' ),
				0 === $missing_alt
					/* translators: %d: number of images. */
					? sprintf( __( 'All %d image(s) have alt text.', 'wp-custom-seo' ), count( $images ) )
					/* translators: 1: images missing alt text, 2: total images. */
					: sprintf( __( '%1$d of %2$d image(s) are missing alt text.', 'wp-custom-seo' ), $missing_alt, count( $images ) ),
				__( 'Alt text is what screen reader users hear in place of the image, and it is how search engines understand it. Missing alt text is an accessibility failure first and an SEO one second.', 'wp-custom-seo' ),
				__( 'Describe what the image shows. Leave alt empty only for purely decorative images.', 'wp-custom-seo' )
			),
		);
	}

	/**
	 * Internal and external link checks.
	 *
	 * @param string $content Raw HTML content.
	 * @param string $host    Site host, used to classify links.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_links( string $content, string $host ): array {
		preg_match_all( '/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $content, $matches );

		$internal = 0;
		$external = 0;

		foreach ( $matches[1] as $href ) {
			$href = trim( html_entity_decode( $href, ENT_QUOTES ) );

			if ( '' === $href || preg_match( '/^(#|mailto:|tel:|javascript:)/i', $href ) ) {
				continue;
			}

			$link_host = (string) wp_parse_url( $href, PHP_URL_HOST );

			if ( '' === $link_host || ( '' !== $host && self::same_host( $link_host, $host ) ) ) {
				++$internal;
				continue;
			}

			++$external;
		}

		return array(
			self::result(
				'internal_links',
				$internal > 0 ? self::GOOD : self::BAD,
				__( 'Internal links', 'wp-custom-seo' ),
				$internal > 0
					/* translators: %d: number of internal links. */
					? sprintf( __( 'The content links to %d other page(s) on this site.', 'wp-custom-seo' ), $internal )
					: __( 'The content does not link to any other page on this site.', 'wp-custom-seo' ),
				__( 'Internal links let readers follow a subject further and are how search engines discover and relate your pages. A page with none is effectively a dead end.', 'wp-custom-seo' ),
				__( 'Link to related pages where the reference genuinely helps the reader.', 'wp-custom-seo' )
			),
			self::result(
				'external_links',
				$external > 0 ? self::GOOD : self::WARN,
				__( 'External references', 'wp-custom-seo' ),
				$external > 0
					/* translators: %d: number of external links. */
					? sprintf( __( 'The content cites %d external source(s).', 'wp-custom-seo' ), $external )
					: __( 'The content cites no external sources.', 'wp-custom-seo' ),
				__( 'Citing sources lets a reader verify claims, which is a large part of what makes content trustworthy.', 'wp-custom-seo' ),
				__( 'Link to the sources behind any figures, standards or quotations you use.', 'wp-custom-seo' )
			),
		);
	}

	/**
	 * URL slug check.
	 *
	 * @param string $slug    Post slug.
	 * @param string $keyword Focus keyword.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function check_slug( string $slug, string $keyword ): array {
		if ( '' === $keyword || '' === $slug ) {
			return array();
		}

		$normalized_slug    = str_replace( array( '-', '_', '/' ), ' ', mb_strtolower( $slug ) );
		$keyword_words      = self::words( $keyword );
		$slug_words         = self::words( $normalized_slug );
		$matched            = array_intersect( $keyword_words, $slug_words );
		$all_words_in_slug  = $keyword_words && count( $matched ) === count( $keyword_words );
		$some_words_in_slug = (bool) $matched;

		if ( $all_words_in_slug ) {
			$status = self::GOOD;
			$issue  = __( 'The URL slug contains the focus keyphrase.', 'wp-custom-seo' );
		} elseif ( $some_words_in_slug ) {
			$status = self::WARN;
			$issue  = __( 'The URL slug contains only part of the focus keyphrase.', 'wp-custom-seo' );
		} else {
			$status = self::BAD;
			$issue  = __( 'The URL slug does not contain the focus keyphrase.', 'wp-custom-seo' );
		}

		return array(
			self::result(
				'slug_keyword',
				$status,
				__( 'Keyphrase in URL', 'wp-custom-seo' ),
				$issue,
				__( 'A readable URL tells people what a link leads to before they follow it.', 'wp-custom-seo' ),
				__( 'Use a short slug built from the keyphrase. Avoid changing the slug of a published page unless you also add a redirect.', 'wp-custom-seo' )
			),
		);
	}

	/**
	 * Compare hosts, ignoring a leading www.
	 *
	 * @param string $a First host.
	 * @param string $b Second host.
	 */
	private static function same_host( string $a, string $b ): bool {
		$strip = static fn ( string $host ): string => preg_replace( '/^www\./i', '', mb_strtolower( $host ) ) ?? '';

		return $strip( $a ) === $strip( $b );
	}

	/**
	 * Collapse whitespace and trim.
	 *
	 * @param string $text Source text.
	 */
	private static function normalize( string $text ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Reduce HTML content to plain text.
	 *
	 * @param string $html Raw content.
	 */
	public static function to_text( string $html ): string {
		$html = (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = (string) preg_replace( '#<(br|/p|/h[1-6]|/li|/div)[^>]*>#i', "\n", $html );
		$html = wp_strip_all_tags( $html );

		return self::normalize( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Split text into words.
	 *
	 * @param string $text Plain text.
	 *
	 * @return string[]
	 */
	public static function words( string $text ): array {
		$parts = preg_split( "/[^\p{L}\p{N}'’-]+/u", mb_strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * Character offset of a keyword within a haystack, or null when absent.
	 *
	 * @param string $haystack Text to search.
	 * @param string $keyword  Phrase to find.
	 */
	private static function position( string $haystack, string $keyword ): ?int {
		if ( '' === $keyword || '' === $haystack ) {
			return null;
		}

		$position = mb_stripos( $haystack, $keyword );

		return false === $position ? null : $position;
	}

	/**
	 * Count keyword occurrences.
	 *
	 * @param string $text    Plain text.
	 * @param string $keyword Phrase to count.
	 */
	private static function count_occurrences( string $text, string $keyword ): int {
		if ( '' === $keyword ) {
			return 0;
		}

		return (int) preg_match_all( '/' . preg_quote( $keyword, '/' ) . '/iu', $text );
	}

	/**
	 * First paragraph, falling back to the opening words.
	 *
	 * @param string $text Plain text with paragraph newlines preserved by to_text().
	 */
	private static function first_paragraph( string $text ): string {
		$words = self::words( $text );

		// ponytail: to_text() collapses newlines, so "introduction" means the
		// opening 60 words. Switch to real paragraph splitting if the check
		// proves too loose in practice.
		return implode( ' ', array_slice( $words, 0, 60 ) );
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

<?php
/**
 * FAQPage structured data, from visible content only.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema;

use WPCustomSeo\SEO\Analyzer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Emits FAQPage only when the page actually shows an FAQ.
 *
 * This is why FAQPage was left out of the manually selectable schema types in
 * Phase 3. Structured data must describe what a visitor sees; a page that
 * claims FAQPage without showing questions and answers is misrepresenting
 * itself, and that is the kind of mismatch that earns a manual action.
 *
 * So the type is never something an editor ticks. It is derived: the content is
 * read for a real question-and-answer structure, and the node is emitted only
 * if one is found. Generating FAQ text with the AI assistant is not enough on
 * its own — the text has to be put on the page first.
 */
final class Faq {

	/**
	 * Minimum pairs before a page counts as having an FAQ.
	 *
	 * One question and answer is a paragraph. Two or more is a section a reader
	 * would recognise as an FAQ.
	 */
	private const MINIMUM = 2;

	/**
	 * Question-and-answer pairs visible in a block of content.
	 *
	 * Recognises the two structures a WordPress editor actually produces: a
	 * heading phrased as a question followed by its answer, and a details or
	 * summary disclosure block.
	 *
	 * @param string $content Raw post content.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function detect( string $content ): array {
		if ( '' === trim( $content ) ) {
			return array();
		}

		$pairs = array_merge( self::from_headings( $content ), self::from_details( $content ) );

		/**
		 * Filters the question and answer pairs detected in content.
		 *
		 * @param array  $pairs   Detected pairs.
		 * @param string $content Raw content.
		 */
		return (array) apply_filters( 'wpcseo_detected_faq', $pairs, $content );
	}

	/**
	 * Whether a page shows enough of an FAQ to be described as one.
	 *
	 * @param string $content Raw post content.
	 */
	public static function qualifies( string $content ): bool {
		return count( self::detect( $content ) ) >= self::MINIMUM;
	}

	/**
	 * Pairs from headings phrased as questions.
	 *
	 * @param string $content Raw post content.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	private static function from_headings( string $content ): array {
		// Split on headings, keeping the heading text and its level.
		$parts = preg_split( '#<h([2-4])\b[^>]*>(.*?)</h\1>#is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) || count( $parts ) < 4 ) {
			return array();
		}

		$pairs = array();
		$total = count( $parts );

		// Chunks run: text before, then level, heading and body per match.
		for ( $i = 1; $i + 2 < $total + 1; $i += 3 ) {
			$heading = isset( $parts[ $i + 1 ] ) ? Analyzer::to_text( (string) $parts[ $i + 1 ] ) : '';
			$body    = isset( $parts[ $i + 2 ] ) ? Analyzer::to_text( (string) $parts[ $i + 2 ] ) : '';

			if ( ! self::is_question( $heading ) || '' === $body ) {
				continue;
			}

			$pairs[] = array(
				'question' => $heading,
				'answer'   => $body,
			);
		}

		return $pairs;
	}

	/**
	 * Pairs from disclosure blocks.
	 *
	 * @param string $content Raw post content.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	private static function from_details( string $content ): array {
		preg_match_all( '#<details\b[^>]*>(.*?)</details>#is', $content, $matches, PREG_SET_ORDER );

		$pairs = array();

		foreach ( $matches as $match ) {
			$inner = (string) $match[1];

			if ( ! preg_match( '#<summary\b[^>]*>(.*?)</summary>#is', $inner, $summary ) ) {
				continue;
			}

			$question = Analyzer::to_text( (string) $summary[1] );
			$answer   = Analyzer::to_text( (string) preg_replace( '#<summary\b[^>]*>.*?</summary>#is', '', $inner ) );

			if ( ! self::is_question( $question ) || '' === $answer ) {
				continue;
			}

			$pairs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $pairs;
	}

	/**
	 * Whether a heading reads as a question.
	 *
	 * A trailing question mark is the reliable signal. Headings that merely
	 * begin with an interrogative word — "How we work" — are statements, so a
	 * question mark is required rather than inferred.
	 *
	 * @param string $text Heading text.
	 */
	public static function is_question( string $text ): bool {
		$text = trim( $text );

		return '' !== $text && str_ends_with( $text, '?' );
	}

	/**
	 * Question nodes for a post, empty when the page shows no FAQ.
	 *
	 * These belong on the page node itself rather than in a node of their own:
	 * the FAQ is what part of that page *is*, so the page gains the FAQPage
	 * type and carries the questions, and nothing in the graph is left
	 * describing a page that does not exist.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function entities( WP_Post $post ): array {
		$pairs = self::detect( (string) $post->post_content );

		if ( count( $pairs ) < self::MINIMUM ) {
			return array();
		}

		$entities = array();

		foreach ( $pairs as $pair ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $pair['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $pair['answer'],
				),
			);
		}

		return $entities;
	}
}

<?php
/**
 * Keyword cannibalization detection.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Audit;

use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

/*
 * Reads post meta through one grouped query rather than N per-post lookups.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */

/**
 * Finds published pages competing for the same search term.
 *
 * This is arithmetic, not inference: focus keyphrases are normalised and
 * grouped, so a match is a fact about what the site says it is targeting, not
 * an opinion. Nothing is sent anywhere and it costs nothing to run.
 *
 * Two phrases are treated as the same target when their significant words are
 * the same set — "roof insulation" and "insulation for roofs" collide, which
 * is the case an exact string match would miss.
 */
final class Cannibalization {

	/**
	 * Words too common to distinguish one target from another.
	 *
	 * @var string[]
	 */
	private const STOP_WORDS = array(
		'a',
		'an',
		'and',
		'are',
		'as',
		'at',
		'be',
		'best',
		'but',
		'by',
		'for',
		'from',
		'how',
		'in',
		'is',
		'it',
		'of',
		'on',
		'or',
		'the',
		'to',
		'top',
		'what',
		'when',
		'where',
		'which',
		'why',
		'with',
		'your',
	);

	/**
	 * Reduce a phrase to its distinguishing words, sorted.
	 *
	 * Sorting is what makes word order irrelevant, so "roof insulation" and
	 * "insulation roof" land in the same group.
	 *
	 * @param string $phrase Raw keyphrase.
	 *
	 * @return string[]
	 */
	public static function tokens( string $phrase ): array {
		$kept = array_values( array_unique( self::tokens_all( $phrase ) ) );

		sort( $kept );

		return $kept;
	}

	/**
	 * Significant words in order, keeping repeats.
	 *
	 * Signature matching wants a unique sorted set; counting how often a word
	 * appears wants every occurrence, so the two callers need different views
	 * of the same normalisation.
	 *
	 * @param string $phrase Raw text.
	 *
	 * @return string[]
	 */
	public static function tokens_all( string $phrase ): array {
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( trim( $phrase ) ), -1, PREG_SPLIT_NO_EMPTY );
		$parts = is_array( $parts ) ? $parts : array();

		$kept = array();

		foreach ( $parts as $word ) {
			// Trailing plurals are stripped so "roof" and "roofs" agree.
			$singular = ( mb_strlen( $word ) > 3 && str_ends_with( $word, 's' ) && ! str_ends_with( $word, 'ss' ) )
				? mb_substr( $word, 0, -1 )
				: $word;

			if ( ! in_array( $singular, self::STOP_WORDS, true ) ) {
				$kept[] = $singular;
			}
		}

		return $kept;
	}

	/**
	 * The signature two phrases share when they target the same thing.
	 *
	 * @param string $phrase Raw keyphrase.
	 */
	public static function signature( string $phrase ): string {
		return implode( ' ', self::tokens( $phrase ) );
	}

	/**
	 * Published posts that share a focus keyphrase with another post.
	 *
	 * @param int $limit Maximum clashes to report.
	 *
	 * @return array<int, array{signature: string, posts: array<int, array{id: int, title: string, keyword: string}>}>
	 */
	public static function clashes( int $limit = 25 ): array {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, m.meta_value AS keyword
				FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s
					AND m.meta_value <> ''
					AND p.post_status = 'publish'
				ORDER BY p.ID ASC
				LIMIT 2000",
				Meta::FOCUS_KEYWORD
			)
		);

		$groups = array();

		foreach ( $rows as $row ) {
			$signature = self::signature( (string) $row->keyword );

			if ( '' === $signature ) {
				continue;
			}

			$groups[ $signature ][] = array(
				'id'      => (int) $row->ID,
				'title'   => (string) $row->post_title,
				'keyword' => (string) $row->keyword,
			);
		}

		$clashes = array();

		foreach ( $groups as $signature => $posts ) {
			if ( count( $posts ) < 2 ) {
				continue;
			}

			$clashes[] = array(
				'signature' => $signature,
				'posts'     => $posts,
			);

			if ( count( $clashes ) >= $limit ) {
				break;
			}
		}

		return $clashes;
	}

	/**
	 * What to do about a clash.
	 *
	 * Deliberately a list of options rather than one instruction: which is
	 * right depends on whether the pages serve different intents, and the
	 * plugin cannot know that.
	 *
	 * @return string[]
	 */
	public static function remedies(): array {
		return array(
			__( 'Merge the pages if they say much the same thing, and redirect the weaker URL to the stronger one.', 'wp-custom-seo' ),
			__( 'Give one page a different focus keyphrase if they genuinely answer different questions.', 'wp-custom-seo' ),
			__( 'Point a canonical URL from the secondary page at the primary one if both must stay.', 'wp-custom-seo' ),
			__( 'Differentiate the search intent — for example one explaining, one comparing.', 'wp-custom-seo' ),
		);
	}
}

<?php
/**
 * Content freshness review.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Audit;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Links\Links;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Meta;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Suggests pages that may be worth re-reading.
 *
 * **This does not detect decay.** Detecting decay needs traffic or ranking
 * data over time, and no such source is connected — so nothing here claims a
 * page is losing anything. What it does is surface pages that have not been
 * touched in a long time *and* still matter structurally, which is a prompt to
 * look, not a diagnosis.
 *
 * Age alone is deliberately not enough to qualify. Evergreen content that was
 * right five years ago is still right, and telling someone to rewrite it
 * because a date is old would be advice with nothing behind it. A page is only
 * raised when age is combined with a second signal: other pages link to it, or
 * it is substantial enough that being stale would matter.
 */
final class Decay {

	/**
	 * Pages that may be worth reviewing.
	 *
	 * @param int $limit Maximum rows.
	 *
	 * @return array<int, array{id: int, title: string, modified: string, months: int, incoming: int, words: int, reasons: string[]}>
	 */
	public static function candidates( int $limit = 25 ): array {
		$months    = max( 1, (int) Settings::get( 'decay_months', 12 ) );
		$timestamp = strtotime( '-' . $months . ' months' );
		$cutoff    = gmdate( 'Y-m-d H:i:s', false === $timestamp ? time() : $timestamp );

		$query = new WP_Query(
			array(
				'post_type'              => Meta::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'modified',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'no_found_rows'          => true,
				'date_query'             => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => $cutoff,
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed key, admin screen only, result cached by the auditor.
				'meta_query'             => Meta::exclude_noindex_clause(),
			)
		);

		if ( ! $query->posts ) {
			return array();
		}

		$ids      = wp_list_pluck( $query->posts, 'ID' );
		$incoming = Links::incoming_counts( array_map( 'intval', $ids ) );
		$rows     = array();

		foreach ( $query->posts as $post ) {
			$id      = (int) $post->ID;
			$words   = count( Analyzer::words( Analyzer::to_text( (string) $post->post_content ) ) );
			$links   = (int) ( $incoming[ $id ] ?? 0 );
			$age     = self::months_since( (string) $post->post_modified_gmt );
			$reasons = array();

			if ( $links > 0 ) {
				$reasons[] = sprintf(
					/* translators: %d: number of incoming links. */
					_n( '%d other page links to it', '%d other pages link to it', $links, 'wp-custom-seo' ),
					$links
				);
			}

			if ( $words >= 300 ) {
				$reasons[] = sprintf(
					/* translators: %d: word count. */
					__( 'it is a substantial page at %d words', 'wp-custom-seo' ),
					$words
				);
			}

			// Age on its own is not a reason to change anything.
			if ( ! $reasons ) {
				continue;
			}

			$rows[] = array(
				'id'       => $id,
				'title'    => (string) $post->post_title,
				'modified' => (string) $post->post_modified,
				'months'   => $age,
				'incoming' => $links,
				'words'    => $words,
				'reasons'  => $reasons,
			);
		}

		// Oldest first, then most-linked, so the pages that matter most rise.
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return ( $b['months'] * ( 1 + $b['incoming'] ) ) <=> ( $a['months'] * ( 1 + $a['incoming'] ) );
			}
		);

		return array_slice( $rows, 0, $limit );
	}

	/**
	 * Whole months between a date and now.
	 *
	 * @param string $gmt_date Date in `Y-m-d H:i:s` GMT.
	 */
	public static function months_since( string $gmt_date ): int {
		$timestamp = strtotime( $gmt_date . ' UTC' );

		if ( false === $timestamp ) {
			return 0;
		}

		return (int) floor( ( time() - $timestamp ) / ( 30 * DAY_IN_SECONDS ) );
	}
}

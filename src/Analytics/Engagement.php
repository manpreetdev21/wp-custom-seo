<?php
/**
 * What visitors did after they arrived.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Analytics;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The two Analytics questions this plugin has a use for.
 *
 * Not a general Analytics screen. The plugin's job is SEO, so it asks only what
 * bears on that: which pages organic search sends people to, and whether those
 * people stay. Everything else Analytics can answer is Analytics' own job, and
 * duplicating it here would be a worse version of a tool the site already has.
 */
final class Engagement {

	/**
	 * Landing pages for organic search traffic.
	 *
	 * Filtered to organic search rather than all traffic: a page that gets its
	 * visitors from a newsletter says nothing about how the site performs in
	 * search, and mixing the two produces a number that means neither.
	 *
	 * @param int  $days  Period in days.
	 * @param int  $limit Rows.
	 * @param bool $fresh Skip the cache.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function landing_pages( int $days = 28, int $limit = 25, bool $fresh = false ): array|WP_Error {
		$rows = Client::report(
			array( 'landingPage', 'sessionDefaultChannelGroup' ),
			array( 'sessions', 'engagedSessions', 'engagementRate', 'averageSessionDuration' ),
			$days,
			// Asked for generously, then filtered down to organic here, because
			// the channel is a dimension rather than something the request can
			// narrow without a filter expression per channel name.
			$limit * 4,
			$fresh
		);

		if ( $rows instanceof WP_Error ) {
			return $rows;
		}

		$organic = array();

		foreach ( $rows as $row ) {
			if ( 'Organic Search' !== ( $row['sessionDefaultChannelGroup'] ?? '' ) ) {
				continue;
			}

			$organic[] = array(
				'page'       => (string) ( $row['landingPage'] ?? '' ),
				'sessions'   => (int) ( $row['sessions'] ?? 0 ),
				'engaged'    => (int) ( $row['engagedSessions'] ?? 0 ),
				'engagement' => (float) ( $row['engagementRate'] ?? 0 ),
				'duration'   => (float) ( $row['averageSessionDuration'] ?? 0 ),
			);

			if ( count( $organic ) >= $limit ) {
				break;
			}
		}

		return $organic;
	}

	/**
	 * Totals for organic search over a period.
	 *
	 * @param int  $days  Period in days.
	 * @param bool $fresh Skip the cache.
	 *
	 * @return array{sessions: int, engaged: int, engagement: float}|WP_Error
	 */
	public static function totals( int $days = 28, bool $fresh = false ): array|WP_Error {
		$rows = Client::report(
			array( 'sessionDefaultChannelGroup' ),
			array( 'sessions', 'engagedSessions' ),
			$days,
			25,
			$fresh
		);

		if ( $rows instanceof WP_Error ) {
			return $rows;
		}

		foreach ( $rows as $row ) {
			if ( 'Organic Search' !== ( $row['sessionDefaultChannelGroup'] ?? '' ) ) {
				continue;
			}

			$sessions = (int) ( $row['sessions'] ?? 0 );
			$engaged  = (int) ( $row['engagedSessions'] ?? 0 );

			return array(
				'sessions'   => $sessions,
				'engaged'    => $engaged,
				// Recomputed from the two counts rather than read from the
				// API's own rate, so the three figures shown always agree.
				'engagement' => $sessions > 0 ? $engaged / $sessions : 0.0,
			);
		}

		// No organic row at all means Analytics reported no organic sessions —
		// which is a real answer, not a missing one.
		return array(
			'sessions'   => 0,
			'engaged'    => 0,
			'engagement' => 0.0,
		);
	}
}

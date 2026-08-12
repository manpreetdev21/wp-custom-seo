<?php
/**
 * Search performance reporting.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SearchConsole;

use WPCustomSeo\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Turns Search Console rows into the report the screens show.
 *
 * Every figure here came from Google. Nothing is modelled, interpolated or
 * filled in: a property with no data for a period produces an empty report and
 * says so, rather than a row of zeroes that looks like a measurement.
 *
 * The one derived number is the totals row, which is a sum of clicks and
 * impressions and a recomputed CTR — arithmetic over reported values, not an
 * estimate. Average position is deliberately **not** summed or averaged across
 * rows: the mean of per-query averages is not the site's average position, and
 * publishing it as though it were would be inventing a statistic.
 */
final class Performance {

	/**
	 * Settings key holding the chosen property.
	 */
	public const PROPERTY = 'gsc_property';

	/**
	 * Periods offered, in days.
	 */
	public const PERIODS = array( 7, 28, 90 );

	/**
	 * Search Console data lags behind live traffic by two to three days, so a
	 * range ending today is mostly empty and reads as a collapse in traffic.
	 */
	private const LAG_DAYS = 3;

	/**
	 * The property this site reports on.
	 */
	public static function property(): string {
		return (string) Settings::get( self::PROPERTY, '' );
	}

	/**
	 * Start and end dates for a period.
	 *
	 * @param int $days How many days the period covers.
	 *
	 * @return array{start: string, end: string}
	 */
	public static function range( int $days ): array {
		$end = time() - ( self::LAG_DAYS * DAY_IN_SECONDS );

		return array(
			'start' => gmdate( 'Y-m-d', $end - ( max( 1, $days ) * DAY_IN_SECONDS ) ),
			'end'   => gmdate( 'Y-m-d', $end ),
		);
	}

	/**
	 * The report for a period.
	 *
	 * @param int  $days  Period length.
	 * @param int  $limit Rows per table.
	 * @param bool $fresh Skip the cache.
	 *
	 * @return array{range: array{start: string, end: string}, totals: array<string, float>, queries: array<int, array<string, mixed>>, pages: array<int, array<string, mixed>>}|WP_Error
	 */
	public static function report( int $days = 28, int $limit = 25, bool $fresh = false ): array|WP_Error {
		$property = self::property();
		$range    = self::range( $days );

		$queries = Client::query( $property, array( 'query' ), $range['start'], $range['end'], $limit, array(), $fresh );

		if ( $queries instanceof WP_Error ) {
			return $queries;
		}

		$pages = Client::query( $property, array( 'page' ), $range['start'], $range['end'], $limit, array(), $fresh );

		if ( $pages instanceof WP_Error ) {
			return $pages;
		}

		return array(
			'range'   => $range,
			// Totals come from the page breakdown: every impression belongs to
			// a page, whereas the query breakdown omits rare queries for
			// privacy and would undercount.
			'totals'  => self::totals( $pages ),
			'queries' => self::rows( $queries ),
			'pages'   => self::rows( $pages ),
		);
	}

	/**
	 * What Search Console reports for one URL.
	 *
	 * @param string $url   Page URL, as it appears in the index.
	 * @param int    $days  Period length.
	 * @param int    $limit Rows.
	 * @param bool   $fresh Skip the cache.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function for_url( string $url, int $days = 28, int $limit = 10, bool $fresh = false ): array|WP_Error {
		$range = self::range( $days );

		$rows = Client::query(
			self::property(),
			array( 'query' ),
			$range['start'],
			$range['end'],
			$limit,
			array(
				array(
					'dimension'  => 'page',
					'operator'   => 'equals',
					'expression' => $url,
				),
			),
			$fresh
		);

		return $rows instanceof WP_Error ? $rows : self::rows( $rows );
	}

	/**
	 * Flatten API rows for display.
	 *
	 * @param array<int, array<string, mixed>> $rows API rows.
	 *
	 * @return array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>
	 */
	private static function rows( array $rows ): array {
		$out = array();

		foreach ( $rows as $row ) {
			$out[] = array(
				'key'         => (string) ( $row['keys'][0] ?? '' ),
				// Clicks and impressions are whole events; the API sends them
				// as floats and rounding them here stops "12.0 clicks".
				'clicks'      => (int) round( (float) $row['clicks'] ),
				'impressions' => (int) round( (float) $row['impressions'] ),
				'ctr'         => (float) $row['ctr'],
				'position'    => (float) $row['position'],
			);
		}

		return $out;
	}

	/**
	 * Totals across rows.
	 *
	 * @param array<int, array<string, mixed>> $rows API rows.
	 *
	 * @return array{clicks: int, impressions: int, ctr: float}
	 */
	private static function totals( array $rows ): array {
		$clicks      = 0;
		$impressions = 0;

		foreach ( $rows as $row ) {
			$clicks      += (int) round( (float) $row['clicks'] );
			$impressions += (int) round( (float) $row['impressions'] );
		}

		return array(
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'ctr'         => $impressions > 0 ? $clicks / $impressions : 0.0,
		);
	}
}

<?php
/**
 * The periodic report.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Reports;

use WPCustomSeo\Audit\Auditor;
use WPCustomSeo\Audit\Finding;
use WPCustomSeo\Redirects\NotFound;
use WPCustomSeo\SearchConsole\Account;
use WPCustomSeo\SearchConsole\Performance;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Gathers what a site owner should know since the last report.
 *
 * Nothing here is new analysis. The audit and the Search Console figures both
 * already exist; this assembles them into something that arrives without
 * anyone remembering to look, which is the entire point of a report.
 *
 * **A section that has no data is left out, not filled with zeroes.** A site
 * with no Search Console connection gets a report with no search section — not
 * one reporting nought clicks, which would read as a catastrophe rather than
 * an absence.
 */
final class Report {

	/**
	 * How many rows of each table to include.
	 *
	 * An email is a summary. Someone who wants the twenty-fifth most-clicked
	 * query wants the screen, not a longer email.
	 */
	private const ROWS = 5;

	/**
	 * Build the report.
	 *
	 * @param bool $fresh Rebuild the audit rather than reusing the cached one.
	 *
	 * @return array<string, mixed>
	 */
	public static function build( bool $fresh = true ): array {
		$report = array(
			'site'      => (string) get_bloginfo( 'name' ),
			'url'       => home_url( '/' ),
			'generated' => current_time( 'mysql' ),
			'audit'     => self::audit( $fresh ),
			'search'    => self::search(),
			'not_found' => self::not_found(),
		);

		/**
		 * Filters the assembled report before it is rendered.
		 *
		 * @param array<string, mixed> $report Report sections.
		 */
		return (array) apply_filters( 'wpcseo_report', $report );
	}

	/**
	 * Whether a report is worth sending at all.
	 *
	 * A site with nothing to say produces no email. Sending "no issues found"
	 * every week is how a report becomes something people filter to a folder,
	 * and then the one that matters is filtered too.
	 *
	 * @param array<string, mixed> $report Assembled report.
	 */
	public static function is_worth_sending( array $report ): bool {
		$audit = (array) $report['audit'];

		$actionable = (int) ( $audit['totals'][ Finding::CRITICAL ] ?? 0 )
			+ (int) ( $audit['totals'][ Finding::IMPORTANT ] ?? 0 )
			+ (int) ( $audit['totals'][ Finding::OPPORTUNITY ] ?? 0 );

		/**
		 * Filters whether a report has enough in it to send.
		 *
		 * @param bool                 $worth  Whether to send.
		 * @param array<string, mixed> $report Assembled report.
		 */
		return (bool) apply_filters(
			'wpcseo_report_worth_sending',
			$actionable > 0 || null !== $report['search'] || (int) $report['not_found'] > 0,
			$report
		);
	}

	/**
	 * The audit section.
	 *
	 * @param bool $fresh Rebuild rather than reusing the cache.
	 *
	 * @return array{totals: array<string, int>, findings: array<int, array<string, mixed>>}
	 */
	private static function audit( bool $fresh ): array {
		$audit    = Auditor::report( $fresh );
		$findings = array();

		foreach ( $audit['findings'] as $finding ) {
			// "Good" findings say a thing is fine. They belong on the screen,
			// where the whole picture is useful; in an email they are padding
			// around the things that need doing.
			if ( Finding::GOOD === $finding->level ) {
				continue;
			}

			$findings[] = array(
				'level'  => $finding->level,
				'title'  => $finding->title,
				'action' => $finding->action,
				'count'  => $finding->count,
			);
		}

		return array(
			'totals'   => $audit['totals'],
			'findings' => $findings,
		);
	}

	/**
	 * The search performance section, or null when there is none.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function search(): ?array {
		if ( ! Account::is_connected() || '' === Performance::property() ) {
			return null;
		}

		$performance = Performance::report( 28, self::ROWS );

		if ( $performance instanceof WP_Error ) {
			// A failing API is not something to report as a change in traffic.
			// The section is dropped and the failure stays on the screen, where
			// there is somewhere to act on it.
			return null;
		}

		if ( 0 === (int) $performance['totals']['impressions'] ) {
			return null;
		}

		return array(
			'range'   => $performance['range'],
			'totals'  => $performance['totals'],
			'queries' => array_slice( $performance['queries'], 0, self::ROWS ),
			'pages'   => array_slice( $performance['pages'], 0, self::ROWS ),
		);
	}

	/**
	 * How many distinct URLs have been logged as missing.
	 */
	private static function not_found(): int {
		return NotFound::count();
	}
}

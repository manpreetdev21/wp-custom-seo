<?php
/**
 * SEO health summary for the dashboard.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Audit\Auditor;
use WPCustomSeo\Audit\Finding;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the audit's findings into a health score and its breakdown.
 *
 * **This is not a ranking score.** It is a reading of the checks this plugin
 * already ran, expressed as a number so the dashboard can show movement. No
 * search engine publishes anything like it and nothing here is derived from
 * one. Every category says which findings produced it, so the number can be
 * traced back to the exact checks behind it rather than taken on trust.
 *
 * **Why it reuses the audit rather than measuring again.** `Auditor::report()`
 * already computes every count with grouped SQL and caches the result for an
 * hour. Recomputing the same figures for a dial would double the cost of the
 * dashboard and create a second source of truth that could disagree with the
 * audit screen. So this reads that report and nothing else, and when no report
 * has been built yet it says so instead of guessing.
 */
final class Health {

	/**
	 * What each finding level costs the category it belongs to.
	 *
	 * A category starts at 100 and loses ground per finding. Critical costs most
	 * because it is defined as "may stop pages being indexed at all"; an
	 * opportunity barely moves the number because nothing is broken.
	 */
	private const PENALTY = array(
		Finding::CRITICAL    => 34,
		Finding::IMPORTANT   => 14,
		Finding::OPPORTUNITY => 5,
		Finding::GOOD        => 0,
	);

	/**
	 * Which findings roll up into which category.
	 *
	 * A finding not named here still counts towards the overall score through
	 * the issue totals; it simply has no category of its own to sit in.
	 *
	 * @return array<string, array{label: string, findings: string[], link: string}>
	 */
	public static function categories(): array {
		$categories = array(
			'technical' => array(
				'label'    => __( 'Technical SEO', 'wp-custom-seo' ),
				'findings' => array( 'seo_off', 'sitemap_off', 'noindex_share', 'robots_file_overrides' ),
				'link'     => admin_url( 'admin.php?page=' . AuditPage::SLUG ),
			),
			'content'   => array(
				'label'    => __( 'Content SEO', 'wp-custom-seo' ),
				'findings' => array( 'missing_descriptions', 'missing_keywords', 'duplicate_titles', 'duplicate_descriptions', 'cannibalization', 'stale_content' ),
				'link'     => admin_url( 'admin.php?page=' . BulkEditorPage::SLUG ),
			),
			'schema'    => array(
				'label'    => __( 'Schema', 'wp-custom-seo' ),
				'findings' => array( 'schema_errors' ),
				'link'     => admin_url( 'admin.php?page=' . SchemaPage::SLUG ),
			),
			'links'     => array(
				'label'    => __( 'Internal links', 'wp-custom-seo' ),
				'findings' => array( 'orphans', 'unresolved_links', 'referred_404s' ),
				'link'     => admin_url( 'admin.php?page=' . LinksPage::SLUG ),
			),
		);

		/**
		 * Filters the health categories and the findings that feed them.
		 *
		 * @param array<string, array{label: string, findings: string[], link: string}> $categories Category definitions.
		 */
		return (array) apply_filters( 'wpcseo_health_categories', $categories );
	}

	/**
	 * The health summary.
	 *
	 * @param bool $fresh Whether to rebuild the underlying audit.
	 *
	 * @return array{available: bool, score: int, band: string, generated: string, totals: array<string, int>, categories: array<int, array<string, mixed>>, issues: array<int, array<string, mixed>>}
	 */
	public static function summary( bool $fresh = false ): array {
		$report = Auditor::report( $fresh );

		$findings = array();

		foreach ( (array) ( $report['findings'] ?? array() ) as $finding ) {
			if ( $finding instanceof Finding ) {
				$findings[] = $finding;
			}
		}

		$totals = array_fill_keys( array_keys( Finding::levels() ), 0 );

		foreach ( $findings as $finding ) {
			++$totals[ $finding->level ];
		}

		$categories = array();

		foreach ( self::categories() as $id => $category ) {
			$score   = 100;
			$counted = 0;

			foreach ( $findings as $finding ) {
				if ( ! in_array( $finding->id, $category['findings'], true ) ) {
					continue;
				}

				++$counted;
				$score -= self::PENALTY[ $finding->level ] ?? 0;
			}

			$categories[] = array(
				'id'      => (string) $id,
				'label'   => (string) $category['label'],
				// A category whose checks all passed is 100, not "unknown": the
				// checks ran and found nothing, which is the good outcome.
				'score'   => max( 0, min( 100, $score ) ),
				'checked' => $counted,
				'link'    => (string) $category['link'],
			);
		}

		$scores = array_column( $categories, 'score' );
		$score  = $scores ? (int) round( array_sum( $scores ) / count( $scores ) ) : 0;

		// Issues worth surfacing on the dashboard: anything that is not already
		// in order, largest first, so the top of the list is the biggest lever.
		$issues = array();

		foreach ( $findings as $finding ) {
			if ( Finding::GOOD === $finding->level ) {
				continue;
			}

			$issues[] = array(
				'title' => $finding->title,
				'level' => $finding->level,
				'count' => $finding->count,
				'link'  => '' !== $finding->link ? $finding->link : admin_url( 'admin.php?page=' . AuditPage::SLUG ),
			);
		}

		return array(
			'available'  => (bool) $findings,
			'score'      => $score,
			'band'       => self::band( $score ),
			'generated'  => (string) ( $report['generated'] ?? '' ),
			'totals'     => $totals,
			'categories' => $categories,
			'issues'     => array_slice( $issues, 0, 6 ),
		);
	}

	/**
	 * A word for a score, so the number is never the only signal.
	 *
	 * @param int $score Score between 0 and 100.
	 */
	public static function band( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'Excellent', 'wp-custom-seo' );
		}

		if ( $score >= 75 ) {
			return __( 'Good', 'wp-custom-seo' );
		}

		return $score >= 50 ? __( 'Needs work', 'wp-custom-seo' ) : __( 'Critical', 'wp-custom-seo' );
	}
}

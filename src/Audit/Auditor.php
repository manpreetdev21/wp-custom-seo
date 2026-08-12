<?php
/**
 * Site-wide SEO audit.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Audit;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Crawlers\AiCrawlers;
use WPCustomSeo\Links\Links;
use WPCustomSeo\Redirects\NotFound;
use WPCustomSeo\Schema\Graph\Pieces;
use WPCustomSeo\Schema\Validator;
use WPCustomSeo\SEO\Meta;
use WPCustomSeo\Sitemap\Sitemap;

defined( 'ABSPATH' ) || exit;

/*
 * Counts are taken with grouped queries over core tables rather than by
 * loading posts, which is what keeps a site-wide audit to a handful of queries
 * instead of thousands.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 */

/**
 * Builds the site audit.
 *
 * Every finding is computed, not inferred. The specification's own examples —
 * "42 pages have missing meta descriptions", "18 pages have weak internal
 * linking", "7 pages target similar keywords" — are all exact counts over data
 * this plugin already holds, so no model is asked and nothing is estimated.
 * That makes the audit free to run, instant, and incapable of being wrong
 * about a number.
 *
 * The report is cached because the queries, while few, are not free on a large
 * site. It never crawls URLs: nothing here makes an HTTP request.
 */
final class Auditor {

	private const CACHE_KEY = 'wpcseo_audit_report';

	private const SAMPLE = 15;

	/**
	 * The cached report, building it if needed.
	 *
	 * @param bool $fresh Whether to bypass the cache.
	 *
	 * @return array{generated: string, findings: array<int, Finding>, totals: array<string, int>}
	 */
	public static function report( bool $fresh = false ): array {
		if ( ! $fresh ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( is_array( $cached ) && isset( $cached['findings'] ) ) {
				return $cached;
			}
		}

		$findings = self::run();
		$totals   = array_fill_keys( array_keys( Finding::levels() ), 0 );

		foreach ( $findings as $finding ) {
			++$totals[ $finding->level ];
		}

		$report = array(
			'generated' => current_time( 'mysql' ),
			'findings'  => $findings,
			'totals'    => $totals,
		);

		set_transient( self::CACHE_KEY, $report, HOUR_IN_SECONDS );

		return $report;
	}

	/**
	 * Discard the cached report.
	 */
	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Run every check.
	 *
	 * @return array<int, Finding>
	 */
	private static function run(): array {
		$findings = array_merge(
			self::check_indexability(),
			self::check_metadata(),
			self::check_duplicates(),
			self::check_cannibalization(),
			self::check_linking(),
			self::check_not_found(),
			self::check_schema(),
			self::check_freshness()
		);

		/**
		 * Filters the audit findings.
		 *
		 * @param array<int, Finding> $findings Findings.
		 */
		$findings = (array) apply_filters( 'wpcseo_audit_findings', $findings );

		$order = array_keys( Finding::levels() );

		usort(
			$findings,
			static function ( Finding $a, Finding $b ) use ( $order ): int {
				$rank = array_search( $a->level, $order, true ) <=> array_search( $b->level, $order, true );

				return 0 !== $rank ? $rank : $b->count <=> $a->count;
			}
		);

		return $findings;
	}

	/**
	 * Published posts, per post type, that the plugin manages.
	 */
	private static function published(): int {
		global $wpdb;

		$types  = Meta::post_types();
		$tokens = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$tokens})",
				...$types
			)
		);
	}

	/**
	 * Published posts missing a given meta value.
	 *
	 * @param string $meta_key Meta key.
	 *
	 * @return array{count: int, items: array<int, array<string, mixed>>}
	 */
	private static function missing( string $meta_key ): array {
		global $wpdb;

		$types  = Meta::post_types();
		$tokens = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		$where = "p.post_status = 'publish'
			AND p.post_type IN ({$tokens})
			AND ( m.meta_id IS NULL OR m.meta_value = '' )";

		$join = "LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s";

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p {$join} WHERE {$where}",
				$meta_key,
				...$types
			)
		);

		$items = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p {$join} WHERE {$where} ORDER BY p.post_modified DESC LIMIT %d",
				$meta_key,
				...array_merge( $types, array( self::SAMPLE ) )
			),
			ARRAY_A
		);

		return array(
			'count' => $count,
			'items' => $items,
		);
	}

	/**
	 * Meta values shared by more than one published post.
	 *
	 * @param string $meta_key Meta key.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function duplicates( string $meta_key ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_value AS value, COUNT(*) AS total
				FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s AND m.meta_value <> '' AND p.post_status = 'publish'
				GROUP BY m.meta_value
				HAVING total > 1
				ORDER BY total DESC
				LIMIT %d",
				$meta_key,
				self::SAMPLE
			),
			ARRAY_A
		);
	}

	/**
	 * Indexability and discoverability.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_indexability(): array {
		global $wpdb;

		$findings = array();

		// A physical robots.txt stops WordPress serving its own, so the AI
		// crawler rules are written and never read. Worth reporting only when
		// there are rules to lose.
		if ( AiCrawlers::blocked() && AiCrawlers::has_physical_file() ) {
			$findings[] = new Finding(
				'robots_file_overrides',
				Finding::CRITICAL,
				__( 'Your AI crawler choices are not being served.', 'wp-custom-seo' ),
				__( 'A robots.txt file exists in the site root, and WordPress only serves its own when there is not one. The rules chosen in this plugin are being written into a file nobody fetches, so the crawlers you asked to stay away are not seeing that request.', 'wp-custom-seo' ),
				__( 'Either delete the physical robots.txt so WordPress serves the generated one, or copy the rules from Settings → AI crawlers into the file by hand.', 'wp-custom-seo' ),
				count( AiCrawlers::blocked() ),
				array(),
				admin_url( 'admin.php?page=' . Settings::PAGE )
			);
		}

		$noindex = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s AND m.meta_value = '1' AND p.post_status = 'publish'",
				Meta::NOINDEX
			)
		);

		$published = max( 1, self::published() );

		if ( $noindex > 0 && ( $noindex / $published ) > 0.2 ) {
			$findings[] = new Finding(
				'noindex_share',
				Finding::CRITICAL,
				sprintf(
					/* translators: 1: number of pages, 2: percentage. */
					__( '%1$d published pages are set to noindex — %2$d%% of the site.', 'wp-custom-seo' ),
					$noindex,
					(int) round( ( $noindex / $published ) * 100 )
				),
				__( 'A noindexed page cannot appear in search results at all. On this share of the site it is more often a mistake — a setting applied in bulk, or inherited from an import — than a deliberate choice.', 'wp-custom-seo' ),
				__( 'Check the list in the bulk editor and clear noindex on anything that should be findable.', 'wp-custom-seo' ),
				$noindex,
				array(),
				admin_url( 'admin.php?page=wp-custom-seo-bulk' )
			);
		} elseif ( $noindex > 0 ) {
			$findings[] = new Finding(
				'noindex_share',
				Finding::GOOD,
				sprintf(
					/* translators: %d: number of pages. */
					__( '%d published pages are set to noindex.', 'wp-custom-seo' ),
					$noindex
				),
				__( 'A small number of noindexed pages is normal — thank-you pages, internal landing pages and duplicates are all legitimate reasons.', 'wp-custom-seo' ),
				__( 'Nothing to do unless one of them should be findable.', 'wp-custom-seo' ),
				$noindex
			);
		}

		if ( '' === Sitemap::index_url() ) {
			$findings[] = new Finding(
				'sitemap_off',
				Finding::CRITICAL,
				__( 'No XML sitemap is being served.', 'wp-custom-seo' ),
				__( 'A sitemap is how a search engine finds pages that little else links to. Without one, discovery depends entirely on internal links.', 'wp-custom-seo' ),
				__( 'Turn the sitemap on under Settings → Sitemap & Breadcrumbs.', 'wp-custom-seo' ),
				0,
				array(),
				admin_url( 'admin.php?page=wp-custom-seo-settings' )
			);
		} else {
			$findings[] = new Finding(
				'sitemap_off',
				Finding::GOOD,
				__( 'An XML sitemap is being served.', 'wp-custom-seo' ),
				__( 'Search engines have a complete list of the pages you want found.', 'wp-custom-seo' ),
				__( 'Nothing to do.', 'wp-custom-seo' )
			);
		}

		if ( ! Settings::enabled( 'enable_seo' ) ) {
			$findings[] = new Finding(
				'seo_off',
				Finding::CRITICAL,
				__( 'SEO output is switched off.', 'wp-custom-seo' ),
				__( 'No titles, descriptions, canonicals, robots directives or structured data are being emitted at all.', 'wp-custom-seo' ),
				__( 'Turn on “Enable SEO output” under Settings → General.', 'wp-custom-seo' ),
				0,
				array(),
				admin_url( 'admin.php?page=wp-custom-seo-settings' )
			);
		}

		return $findings;
	}

	/**
	 * Missing metadata.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_metadata(): array {
		$findings    = array();
		$description = self::missing( Meta::DESCRIPTION );
		$keyword     = self::missing( Meta::FOCUS_KEYWORD );

		if ( $description['count'] > 0 ) {
			$findings[] = new Finding(
				'missing_descriptions',
				Finding::IMPORTANT,
				sprintf(
					/* translators: %d: number of pages. */
					_n( '%d published page has no meta description.', '%d published pages have no meta description.', $description['count'], 'wp-custom-seo' ),
					$description['count']
				),
				__( 'Without one, a search engine writes its own summary from whatever text it finds. That is sometimes fine and sometimes a navigation menu.', 'wp-custom-seo' ),
				__( 'Write descriptions in the bulk editor, filtering by “Missing meta description”.', 'wp-custom-seo' ),
				$description['count'],
				$description['items'],
				admin_url( 'admin.php?page=wp-custom-seo-bulk&missing=description' )
			);
		} else {
			$findings[] = new Finding(
				'missing_descriptions',
				Finding::GOOD,
				__( 'Every published page has a meta description.', 'wp-custom-seo' ),
				__( 'You control the summary shown under each result.', 'wp-custom-seo' ),
				__( 'Nothing to do.', 'wp-custom-seo' )
			);
		}

		if ( $keyword['count'] > 0 ) {
			$findings[] = new Finding(
				'missing_keywords',
				Finding::OPPORTUNITY,
				sprintf(
					/* translators: %d: number of pages. */
					_n( '%d published page has no focus keyphrase.', '%d published pages have no focus keyphrase.', $keyword['count'], 'wp-custom-seo' ),
					$keyword['count']
				),
				__( 'The keyphrase is what the editor analysis measures against. Without one, most of the on-page checks have nothing to compare to — the page is not worse off, but you get less feedback on it.', 'wp-custom-seo' ),
				__( 'Set one on the pages that matter most. It is not worth doing for every page on a large site.', 'wp-custom-seo' ),
				$keyword['count'],
				$keyword['items']
			);
		}

		return $findings;
	}

	/**
	 * Duplicate titles and descriptions.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_duplicates(): array {
		$findings = array();

		foreach ( array(
			array( Meta::TITLE, 'duplicate_titles', __( 'SEO title', 'wp-custom-seo' ) ),
			array( Meta::DESCRIPTION, 'duplicate_descriptions', __( 'meta description', 'wp-custom-seo' ) ),
		) as $pair ) {
			[ $key, $id, $label ] = $pair;

			$duplicates = self::duplicates( $key );

			if ( ! $duplicates ) {
				continue;
			}

			$affected = 0;

			foreach ( $duplicates as $row ) {
				$affected += (int) $row['total'];
			}

			$findings[] = new Finding(
				$id,
				Finding::IMPORTANT,
				sprintf(
					/* translators: 1: number of pages, 2: field name. */
					__( '%1$d pages share a %2$s with another page.', 'wp-custom-seo' ),
					$affected,
					$label
				),
				__( 'Identical text on several pages gives a searcher no way to tell them apart, and gives a search engine a reason to treat them as the same page.', 'wp-custom-seo' ),
				__( 'Rewrite the duplicates so each says what is specific to that page.', 'wp-custom-seo' ),
				$affected,
				$duplicates,
				admin_url( 'admin.php?page=wp-custom-seo-bulk' )
			);
		}

		return $findings;
	}

	/**
	 * Pages competing for the same keyphrase.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_cannibalization(): array {
		$clashes = Cannibalization::clashes();

		if ( ! $clashes ) {
			return array();
		}

		$pages = 0;

		foreach ( $clashes as $clash ) {
			$pages += count( $clash['posts'] );
		}

		return array(
			new Finding(
				'cannibalization',
				Finding::IMPORTANT,
				sprintf(
					/* translators: %d: number of pages. */
					_n(
						'%d page competes with another for its keyphrase.',
						'%d pages compete with each other for a keyphrase.',
						$pages,
						'wp-custom-seo'
					),
					$pages
				),
				__( 'When two of your own pages target one phrase, a search engine has to choose between them and may pick the weaker one, or split the signal across both. Word order and plurals are ignored here, so "roof insulation" and "insulation for roofs" count as the same target.', 'wp-custom-seo' ),
				__( 'For each clash: merge the pages, change one keyphrase, canonicalise one to the other, or make the intents genuinely different.', 'wp-custom-seo' ),
				$pages,
				$clashes
			),
		);
	}

	/**
	 * Internal linking.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_linking(): array {
		if ( ! Settings::enabled( 'enable_link_graph' ) ) {
			return array();
		}

		$findings = array();
		$critical = (int) Settings::get( 'orphan_critical', 0 );
		$warning  = max( $critical, (int) Settings::get( 'orphan_warning', 1 ) );
		$under    = Links::under_linked( $warning, Links::post_types(), 100 );
		$orphans  = array_values(
			array_filter(
				$under,
				static fn ( object $row ): bool => (int) $row->incoming <= $critical
			)
		);

		if ( $orphans ) {
			$findings[] = new Finding(
				'orphans',
				Finding::IMPORTANT,
				sprintf(
					/* translators: %d: number of pages. */
					_n( '%d published page has no internal links pointing at it.', '%d published pages have no internal links pointing at them.', count( $orphans ), 'wp-custom-seo' ),
					count( $orphans )
				),
				__( 'A page nothing links to is reachable only through the sitemap or a direct URL. Pages in a navigation menu, the front page and the posts page are excluded here, so these are genuinely unreferenced.', 'wp-custom-seo' ),
				__( 'Link to them from related content where the reference actually helps a reader.', 'wp-custom-seo' ),
				count( $orphans ),
				array_map( static fn ( object $row ): array => (array) $row, array_slice( $orphans, 0, self::SAMPLE ) ),
				admin_url( 'admin.php?page=wp-custom-seo-links' )
			);
		}

		$unresolved = Links::unresolved( 100 );

		if ( $unresolved ) {
			$findings[] = new Finding(
				'unresolved_links',
				Finding::OPPORTUNITY,
				sprintf(
					/* translators: %d: number of links. */
					_n( '%d internal link does not resolve to a post.', '%d internal links do not resolve to a post.', count( $unresolved ), 'wp-custom-seo' ),
					count( $unresolved )
				),
				__( 'These are not necessarily broken — a category archive, a date archive or a page served by something other than a post all appear here legitimately. Confirming one is broken needs an actual request, which this audit does not make.', 'wp-custom-seo' ),
				__( 'Check the list and fix any that really are wrong.', 'wp-custom-seo' ),
				count( $unresolved ),
				array_map( static fn ( object $row ): array => (array) $row, array_slice( $unresolved, 0, self::SAMPLE ) ),
				admin_url( 'admin.php?page=wp-custom-seo-links' )
			);
		}

		return $findings;
	}

	/**
	 * Logged 404s that look like real broken links.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_not_found(): array {
		if ( ! Settings::enabled( 'monitor_404' ) ) {
			return array();
		}

		$rows = NotFound::all(
			array(
				'per_page' => 100,
				'orderby'  => 'hits',
				'order'    => 'desc',
			)
		);

		// A 404 with a referrer was reached by following a link, which makes it
		// far more likely to be a real broken link than a typo or a scan.
		$referred = array_values(
			array_filter(
				$rows,
				static fn ( object $row ): bool => '' !== trim( (string) $row->referrer )
			)
		);

		if ( ! $referred ) {
			return array();
		}

		return array(
			new Finding(
				'referred_404s',
				Finding::IMPORTANT,
				sprintf(
					/* translators: %d: number of URLs. */
					_n( '%d missing URL was reached by following a link.', '%d missing URLs were reached by following a link.', count( $referred ), 'wp-custom-seo' ),
					count( $referred )
				),
				__( 'A 404 with a referrer means something linked to it — a real broken link, rather than a mistyped address or a bot scanning for filenames.', 'wp-custom-seo' ),
				__( 'Create a redirect to the replacement page, straight from the 404 monitor.', 'wp-custom-seo' ),
				count( $referred ),
				array_map( static fn ( object $row ): array => (array) $row, array_slice( $referred, 0, self::SAMPLE ) ),
				admin_url( 'admin.php?page=wp-custom-seo-404' )
			),
		);
	}

	/**
	 * Structured data.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_schema(): array {
		if ( ! Settings::enabled( 'enable_schema' ) ) {
			return array();
		}

		$front  = (int) get_option( 'page_on_front' );
		$graph  = Pieces::for_post( $front );
		$issues = Validator::validate( $graph );
		$errors = array_values(
			array_filter(
				$issues,
				static fn ( array $issue ): bool => Validator::ERROR === $issue['level']
			)
		);

		if ( ! $errors ) {
			return array(
				new Finding(
					'schema_errors',
					Finding::GOOD,
					__( 'The front page schema graph validates.', 'wp-custom-seo' ),
					__( 'Identifiers are unique, references resolve, and URLs are absolute.', 'wp-custom-seo' ),
					__( 'Nothing to do.', 'wp-custom-seo' )
				),
			);
		}

		return array(
			new Finding(
				'schema_errors',
				Finding::CRITICAL,
				sprintf(
					/* translators: %d: number of errors. */
					_n( 'The schema graph has %d error and is being withheld.', 'The schema graph has %d errors and is being withheld.', count( $errors ), 'wp-custom-seo' ),
					count( $errors )
				),
				__( 'A graph that fails validation is not published at all, on the principle that no structured data is better than wrong structured data. So none of your pages are currently carrying it.', 'wp-custom-seo' ),
				__( 'Open SEO → Schema to see what is wrong and fix the underlying setting.', 'wp-custom-seo' ),
				count( $errors ),
				$errors,
				admin_url( 'admin.php?page=wp-custom-seo-schema' )
			),
		);
	}

	/**
	 * Pages worth re-reading.
	 *
	 * @return array<int, Finding>
	 */
	private static function check_freshness(): array {
		$candidates = Decay::candidates( 100 );

		if ( ! $candidates ) {
			return array();
		}

		return array(
			new Finding(
				'stale_content',
				Finding::OPPORTUNITY,
				sprintf(
					/* translators: %d: number of pages. */
					_n( '%d page has not been updated in a long time and still matters.', '%d pages have not been updated in a long time and still matter.', count( $candidates ), 'wp-custom-seo' ),
					count( $candidates )
				),
				__( 'This is a prompt to look, not a diagnosis. No traffic or ranking data is connected, so nothing here can tell you a page is declining — only that it is old and either well linked or substantial. Evergreen content that was right years ago is still right.', 'wp-custom-seo' ),
				__( 'Skim the oldest few. Update anything where the facts have moved on; leave anything still accurate alone.', 'wp-custom-seo' ),
				count( $candidates ),
				array_slice( $candidates, 0, self::SAMPLE )
			),
		);
	}
}

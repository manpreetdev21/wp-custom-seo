<?php
/**
 * WP-CLI commands.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\CLI;

use WPCustomSeo\Audit\Auditor;
use WPCustomSeo\Audit\Finding;
use WPCustomSeo\Links\Scanner;
use WPCustomSeo\Schema\Cache;
use WPCustomSeo\Schema\Graph\Pieces;
use WPCustomSeo\Schema\Validator;
use WPCustomSeo\Transfer\Csv;
use WPCustomSeo\Transfer\Import;
use WPCustomSeo\Transfer\Sources;
use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the plugin's long jobs from a terminal.
 *
 * The reason these exist is not convenience. Three of them — the link rebuild,
 * the migration and a whole-site CSV import — are bounded in the admin by what
 * fits in one HTTP request, and are asked to continue by hand. Here they run to
 * completion in one go, which is what a developer moving a large site actually
 * needs.
 *
 * Nothing new is computed. Every command calls the same code the screens do, so
 * a number reported here and a number on a screen cannot disagree.
 *
 * Per-post meta is deliberately absent: `wp post meta get 12 _wpcseo_title`
 * already reads and writes these fields, and a second spelling of it would be
 * one more thing to keep correct.
 */
final class Command {

	/**
	 * Register with WP-CLI when running under it.
	 */
	public static function init(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'seo', self::class );
	}

	/**
	 * Runs the site audit and prints what it found.
	 *
	 * ## OPTIONS
	 *
	 * [--level=<level>]
	 * : Show only findings at this level.
	 * ---
	 * options:
	 *   - critical
	 *   - important
	 *   - opportunity
	 *   - good
	 * ---
	 *
	 * [--fresh]
	 * : Rebuild the report instead of reading the cached one.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp seo audit
	 *     wp seo audit --level=critical --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function audit( array $args, array $assoc_args ): void {
		$report = Auditor::report( isset( $assoc_args['fresh'] ) );
		$level  = (string) ( $assoc_args['level'] ?? '' );

		$rows = array();

		foreach ( $report['findings'] as $finding ) {
			if ( '' !== $level && $finding->level !== $level ) {
				continue;
			}

			$rows[] = array(
				'level'  => $finding->level,
				'id'     => $finding->id,
				'count'  => $finding->count,
				'title'  => $finding->title,
				'action' => $finding->action,
			);
		}

		if ( ! $rows ) {
			WP_CLI::success( 'Nothing to report at that level.' );

			return;
		}

		Utils\format_items( (string) ( $assoc_args['format'] ?? 'table' ), $rows, array( 'level', 'id', 'count', 'title', 'action' ) );

		// The exit status is deliberately zero even with critical findings. An
		// audit result is a judgement about content, not a build failure, and a
		// non-zero status here would break any pipeline it was put into.
		WP_CLI::log(
			sprintf(
				'%d critical, %d important, %d opportunities, %d good.',
				$report['totals'][ Finding::CRITICAL ],
				$report['totals'][ Finding::IMPORTANT ],
				$report['totals'][ Finding::OPPORTUNITY ],
				$report['totals'][ Finding::GOOD ]
			)
		);
	}

	/**
	 * Rebuilds the internal link graph.
	 *
	 * The admin runs this over cron, a batch every few seconds. Here it runs
	 * straight through.
	 *
	 * ## EXAMPLES
	 *
	 *     wp seo links
	 *
	 * @subcommand links
	 */
	public function links(): void {
		$total = Scanner::countable();

		if ( 0 === $total ) {
			WP_CLI::success( 'No published content to scan.' );

			return;
		}

		Scanner::start_rebuild();

		$progress = Utils\make_progress_bar( 'Scanning links', $total );
		$last     = 0;

		while ( Scanner::is_rebuilding() ) {
			Scanner::run_batch();

			$done = Scanner::progress()['done'];

			$progress->tick( max( 0, $done - $last ) );
			$last = $done;
		}

		$progress->finish();

		// The scanner queues a cron event after every batch so the admin can
		// carry on where it left off. Nothing is left to carry on from now.
		wp_clear_scheduled_hook( 'wpcseo_rebuild_links' );

		WP_CLI::success( sprintf( 'Scanned %d posts.', $total ) );
	}

	/**
	 * Prints the schema graph for a post and validates it.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post to describe.
	 *
	 * [--format=<format>]
	 * : Output format for the graph.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp seo schema 42
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function schema( array $args, array $assoc_args ): void {
		$post_id = (int) ( $args[0] ?? 0 );

		if ( ! get_post( $post_id ) ) {
			WP_CLI::error( sprintf( 'No post with id %d.', $post_id ) );
		}

		$graph  = Pieces::for_post( $post_id );
		$issues = Validator::validate( $graph );

		if ( 'yaml' === ( $assoc_args['format'] ?? 'json' ) ) {
			WP_CLI::log( Utils\format_items( 'yaml', $graph->nodes(), array_keys( $graph->nodes()[0] ?? array() ) ) );
		} else {
			WP_CLI::log( wp_json_encode( $graph->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}

		if ( ! $issues ) {
			WP_CLI::success( sprintf( '%d nodes, no issues.', count( $graph->nodes() ) ) );

			return;
		}

		foreach ( $issues as $issue ) {
			$line = sprintf( '%s: %s', $issue['node'], $issue['message'] );

			if ( Validator::ERROR === $issue['level'] ) {
				WP_CLI::warning( $line );
			} else {
				WP_CLI::log( '  ' . $line );
			}
		}

		if ( Validator::has_errors( $issues ) ) {
			// An error means the front end withholds this graph, so the command
			// fails: that is a broken page, not an observation about it.
			WP_CLI::error( 'The graph has errors and would not be output on the front end.' );
		}

		WP_CLI::success( sprintf( '%d nodes, %d warnings.', count( $graph->nodes() ), count( $issues ) ) );
	}

	/**
	 * Writes every post's SEO fields to a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Where to write. Defaults to standard output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp seo export --file=seo.csv
	 *     wp seo export > seo.csv
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function export( array $args, array $assoc_args ): void {
		$path = (string) ( $assoc_args['file'] ?? '' );

		$handle = '' === $path
			? fopen( 'php://stdout', 'w' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			: fopen( $path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			WP_CLI::error( sprintf( 'Could not write to %s.', $path ) );
		}

		$rows = Csv::write( $handle );

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( '' !== $path ) {
			WP_CLI::success( sprintf( 'Wrote %d rows to %s.', $rows, $path ) );
		}
	}

	/**
	 * Applies a CSV of SEO fields.
	 *
	 * Reports what would change and writes nothing unless --apply is given.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The CSV to read, in the format `wp seo export` produces.
	 *
	 * [--apply]
	 * : Write the changes. Without it this is a dry run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp seo import-csv seo.csv
	 *     wp seo import-csv seo.csv --apply
	 *
	 * @subcommand import-csv
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function import_csv( array $args, array $assoc_args ): void {
		$path = (string) ( $args[0] ?? '' );

		if ( ! is_readable( $path ) ) {
			WP_CLI::error( sprintf( 'Cannot read %s.', $path ) );
		}

		// WP-CLI runs as nobody unless told otherwise, and the importer checks
		// edit_post per row. Without this the command would skip every row and
		// report "0 changed", which reads like a clean run rather than a
		// missing flag.
		if ( 0 === get_current_user_id() ) {
			WP_CLI::error( 'This writes posts, so it needs a user: run it as `wp --user=<login-or-id> seo import-csv …`.' );
		}

		$parsed = Csv::read( $path );

		if ( '' !== $parsed['error'] ) {
			WP_CLI::error( $parsed['error'] );
		}

		$apply  = isset( $assoc_args['apply'] );
		$result = Csv::apply( $parsed['rows'], ! $apply );

		foreach ( $result['problems'] as $problem ) {
			WP_CLI::warning( $problem );
		}

		$summary = sprintf(
			'%d rows read. %d posts, %d fields; %d already matched.',
			$result['rows'],
			$result['posts'],
			$result['fields'],
			$result['unchanged']
		);

		if ( $apply ) {
			WP_CLI::success( $summary );

			return;
		}

		WP_CLI::log( $summary );
		WP_CLI::log( 'Dry run — nothing was changed. Pass --apply to write.' );
	}

	/**
	 * Copies SEO data in from another plugin.
	 *
	 * Runs to completion rather than a batch at a time. The source plugin's
	 * data is read and never changed.
	 *
	 * ## OPTIONS
	 *
	 * [<source>]
	 * : Which plugin to read. Omit to list what was found on this site.
	 *
	 * [--overwrite]
	 * : Replace values this plugin already holds.
	 *
	 * ## EXAMPLES
	 *
	 *     wp seo import
	 *     wp seo import yoast
	 *     wp seo import rankmath --overwrite
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function import( array $args, array $assoc_args ): void {
		$slug = (string) ( $args[0] ?? '' );

		if ( '' === $slug ) {
			$this->list_sources();

			return;
		}

		$source = Sources::get( $slug );

		if ( null === $source ) {
			WP_CLI::error( sprintf( 'Unknown source "%s". Run `wp seo import` to see what is available.', $slug ) );
		}

		$first = Import::run( $slug, 0, isset( $assoc_args['overwrite'] ) );

		if ( 0 === $first['total'] ) {
			WP_CLI::success( sprintf( 'No %s data found on this site.', (string) $source['label'] ) );

			return;
		}

		$progress = Utils\make_progress_bar( sprintf( 'Importing from %s', (string) $source['label'] ), $first['total'] );
		$progress->tick( $first['processed'] );

		$totals = $first;

		while ( ! $totals['done'] ) {
			$batch = Import::run( $slug, $totals['processed'], isset( $assoc_args['overwrite'] ) );

			if ( 0 === $batch['processed'] ) {
				// Nothing came back, so there is nothing left to read. Stopping
				// beats looping on an empty batch forever.
				break;
			}

			$totals['processed'] += $batch['processed'];
			$totals['posts']     += $batch['posts'];
			$totals['fields']    += $batch['fields'];
			$totals['skipped']   += $batch['skipped'];
			$totals['dropped']    = array_values( array_unique( array_merge( $totals['dropped'], $batch['dropped'] ) ) );
			$totals['done']       = $batch['done'];

			$progress->tick( $batch['processed'] );
		}

		$progress->finish();

		if ( $totals['dropped'] ) {
			WP_CLI::warning(
				sprintf(
					'These template variables have no equivalent here and were removed from the text they appeared in: %s',
					implode( ', ', array_map( static fn ( string $name ): string => '%%' . $name . '%%', $totals['dropped'] ) )
				)
			);
		}

		WP_CLI::success(
			sprintf(
				'%d posts updated, %d fields copied, %d left alone.',
				$totals['posts'],
				$totals['fields'],
				$totals['skipped']
			)
		);
	}

	/**
	 * Clears the aggregated schema cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp seo flush
	 */
	public function flush(): void {
		$removed = Cache::flush();

		Auditor::flush();

		WP_CLI::success( sprintf( 'Cleared %d cached schema entries and the audit report.', $removed ) );
	}

	/**
	 * Print what other SEO plugins left behind on this site.
	 */
	private function list_sources(): void {
		$counts = Sources::detect();
		$rows   = array();

		foreach ( Sources::all() as $slug => $source ) {
			$rows[] = array(
				'source' => $slug,
				'plugin' => (string) $source['label'],
				'posts'  => (int) ( $counts[ $slug ] ?? 0 ),
			);
		}

		Utils\format_items( 'table', $rows, array( 'source', 'plugin', 'posts' ) );

		if ( Sources::aioseo_present() ) {
			WP_CLI::warning( 'All in One SEO is present, but version 4 keeps its data in its own table rather than post meta. It cannot be imported.' );
		}
	}
}

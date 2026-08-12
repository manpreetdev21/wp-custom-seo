<?php
/**
 * Tools screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Reports\Mailer;
use WPCustomSeo\Reports\Report;
use WPCustomSeo\Reports\Schedule as ReportSchedule;
use WPCustomSeo\Schema\Aggregator;
use WPCustomSeo\Schema\Cache;
use WPCustomSeo\Transfer\Csv;
use WPCustomSeo\Transfer\Import;
use WPCustomSeo\Transfer\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Tools: maintenance, transfer and migration.
 */
final class ToolsPage {

	public const SLUG = 'wp-custom-seo-tools';

	private const CLEAR_ACTION = 'wpcseo_clear_schema_cache';

	private const EXPORT_ACTION = 'wpcseo_export_csv';

	private const IMPORT_ACTION = 'wpcseo_import_csv';

	private const MIGRATE_ACTION = 'wpcseo_migrate_seo';

	private const REPORT_ACTION = 'wpcseo_send_report_now';

	/**
	 * How long a report waits to be shown once.
	 */
	private const REPORT_TTL = 300;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( self::class, 'clear_cache' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( self::class, 'export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( self::class, 'import' ) );
		add_action( 'admin_post_' . self::MIGRATE_ACTION, array( self::class, 'migrate' ) );
		add_action( 'admin_post_' . self::REPORT_ACTION, array( self::class, 'send_report' ) );
	}

	/**
	 * Add the screen to the menu registry.
	 *
	 * @param array<string, array<string, mixed>> $pages Registered pages.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function register( array $pages ): array {
		$pages[ self::SLUG ] = array(
			'title'      => __( 'Tools', 'wp-custom-seo' ),
			'menu_title' => __( 'Tools', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Refuse anyone without the plugin capability.
	 *
	 * The nonce is checked by each handler rather than here, so that the check
	 * is visible in the same function as the request data it protects.
	 */
	private static function guard(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-custom-seo' ), 403 );
		}
	}

	/**
	 * Handle the clear-cache submission.
	 */
	public static function clear_cache(): void {
		self::guard();
		check_admin_referer( self::CLEAR_ACTION );

		$removed = Cache::flush();

		self::back( array( 'wpcseo_cleared' => $removed ) );
	}

	/**
	 * Build and send a report immediately.
	 *
	 * Unlike the scheduled send this does not check whether the report is worth
	 * sending: someone who has just pressed the button wants to see what
	 * arrives, including that it arrives at all.
	 */
	public static function send_report(): void {
		self::guard();
		check_admin_referer( self::REPORT_ACTION );

		$sent = Mailer::send( Report::build() );

		self::report(
			array(
				'kind' => 'email',
				'sent' => $sent,
				'to'   => implode( ', ', Mailer::recipients() ),
			)
		);
	}

	/**
	 * Stream every post's SEO fields as a CSV download.
	 */
	public static function export(): void {
		self::guard();
		check_admin_referer( self::EXPORT_ACTION );

		$name = sanitize_file_name( wp_parse_url( home_url(), PHP_URL_HOST ) . '-seo-' . gmdate( 'Y-m-d' ) . '.csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $name );

		$handle = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false !== $handle ) {
			// Excel reads a file without this as the system codepage, which
			// turns every accented character in a meta description to mojibake.
			fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

			Csv::write( $handle );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		exit;
	}

	/**
	 * Read an uploaded CSV, then either report on it or apply it.
	 */
	public static function import(): void {
		self::guard();
		check_admin_referer( self::IMPORT_ACTION );

		// The uploaded file is read from its temporary location and never moved
		// into the media library: it is a spreadsheet of instructions, not an
		// attachment, and there is no reason to keep it after the run.
		$tmp = isset( $_FILES['wpcseo_csv']['tmp_name'] )
			? sanitize_text_field( wp_unslash( $_FILES['wpcseo_csv']['tmp_name'] ) )
			: '';

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			self::report(
				array(
					'kind'  => 'import',
					'error' => __( 'No file arrived. It may have been larger than this server accepts.', 'wp-custom-seo' ),
				)
			);
		}

		$parsed = Csv::read( $tmp );

		if ( '' !== $parsed['error'] ) {
			self::report(
				array(
					'kind'  => 'import',
					'error' => $parsed['error'],
				)
			);
		}

		// The checkbox is deliberately opt-in: the default press reports what
		// would happen and changes nothing.
		$apply = isset( $_POST['wpcseo_apply'] ) && '1' === sanitize_key( wp_unslash( $_POST['wpcseo_apply'] ) );

		$result         = Csv::apply( $parsed['rows'], ! $apply );
		$result['kind'] = 'import';
		$result['dry']  = ! $apply;

		self::report( $result );
	}

	/**
	 * Copy one batch from another SEO plugin.
	 */
	public static function migrate(): void {
		self::guard();
		check_admin_referer( self::MIGRATE_ACTION );

		$slug = isset( $_POST['wpcseo_source'] ) ? sanitize_key( wp_unslash( $_POST['wpcseo_source'] ) ) : '';

		if ( null === Sources::get( $slug ) ) {
			self::report(
				array(
					'kind'  => 'migrate',
					'error' => __( 'That is not a source this plugin can read.', 'wp-custom-seo' ),
				)
			);
		}

		$offset    = isset( $_POST['wpcseo_offset'] ) ? absint( wp_unslash( $_POST['wpcseo_offset'] ) ) : 0;
		$overwrite = isset( $_POST['wpcseo_overwrite'] ) && '1' === sanitize_key( wp_unslash( $_POST['wpcseo_overwrite'] ) );

		$result = Import::run( $slug, $offset, $overwrite );

		// Totals accumulate across batches so the report describes the run, not
		// the last two hundred posts of it.
		$carried = array(
			'posts'   => isset( $_POST['wpcseo_posts'] ) ? absint( wp_unslash( $_POST['wpcseo_posts'] ) ) : 0,
			'fields'  => isset( $_POST['wpcseo_fields'] ) ? absint( wp_unslash( $_POST['wpcseo_fields'] ) ) : 0,
			'skipped' => isset( $_POST['wpcseo_skipped'] ) ? absint( wp_unslash( $_POST['wpcseo_skipped'] ) ) : 0,
		);

		self::report(
			array(
				'kind'      => 'migrate',
				'source'    => $slug,
				'label'     => (string) ( Sources::get( $slug )['label'] ?? $slug ),
				'processed' => $offset + $result['processed'],
				'total'     => $result['total'],
				'posts'     => $carried['posts'] + $result['posts'],
				'fields'    => $carried['fields'] + $result['fields'],
				'skipped'   => $carried['skipped'] + $result['skipped'],
				'dropped'   => $result['dropped'],
				'overwrite' => $overwrite,
				'done'      => $result['done'],
			)
		);
	}

	/**
	 * Stash a report for the next page load and go back to the screen.
	 *
	 * A report is too big for a query string and too transient for an option,
	 * so it lives for five minutes and is read once.
	 *
	 * @param array<string, mixed> $report Report data.
	 */
	private static function report( array $report ): void {
		set_transient( self::report_key(), $report, self::REPORT_TTL );

		self::back();
	}

	/**
	 * Transient key for the current user's report.
	 */
	private static function report_key(): string {
		return 'wpcseo_transfer_report_' . get_current_user_id();
	}

	/**
	 * Return to the screen.
	 *
	 * @param array<string, int|string> $args Extra query arguments.
	 */
	private static function back( array $args = array() ): void {
		wp_safe_redirect(
			add_query_arg(
				array_merge( array( 'page' => self::SLUG ), $args ),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		$report = get_transient( self::report_key() );

		if ( false !== $report ) {
			delete_transient( self::report_key() );
		}

		$vars = array(
			'action'     => self::CLEAR_ACTION,
			'export'     => self::EXPORT_ACTION,
			'import'     => self::IMPORT_ACTION,
			'migrate'    => self::MIGRATE_ACTION,
			'send'       => self::REPORT_ACTION,
			'recipients' => Mailer::recipients(),
			'next'       => ReportSchedule::next(),
			'post_types' => Aggregator::index()['post_types'],
			'sources'    => Sources::all(),
			'detected'   => Sources::detect(),
			'aioseo'     => Sources::aioseo_present(),
			'columns'    => Csv::header(),
			'report'     => is_array( $report ) ? $report : null,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only confirmation notice; the action itself is nonce checked.
			'cleared'    => isset( $_GET['wpcseo_cleared'] ) ? absint( wp_unslash( $_GET['wpcseo_cleared'] ) ) : null,
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/tools.php';
	}
}

<?php
/**
 * Rendering and sending the report.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Reports;

use WPCustomSeo\Audit\Finding;
use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a report into an email and sends it.
 *
 * Sent as plain text. An HTML email would need inline styles to survive a mail
 * client, a text alternative anyway, and would still look wrong in half of
 * them; a short plain-text summary with a link to the screen is readable
 * everywhere, and the screen is where anything is actually done.
 */
final class Mailer {

	/**
	 * Who the report goes to.
	 *
	 * Falls back to the site's admin address rather than sending nowhere, since
	 * an empty recipients field is far more likely to be an oversight than an
	 * instruction to send to no one.
	 *
	 * @return string[]
	 */
	public static function recipients(): array {
		$raw = (string) Settings::get( 'report_recipients', '' );

		$parts = preg_split( '/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );

		$addresses = array_filter(
			array_map( 'trim', is_array( $parts ) ? $parts : array() ),
			static fn ( string $address ): bool => false !== is_email( $address )
		);

		if ( ! $addresses ) {
			$addresses = array( (string) get_option( 'admin_email' ) );
		}

		/**
		 * Filters the report recipients.
		 *
		 * @param string[] $addresses Email addresses.
		 */
		return array_values( array_unique( (array) apply_filters( 'wpcseo_report_recipients', $addresses ) ) );
	}

	/**
	 * The subject line.
	 *
	 * It leads with what needs doing, because a subject that says "SEO report"
	 * tells the reader nothing they can act on from the inbox.
	 *
	 * @param array<string, mixed> $report Assembled report.
	 */
	public static function subject( array $report ): string {
		$totals   = (array) ( $report['audit']['totals'] ?? array() );
		$critical = (int) ( $totals[ Finding::CRITICAL ] ?? 0 );
		$site     = (string) $report['site'];

		if ( $critical > 0 ) {
			return sprintf(
				/* translators: 1: number of critical findings, 2: site name. */
				_n( '%1$d critical SEO issue on %2$s', '%1$d critical SEO issues on %2$s', $critical, 'wp-custom-seo' ),
				$critical,
				$site
			);
		}

		$important = (int) ( $totals[ Finding::IMPORTANT ] ?? 0 );

		if ( $important > 0 ) {
			return sprintf(
				/* translators: 1: number of important findings, 2: site name. */
				_n( '%1$d SEO issue worth looking at on %2$s', '%1$d SEO issues worth looking at on %2$s', $important, 'wp-custom-seo' ),
				$important,
				$site
			);
		}

		return sprintf(
			/* translators: %s: site name. */
			__( 'SEO report for %s', 'wp-custom-seo' ),
			$site
		);
	}

	/**
	 * The message body.
	 *
	 * @param array<string, mixed> $report Assembled report.
	 */
	public static function body( array $report ): string {
		$lines = array(
			sprintf(
				/* translators: 1: site name, 2: site URL. */
				__( 'SEO report for %1$s (%2$s)', 'wp-custom-seo' ),
				(string) $report['site'],
				(string) $report['url']
			),
			'',
		);

		$lines = array_merge( $lines, self::audit_lines( (array) $report['audit'] ) );

		if ( is_array( $report['search'] ) ) {
			$lines = array_merge( $lines, self::search_lines( $report['search'] ) );
		}

		if ( (int) $report['not_found'] > 0 ) {
			$lines[] = '';
			$lines[] = sprintf(
				/* translators: %d: number of logged missing URLs. */
				_n( '%d URL has been requested and not found.', '%d URLs have been requested and not found.', (int) $report['not_found'], 'wp-custom-seo' ),
				(int) $report['not_found']
			);
			$lines[] = admin_url( 'admin.php?page=wp-custom-seo-404' );
		}

		$lines[] = '';
		$lines[] = __( 'Full audit:', 'wp-custom-seo' ) . ' ' . admin_url( 'admin.php?page=wp-custom-seo-audit' );
		$lines[] = '';
		$lines[] = __( 'These are recommendations from this plugin, not measurements from a search engine. Turn this email off under SEO → Settings → Reports.', 'wp-custom-seo' );

		/**
		 * Filters the report email body.
		 *
		 * @param string               $body   Rendered body.
		 * @param array<string, mixed> $report Assembled report.
		 */
		return (string) apply_filters( 'wpcseo_report_body', implode( "\n", $lines ), $report );
	}

	/**
	 * Audit lines.
	 *
	 * @param array<string, mixed> $audit Audit section.
	 *
	 * @return string[]
	 */
	private static function audit_lines( array $audit ): array {
		$findings = (array) $audit['findings'];

		if ( ! $findings ) {
			return array( __( 'The site audit found nothing that needs attention.', 'wp-custom-seo' ) );
		}

		$labels = Finding::levels();
		$lines  = array( __( 'What the site audit found', 'wp-custom-seo' ), '' );

		foreach ( $findings as $finding ) {
			$lines[] = sprintf(
				'[%s] %s',
				(string) ( $labels[ $finding['level'] ] ?? $finding['level'] ),
				(string) $finding['title']
			);
			$lines[] = '    ' . (string) $finding['action'];
		}

		return $lines;
	}

	/**
	 * Search performance lines.
	 *
	 * @param array<string, mixed> $search Search section.
	 *
	 * @return string[]
	 */
	private static function search_lines( array $search ): array {
		$lines = array(
			'',
			sprintf(
				/* translators: 1: start date, 2: end date. */
				__( 'What Google reported, %1$s to %2$s', 'wp-custom-seo' ),
				(string) $search['range']['start'],
				(string) $search['range']['end']
			),
			'',
			sprintf(
				/* translators: 1: clicks, 2: impressions, 3: click-through rate. */
				__( '%1$s clicks from %2$s impressions (%3$s).', 'wp-custom-seo' ),
				number_format_i18n( (int) $search['totals']['clicks'] ),
				number_format_i18n( (int) $search['totals']['impressions'] ),
				number_format_i18n( (float) $search['totals']['ctr'] * 100, 1 ) . '%'
			),
		);

		if ( $search['queries'] ) {
			$lines[] = '';
			$lines[] = __( 'Top queries:', 'wp-custom-seo' );

			foreach ( (array) $search['queries'] as $row ) {
				$lines[] = sprintf(
					/* translators: 1: query, 2: clicks, 3: impressions. */
					__( '    %1$s — %2$s clicks, %3$s impressions', 'wp-custom-seo' ),
					(string) $row['key'],
					number_format_i18n( (int) $row['clicks'] ),
					number_format_i18n( (int) $row['impressions'] )
				);
			}
		}

		return $lines;
	}

	/**
	 * Send the report.
	 *
	 * @param array<string, mixed> $report Assembled report.
	 *
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function send( array $report ): bool {
		return wp_mail(
			self::recipients(),
			self::subject( $report ),
			self::body( $report )
		);
	}
}

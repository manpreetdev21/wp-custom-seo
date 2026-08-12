<?php
/**
 * Tolerant JSON reader for model output.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts a JSON object from a model's reply.
 *
 * Models are asked for bare JSON and usually comply, but not always: a reply
 * may arrive wrapped in a ```json fence, or with a sentence of preamble before
 * the object. Both are recoverable and neither is worth failing a request
 * over, so they are handled here rather than in every caller.
 *
 * What is *not* recovered is genuinely broken JSON. A half-written object is
 * reported as an error rather than guessed at — inventing structure the model
 * did not produce is exactly the failure mode this plugin avoids elsewhere.
 */
final class Json {

	/**
	 * Decode a model reply into an array.
	 *
	 * @param string   $text     Raw model output.
	 * @param string[] $required Keys that must be present.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function decode( string $text, array $required = array() ): array|WP_Error {
		$candidate = self::extract( $text );

		if ( '' === $candidate ) {
			return new WP_Error(
				'wpcseo_ai_no_json',
				__( 'The model did not return structured data. Try again — this usually succeeds on a second attempt.', 'wp-custom-seo' )
			);
		}

		$decoded = json_decode( $candidate, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'wpcseo_ai_bad_json',
				__( 'The model returned structured data this plugin could not read. Try again, or raise the maximum length if the reply looks cut off.', 'wp-custom-seo' )
			);
		}

		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $decoded ) ) {
				return new WP_Error(
					'wpcseo_ai_incomplete_json',
					__( 'The model left out part of the response. Try again.', 'wp-custom-seo' )
				);
			}
		}

		return $decoded;
	}

	/**
	 * Pull the JSON object out of a reply.
	 *
	 * @param string $text Raw model output.
	 */
	private static function extract( string $text ): string {
		$text = trim( $text );

		// Strip a ```json … ``` fence if the model added one.
		if ( preg_match( '/```(?:json)?\s*(.+?)```/s', $text, $matches ) ) {
			$text = trim( $matches[1] );
		}

		if ( str_starts_with( $text, '{' ) && str_ends_with( $text, '}' ) ) {
			return $text;
		}

		// Fall back to the outermost braces, which handles a sentence of
		// preamble before the object.
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );

		if ( false === $start || false === $end || $end <= $start ) {
			return '';
		}

		return substr( $text, $start, $end - $start + 1 );
	}

	/**
	 * Read a list of rows, keeping only those with the expected shape.
	 *
	 * A model occasionally emits one malformed entry in an otherwise good
	 * list. Dropping that entry is better than discarding the whole reply.
	 *
	 * @param mixed    $value  Raw value from the decoded reply.
	 * @param string[] $fields Field names to keep, in order.
	 * @param int      $limit  Maximum rows.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function rows( mixed $value, array $fields, int $limit = 25 ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$rows = array();

		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$row   = array();
			$empty = true;

			foreach ( $fields as $field ) {
				$cell          = $entry[ $field ] ?? '';
				$cell          = is_scalar( $cell ) ? trim( (string) $cell ) : '';
				$row[ $field ] = $cell;

				if ( '' !== $cell ) {
					$empty = false;
				}
			}

			if ( ! $empty ) {
				$rows[] = $row;
			}

			if ( count( $rows ) >= $limit ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * Read a list of plain strings.
	 *
	 * @param mixed $value Raw value from the decoded reply.
	 * @param int   $limit Maximum entries.
	 *
	 * @return string[]
	 */
	public static function strings( mixed $value, int $limit = 25 ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();

		foreach ( $value as $entry ) {
			if ( ! is_scalar( $entry ) ) {
				continue;
			}

			$entry = trim( (string) $entry );

			if ( '' !== $entry ) {
				$out[] = $entry;
			}

			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}
}

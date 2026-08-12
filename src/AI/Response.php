<?php
/**
 * Neutral AI response.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

defined( 'ABSPATH' ) || exit;

/**
 * A provider-independent completion result.
 */
final class Response {

	/**
	 * Build a response.
	 *
	 * @param string $text          Generated text.
	 * @param string $model         Model that produced it.
	 * @param int    $input_tokens  Prompt tokens, or 0 when the provider does not report them.
	 * @param int    $output_tokens Completion tokens, or 0 when not reported.
	 * @param int    $duration_ms   Round-trip time.
	 */
	public function __construct(
		public readonly string $text,
		public readonly string $model,
		public readonly int $input_tokens = 0,
		public readonly int $output_tokens = 0,
		public readonly int $duration_ms = 0
	) {
	}

	/**
	 * Split the text into trimmed, non-empty lines with any list marker removed.
	 *
	 * Used by prompts that ask for one suggestion per line.
	 *
	 * @return string[]
	 */
	public function lines(): array {
		$lines = preg_split( '/\R/', $this->text );
		$clean = array();

		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = trim( (string) $line );

			// Strip "1." / "1)" / "-" / "*" list markers and surrounding quotes.
			$line = (string) preg_replace( '/^\s*(?:\d+[.)]|[-*\x{2022}])\s*/u', '', $line );
			$line = trim( $line, " \t\"'“”‘’" );

			if ( '' !== $line ) {
				$clean[] = $line;
			}
		}

		return $clean;
	}
}

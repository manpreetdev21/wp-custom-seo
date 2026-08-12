<?php
/**
 * Prompt contract.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

use WPCustomSeo\AI\Request;

defined( 'ABSPATH' ) || exit;

/**
 * Builds one AI request from page context.
 *
 * Prompts live in their own classes rather than inside controllers so they can
 * be read, reviewed and filtered as text. Every prompt passes its finished
 * system and user messages through `wpcseo_ai_prompt` before use.
 */
abstract class Prompt {

	/**
	 * Action id, used for logging and for the prompt filter.
	 */
	abstract public function action(): string;

	/**
	 * The system instruction.
	 */
	abstract protected function system(): string;

	/**
	 * The user message.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	abstract protected function user( array $context ): string;

	/**
	 * Output ceiling for this action.
	 */
	public function max_tokens(): int {
		return 700;
	}

	/**
	 * Build the request.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	public function build( array $context ): Request {
		$system = $this->system();
		$user   = $this->user( $context );

		/**
		 * Filters an AI prompt before it is sent.
		 *
		 * @param array<string, string> $parts   `system` and `user` messages.
		 * @param string                $action  Action id.
		 * @param array<string, mixed>  $context Page context.
		 */
		$parts = (array) apply_filters(
			'wpcseo_ai_prompt',
			array(
				'system' => $system,
				'user'   => $user,
			),
			$this->action(),
			$context
		);

		return new Request(
			$this->action(),
			(string) ( $parts['system'] ?? $system ),
			(string) ( $parts['user'] ?? $user ),
			'',
			$this->max_tokens(),
			null,
			(int) ( $context['post_id'] ?? 0 )
		);
	}

	/**
	 * Shorten context so a long page does not blow the request size.
	 *
	 * @param string $text  Plain text.
	 * @param int    $words Maximum words to keep.
	 */
	protected function excerpt( string $text, int $words = 400 ): string {
		$parts = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$parts = is_array( $parts ) ? $parts : array();

		return implode( ' ', array_slice( $parts, 0, $words ) );
	}

	/**
	 * Render the shared context block every prompt starts from.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	protected function context_block( array $context ): string {
		$lines = array();

		foreach ( array(
			'Title'                    => (string) ( $context['title'] ?? '' ),
			'Focus keyphrase'          => (string) ( $context['keyword'] ?? '' ),
			'Current SEO title'        => (string) ( $context['seo_title'] ?? '' ),
			'Current meta description' => (string) ( $context['description'] ?? '' ),
		) as $label => $value ) {
			$value = trim( $value );

			if ( '' !== $value ) {
				$lines[] = $label . ': ' . $value;
			}
		}

		$content = $this->excerpt( (string) ( $context['content'] ?? '' ) );

		if ( '' !== $content ) {
			$lines[] = "\nPage content:\n" . $content;
		}

		return implode( "\n", $lines );
	}
}

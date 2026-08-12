<?php
/**
 * SEO title prompt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Asks for several SEO title options.
 */
final class TitlePrompt extends Prompt {

	/**
	 * Action id.
	 */
	public function action(): string {
		return 'title';
	}

	/**
	 * System instruction.
	 */
	protected function system(): string {
		return implode(
			' ',
			array(
				'You write SEO titles for web pages.',
				'Return exactly five options, one per line, with no numbering, quotes, commentary or preamble.',
				'Each title must be between 30 and 60 characters, describe what is genuinely on the page, and read naturally to a person.',
				'Work the focus keyphrase in where it fits, preferably near the start, but never at the cost of clarity.',
				'Do not invent facts, statistics, dates, prices or claims that are not in the supplied content.',
				'Do not use clickbait, all caps, or manufactured urgency.',
			)
		);
	}

	/**
	 * User message.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	protected function user( array $context ): string {
		return "Write five SEO title options for this page.\n\n" . $this->context_block( $context );
	}
}

<?php
/**
 * Meta description prompt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Asks for several meta description options across search intents.
 */
final class MetaDescriptionPrompt extends Prompt {

	/**
	 * Action id.
	 */
	public function action(): string {
		return 'meta_description';
	}

	/**
	 * Output ceiling.
	 */
	public function max_tokens(): int {
		return 900;
	}

	/**
	 * System instruction.
	 */
	protected function system(): string {
		return implode(
			' ',
			array(
				'You write meta descriptions for web pages.',
				'Return exactly four options, one per line, with no numbering, quotes, commentary or preamble.',
				'Write the first as a plain informational summary, the second with a clear call to action, the third for someone comparing options, and the fourth for someone ready to act.',
				'Each must be between 120 and 160 characters, summarise what the page actually contains, and give a real reason to click.',
				'Use the focus keyphrase once, naturally.',
				'Do not invent facts, statistics, prices, availability, guarantees or claims that are not in the supplied content.',
				'Do not promise anything the page does not deliver.',
			)
		);
	}

	/**
	 * User message.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	protected function user( array $context ): string {
		return "Write four meta description options for this page.\n\n" . $this->context_block( $context );
	}
}

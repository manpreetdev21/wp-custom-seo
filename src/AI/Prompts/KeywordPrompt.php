<?php
/**
 * Keyword suggestion prompt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Asks for keyword and topic suggestions drawn from the page.
 *
 * Search volume, difficulty and competition are deliberately not requested.
 * A language model does not have that data; anything it produced would be a
 * plausible-looking number with nothing behind it, and an editor would
 * reasonably act on it. Those columns appear only if a real keyword-data
 * provider is ever connected.
 */
final class KeywordPrompt extends Prompt {

	/**
	 * Action id.
	 */
	public function action(): string {
		return 'keywords';
	}

	/**
	 * Output ceiling.
	 */
	public function max_tokens(): int {
		return 1600;
	}

	/**
	 * System instruction.
	 */
	protected function system(): string {
		return implode(
			' ',
			array(
				'You analyse a web page and suggest the search terms it could realistically rank for.',
				'Reply with a single JSON object and nothing else — no prose, no code fence.',
				'Use this shape: {"primary":{"keyword":"","intent":"","reason":""},',
				'"secondary":[{"keyword":"","intent":"","usage":"","location":""}],',
				'"long_tail":[{"keyword":"","intent":"","usage":"","location":""}],',
				'"questions":[""],"entities":[""],"semantic":[""]}.',
				'"intent" must be exactly one of: informational, navigational, commercial, transactional, local.',
				'"usage" says briefly how to work the term in; "location" names where it belongs, such as H2, introduction, or a new section.',
				'Base every suggestion on what the supplied content actually covers — do not suggest terms the page would have to be rewritten to deserve.',
				'Never include search volume, difficulty, competition or traffic estimates: you do not have that data and a made-up number is worse than none.',
				'Return at most eight secondary terms, eight long-tail terms, six questions, eight entities and eight semantic terms.',
			)
		);
	}

	/**
	 * User message.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	protected function user( array $context ): string {
		return "Suggest search terms for this page.\n\n" . $this->context_block( $context );
	}
}

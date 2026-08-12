<?php
/**
 * FAQ generation prompt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Asks for questions a reader of this page would still have.
 *
 * The answers must come from the page. A model asked for "an FAQ" will happily
 * supply plausible answers to questions the page never addresses, and those
 * answers then look authoritative sitting under the site's own byline. So the
 * instruction is to answer only from the supplied content, and to say plainly
 * when the page does not contain the answer rather than filling the gap.
 */
final class FaqPrompt extends Prompt {

	/**
	 * Action id.
	 */
	public function action(): string {
		return 'faq';
	}

	/**
	 * Output ceiling.
	 */
	public function max_tokens(): int {
		return 2000;
	}

	/**
	 * System instruction.
	 */
	protected function system(): string {
		return implode(
			' ',
			array(
				'You write frequently asked questions for a web page.',
				'Reply with a single JSON object and nothing else — no prose, no code fence.',
				'Use this shape: {"answered":[{"question":"","answer":"","source":""}],"unanswered":[{"question":"","why":""}]}.',
				'"answered" holds questions the supplied content genuinely answers. The answer must be drawn from that content and nothing else, and "source" quotes the phrase or sentence it came from so an editor can check it.',
				'"unanswered" holds questions a reader would reasonably ask that the content does not address. Do not answer these — "why" says briefly why the question matters to this reader.',
				'Never move a question into "answered" by supplying knowledge from outside the page. If the page does not say it, it is unanswered.',
				'Write each answer in two or three sentences, in the same voice as the page.',
				'Do not invent statistics, prices, timescales, guarantees or legal and medical claims.',
				'Return at most six answered and six unanswered questions.',
			)
		);
	}

	/**
	 * User message.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	protected function user( array $context ): string {
		return "Write FAQ entries for this page.\n\n" . $this->context_block( $context );
	}
}

<?php
/**
 * Content optimization prompt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Asks what the page is missing, and why each gap matters.
 *
 * Every recommendation must carry its own reasoning. A bare list of "add a
 * section on X" gives an editor no basis to judge whether X belongs on the
 * page, so the model is required to state the issue, why it matters, and what
 * to do — the shape the rest of the plugin's checks already use.
 *
 * Nothing here rewrites the page. The result is advice an editor reads and
 * acts on; the plugin never applies it.
 */
final class ContentAnalysisPrompt extends Prompt {

	/**
	 * Action id.
	 */
	public function action(): string {
		return 'content_analysis';
	}

	/**
	 * Output ceiling.
	 */
	public function max_tokens(): int {
		return 2200;
	}

	/**
	 * System instruction.
	 */
	protected function system(): string {
		return implode(
			' ',
			array(
				'You review a web page and report what would make it more useful to the person who searched for it.',
				'Reply with a single JSON object and nothing else — no prose, no code fence.',
				'Use this shape: {"intent":{"type":"","confidence":0,"reason":""},',
				'"summary":"","missing_topics":[{"issue":"","why":"","recommendation":""}],',
				'"missing_questions":[""],"missing_entities":[""],',
				'"weak_sections":[{"issue":"","why":"","recommendation":""}],',
				'"heading_suggestions":[{"issue":"","why":"","recommendation":""}],',
				'"internal_link_ideas":[""],"external_reference_ideas":[""]}.',
				'"intent.type" must be exactly one of: informational, navigational, commercial, transactional, local, mixed.',
				'"intent.confidence" is an integer from 0 to 100 expressing how clearly the content matches that intent — it is your own certainty, not a measurement of anything external.',
				'Judge only against the content supplied. Do not claim the page is missing something it already covers.',
				'Do not invent statistics, dates, prices, study results or sources; when a claim would need a citation, say so rather than supplying one.',
				'Do not recommend adding keywords for their own sake, padding length, or repeating a phrase more often.',
				'Return at most six entries in each list. If the page is genuinely complete in some respect, return an empty list rather than inventing a gap.',
			)
		);
	}

	/**
	 * User message.
	 *
	 * @param array<string, mixed> $context Page context.
	 */
	protected function user( array $context ): string {
		return "Review this page and report what is missing or weak.\n\n" . $this->context_block( $context );
	}
}

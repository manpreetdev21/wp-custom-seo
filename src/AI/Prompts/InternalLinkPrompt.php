<?php
/**
 * Internal linking prompt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Asks which of a supplied list of real pages this page should link to.
 *
 * The candidate list is built from the site's own content before this prompt
 * runs, so the model chooses from pages that certainly exist rather than
 * proposing a destination. It is asked to return the candidate's id, which
 * means a suggestion can be resolved back to a real post or discarded — there
 * is no way for an invented URL to reach the editor.
 *
 * It is also told to return fewer suggestions than it is offered. A model
 * asked to rank a list will rank all of it; the useful answer is which few
 * genuinely belong.
 */
final class InternalLinkPrompt extends Prompt {

	/**
	 * Action id.
	 */
	public function action(): string {
		return 'internal_links';
	}

	/**
	 * Output ceiling.
	 */
	public function max_tokens(): int {
		return 1800;
	}

	/**
	 * System instruction.
	 */
	protected function system(): string {
		return implode(
			' ',
			array(
				'You choose which internal links a web page should gain.',
				'Reply with a single JSON object and nothing else — no prose, no code fence.',
				'Use this shape: {"links":[{"id":0,"anchor":"","reason":"","confidence":0,"placement":""}]}.',
				'"id" must be the numeric id of one of the candidate pages given to you. Never invent an id, a title or a URL.',
				'"anchor" is the exact wording to turn into a link. It must be a phrase that already appears in the page content, or a natural rewording of a phrase that does — never a bare "click here" or the raw page title dropped in.',
				'"reason" says in one sentence why a reader following that link would be helped.',
				'"confidence" is an integer from 0 to 100 expressing how sure you are the link belongs. It is your own judgement, not a measurement.',
				'"placement" names the section or paragraph the link belongs in.',
				'Only suggest a link where it genuinely helps the reader. Return fewer than you are offered — a good answer is often two or three, and an empty list is a valid answer.',
				'Do not suggest a link merely because two pages share a word. Do not suggest linking the same phrase twice.',
				'Return at most six links.',
			)
		);
	}

	/**
	 * User message.
	 *
	 * @param array<string, mixed> $context Page context plus candidates.
	 */
	protected function user( array $context ): string {
		$lines = array( 'This is the page that would gain the links.', '', $this->context_block( $context ), '', 'Candidate pages on the same site:' );

		foreach ( (array) ( $context['candidates'] ?? array() ) as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$line = sprintf( 'id %d: %s', (int) ( $candidate['id'] ?? 0 ), (string) ( $candidate['title'] ?? '' ) );

			$excerpt = trim( (string) ( $candidate['excerpt'] ?? '' ) );

			if ( '' !== $excerpt ) {
				$line .= ' — ' . $this->excerpt( $excerpt, 25 );
			}

			$lines[] = $line;
		}

		$lines[] = '';
		$lines[] = 'Choose only the ones that genuinely belong.';

		return implode( "\n", $lines );
	}
}

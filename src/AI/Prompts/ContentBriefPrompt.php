<?php
/**
 * Content brief prompt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Asks for a full brief for a page that does not exist yet.
 *
 * Unlike the other prompts this one has no page to read: the writer supplies
 * a topic, audience and market, and the model plans from that. It produces a
 * plan for a person to write against — it does not write the page.
 */
final class ContentBriefPrompt extends Prompt {

	/**
	 * Action id.
	 */
	public function action(): string {
		return 'content_brief';
	}

	/**
	 * Output ceiling.
	 */
	public function max_tokens(): int {
		return 2800;
	}

	/**
	 * System instruction.
	 */
	protected function system(): string {
		return implode(
			' ',
			array(
				'You plan web pages. Given a topic and audience, produce a brief a writer can work from.',
				'Reply with a single JSON object and nothing else — no prose, no code fence.',
				'Use this shape: {"title":"","intent":{"type":"","reason":""},"audience":"","h1":"",',
				'"outline":[{"h2":"","h3":[""],"covers":""}],',
				'"questions":[""],"entities":[""],"related_keywords":[""],',
				'"internal_link_ideas":[""],"external_reference_ideas":[""],',
				'"faq_topics":[""],"schema_type":"","depth":"","notes":""}.',
				'"intent.type" must be exactly one of: informational, navigational, commercial, transactional, local, mixed.',
				'"schema_type" must be one a WordPress page can honestly carry: Article, BlogPosting, NewsArticle, WebPage, AboutPage, ContactPage, or none.',
				'"depth" is a plain-language recommendation such as "roughly 1200 words, eight sections" — give a range and say what drives it, not a single number presented as a requirement.',
				'"external_reference_ideas" must describe the *kind* of source to cite (for example "the manufacturer\'s installation specification") rather than naming a specific URL, article or study — you cannot verify that a particular source exists or says what is claimed.',
				'Plan for the audience and market given. Do not invent statistics, prices, regulations or study findings.',
				'Return at most eight outline sections, eight questions, ten entities, ten related keywords and six FAQ topics.',
			)
		);
	}

	/**
	 * User message.
	 *
	 * @param array<string, mixed> $context Brief inputs.
	 */
	protected function user( array $context ): string {
		$lines = array();

		foreach ( array(
			'Topic'           => (string) ( $context['topic'] ?? '' ),
			'Primary keyword' => (string) ( $context['keyword'] ?? '' ),
			'Target audience' => (string) ( $context['audience'] ?? '' ),
			'Country'         => (string) ( $context['country'] ?? '' ),
			'Language'        => (string) ( $context['language'] ?? '' ),
			'Content type'    => (string) ( $context['content_type'] ?? '' ),
			'Search intent'   => (string) ( $context['intent'] ?? '' ),
			'Business'        => (string) ( $context['business'] ?? '' ),
		) as $label => $value ) {
			$value = trim( $value );

			if ( '' !== $value ) {
				$lines[] = $label . ': ' . $value;
			}
		}

		return "Plan a page from this brief.\n\n" . implode( "\n", $lines );
	}
}

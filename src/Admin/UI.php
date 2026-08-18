<?php
/**
 * Reusable admin UI components.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's component library.
 *
 * **Why components rather than markup in fifteen templates.** A badge appeared
 * in eleven of them, each spelling its own classes; a card heading was written
 * out by hand every time. That is how two screens end up disagreeing about what
 * "warning" looks like, and how a spacing fix has to be made eleven times. Each
 * component here is one function that returns escaped HTML, so a change lands
 * everywhere at once and there is exactly one place where a value reaches the
 * page unescaped — which is to say, none.
 *
 * **Escaping.** Every method escapes its own arguments. Where a component takes
 * caller-supplied markup — the body of a card, the cell of a table — that is
 * named `$html` and is the caller's responsibility, in the same way
 * `wp_kses_post()` trusts what it is handed. Everything named as text is
 * escaped here.
 *
 * **Return, don't print.** Components return strings so they compose, and so a
 * template decides where output happens. The one exception is `open_card()` and
 * `close_card()`, which bracket arbitrary template markup and cannot.
 */
final class UI {

	/**
	 * Status tones, mapped to the classes the stylesheet knows.
	 */
	public const TONES = array( 'neutral', 'good', 'warn', 'bad', 'info', 'accent' );

	/**
	 * Normalise a tone to one the stylesheet defines.
	 *
	 * @param string $tone Requested tone.
	 */
	private static function tone( string $tone ): string {
		return in_array( $tone, self::TONES, true ) ? $tone : 'neutral';
	}

	/**
	 * A page header: title, description and optional actions.
	 *
	 * Every screen opens with this, so the eye lands in the same place on all
	 * fifteen instead of hunting for the title.
	 *
	 * @param string $title   Page title.
	 * @param string $lede    One-sentence description, or empty.
	 * @param string $actions Rendered action markup, or empty.
	 */
	public static function page_header( string $title, string $lede = '', string $actions = '' ): string {
		$out = '<header class="wpcseo-page-head"><div class="wpcseo-page-head__text">';

		$out .= '<h1 class="wpcseo-page-title">' . esc_html( $title ) . '</h1>';

		if ( '' !== $lede ) {
			$out .= '<p class="wpcseo-lede">' . esc_html( $lede ) . '</p>';
		}

		$out .= '</div>';

		if ( '' !== $actions ) {
			$out .= '<div class="wpcseo-page-head__actions">' . $actions . '</div>';
		}

		return $out . '</header>';
	}

	/**
	 * A status badge.
	 *
	 * The tone sets the colour, and the label carries the meaning — the two are
	 * never the same job. A reader who cannot distinguish the colours still gets
	 * the word, which is the whole of WCAG 1.4.1 in one line.
	 *
	 * @param string $label Visible text.
	 * @param string $tone  One of the TONES.
	 */
	public static function badge( string $label, string $tone = 'neutral' ): string {
		return sprintf(
			'<span class="wpcseo-badge is-%1$s">%2$s</span>',
			esc_attr( self::tone( $tone ) ),
			esc_html( $label )
		);
	}

	/**
	 * A badge for an on/off state.
	 *
	 * @param bool   $on  Whether the thing is on.
	 * @param string $yes Label when on.
	 * @param string $no  Label when off.
	 */
	public static function state( bool $on, string $yes = '', string $no = '' ): string {
		$yes = '' !== $yes ? $yes : __( 'On', 'wp-custom-seo' );
		$no  = '' !== $no ? $no : __( 'Off', 'wp-custom-seo' );

		return self::badge( $on ? $yes : $no, $on ? 'good' : 'neutral' );
	}

	/**
	 * Open a card.
	 *
	 * @param string $title   Card heading, or empty for an unheaded card.
	 * @param string $id      Element id used to label the region, or empty.
	 * @param string $classes Extra class names.
	 */
	public static function open_card( string $title = '', string $id = '', string $classes = '' ): void {
		$id = '' !== $id ? $id : ( '' !== $title ? 'wpcseo-card-' . sanitize_title( $title ) : '' );

		printf(
			'<section class="wpcseo-card %s"%s>',
			esc_attr( $classes ),
			'' !== $id ? ' aria-labelledby="' . esc_attr( $id ) . '"' : ''
		);

		if ( '' !== $title ) {
			printf(
				'<h2 class="wpcseo-card__title" id="%s">%s</h2>',
				esc_attr( $id ),
				esc_html( $title )
			);
		}
	}

	/**
	 * Close a card.
	 */
	public static function close_card(): void {
		echo '</section>';
	}

	/**
	 * A statistic tile: label, value, and an optional note beneath.
	 *
	 * @param string $label What the number is.
	 * @param string $value The number, already formatted for the locale.
	 * @param string $note  Context, or empty.
	 * @param string $tone  Tone for the note.
	 */
	public static function stat( string $label, string $value, string $note = '', string $tone = 'neutral' ): string {
		$out = '<div class="wpcseo-stat">';

		$out .= '<span class="wpcseo-stat__label">' . esc_html( $label ) . '</span>';
		$out .= '<span class="wpcseo-stat__value">' . esc_html( $value ) . '</span>';

		if ( '' !== $note ) {
			$out .= sprintf(
				'<span class="wpcseo-stat__note is-%s">%s</span>',
				esc_attr( self::tone( $tone ) ),
				esc_html( $note )
			);
		}

		return $out . '</div>';
	}

	/**
	 * A score ring.
	 *
	 * Drawn as an SVG arc rather than a chart library: it is one circle with a
	 * dash offset, and pulling in a charting dependency to draw it would cost
	 * more than every other asset on the page combined.
	 *
	 * The number is repeated as text in the middle, so the ring is decoration
	 * and the value is readable — a screen reader gets the label and the score,
	 * never a bare percentage with no subject.
	 *
	 * @param int    $score   Value between 0 and 100.
	 * @param string $label   What is being scored.
	 * @param string $caption Short word for the band, e.g. "Good".
	 * @param int    $size    Pixel diameter.
	 */
	public static function score_ring( int $score, string $label, string $caption = '', int $size = 148 ): string {
		$score  = max( 0, min( 100, $score ) );
		$tone   = self::score_tone( $score );
		$radius = 54;
		$length = 2 * M_PI * $radius;
		$offset = $length * ( 1 - ( $score / 100 ) );

		$out = sprintf(
			'<div class="wpcseo-ring is-%1$s" style="--wpcseo-ring-size:%2$dpx">',
			esc_attr( $tone ),
			$size
		);

		$out .= sprintf(
			'<svg viewBox="0 0 128 128" class="wpcseo-ring__svg" role="img" aria-label="%s">',
			esc_attr(
				sprintf(
					/* translators: 1: what is being scored, 2: the score out of 100. */
					__( '%1$s: %2$d out of 100', 'wp-custom-seo' ),
					$label,
					$score
				)
			)
		);

		$out .= '<circle class="wpcseo-ring__track" cx="64" cy="64" r="' . $radius . '" />';

		$out .= sprintf(
			'<circle class="wpcseo-ring__value" cx="64" cy="64" r="%1$d" stroke-dasharray="%2$s" stroke-dashoffset="%3$s" />',
			$radius,
			esc_attr( (string) round( $length, 2 ) ),
			esc_attr( (string) round( $offset, 2 ) )
		);

		$out .= '</svg>';

		$out .= '<div class="wpcseo-ring__text" aria-hidden="true">';
		$out .= '<span class="wpcseo-ring__score">' . esc_html( number_format_i18n( $score ) ) . '</span>';

		if ( '' !== $caption ) {
			$out .= '<span class="wpcseo-ring__caption">' . esc_html( $caption ) . '</span>';
		}

		return $out . '</div></div>';
	}

	/**
	 * Which tone a 0-100 score falls in.
	 *
	 * @param int $score Score.
	 */
	public static function score_tone( int $score ): string {
		if ( $score >= 80 ) {
			return 'good';
		}

		return $score >= 50 ? 'warn' : 'bad';
	}

	/**
	 * A labelled progress bar.
	 *
	 * @param string $label Row label.
	 * @param int    $value Value between 0 and 100.
	 * @param string $suffix Text shown after the value, e.g. "%".
	 * @param string $link  Admin URL the row links to, or empty.
	 */
	public static function meter( string $label, int $value, string $suffix = '', string $link = '' ): string {
		$value = max( 0, min( 100, $value ) );
		$tone  = self::score_tone( $value );
		$tag   = '' !== $link ? 'a' : 'div';

		$out = sprintf(
			'<%1$s class="wpcseo-meter is-%2$s"%3$s>',
			$tag,
			esc_attr( $tone ),
			'' !== $link ? ' href="' . esc_url( $link ) . '"' : ''
		);

		$out .= '<span class="wpcseo-meter__label">' . esc_html( $label ) . '</span>';
		$out .= '<span class="wpcseo-meter__value">' . esc_html( number_format_i18n( $value ) . $suffix ) . '</span>';

		// The bar is aria-hidden because the value beside it is already the
		// accessible answer; a progressbar role here would read the same number
		// twice.
		$out .= sprintf(
			'<span class="wpcseo-meter__track" aria-hidden="true"><span class="wpcseo-meter__fill" style="width:%s%%"></span></span>',
			esc_attr( (string) $value )
		);

		return $out . '</' . $tag . '>';
	}

	/**
	 * An empty state.
	 *
	 * A blank screen tells someone the plugin is broken. This tells them what
	 * the screen is for and what to do first.
	 *
	 * @param string $icon    Icon name from Nav::icons().
	 * @param string $title   What is missing.
	 * @param string $body    Why the screen is empty and what to do.
	 * @param string $actions Rendered action markup, or empty.
	 */
	public static function empty_state( string $icon, string $title, string $body, string $actions = '' ): string {
		$out = '<div class="wpcseo-empty">';

		$out .= '<span class="wpcseo-empty__icon">' . Nav::icon( $icon, 28 ) . '</span>';
		$out .= '<h3 class="wpcseo-empty__title">' . esc_html( $title ) . '</h3>';
		$out .= '<p class="wpcseo-empty__body">' . esc_html( $body ) . '</p>';

		if ( '' !== $actions ) {
			$out .= '<div class="wpcseo-empty__actions">' . $actions . '</div>';
		}

		return $out . '</div>';
	}

	/**
	 * An inline alert.
	 *
	 * Distinct from a WordPress `.notice`, which belongs at the top of a screen
	 * and is dismissible. This one sits in the flow, next to what it is about.
	 *
	 * @param string $tone  One of the TONES.
	 * @param string $title Short statement.
	 * @param string $body  Explanation, or empty.
	 */
	public static function alert( string $tone, string $title, string $body = '' ): string {
		$icons = array(
			'bad'  => 'alert',
			'warn' => 'alert',
			'good' => 'shield',
			'info' => 'help',
		);

		$tone = self::tone( $tone );

		$out = sprintf( '<div class="wpcseo-alert is-%s">', esc_attr( $tone ) );

		$out .= '<span class="wpcseo-alert__icon">' . Nav::icon( $icons[ $tone ] ?? 'help', 18 ) . '</span>';
		$out .= '<div class="wpcseo-alert__text"><strong>' . esc_html( $title ) . '</strong>';

		if ( '' !== $body ) {
			$out .= ' <span>' . esc_html( $body ) . '</span>';
		}

		return $out . '</div></div>';
	}

	/**
	 * A button.
	 *
	 * Renders WordPress's own button classes so it inherits core's focus ring
	 * and its behaviour in every admin colour scheme, with one extra class for
	 * the sizing and radius this design system sets.
	 *
	 * @param string $label   Visible text.
	 * @param string $url     Destination.
	 * @param string $variant `primary`, `secondary` or `ghost`.
	 * @param string $icon    Icon name, or empty.
	 * @param bool   $external Whether it opens in a new tab.
	 */
	public static function button( string $label, string $url, string $variant = 'secondary', string $icon = '', bool $external = false ): string {
		$classes = array( 'button', 'wpcseo-btn', 'is-' . sanitize_html_class( $variant ) );

		if ( 'primary' === $variant ) {
			$classes[] = 'button-primary';
		}

		return sprintf(
			'<a class="%1$s" href="%2$s"%3$s>%4$s<span>%5$s</span></a>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $url ),
			$external ? ' target="_blank" rel="noopener"' : '',
			'' !== $icon ? Nav::icon( $icon, 16 ) : '',
			esc_html( $label )
		);
	}

	/**
	 * A definition row for use inside a `.wpcseo-list`.
	 *
	 * @param string $term  Label.
	 * @param string $value Value, already escaped or plain text.
	 * @param bool   $html  Whether `$value` is trusted markup.
	 */
	public static function row( string $term, string $value, bool $html = false ): string {
		return '<dt>' . esc_html( $term ) . '</dt><dd>' . ( $html ? $value : esc_html( $value ) ) . '</dd>';
	}

	/**
	 * A skeleton placeholder, shown while data loads.
	 *
	 * @param int    $lines Number of shimmer rows.
	 * @param string $label What is loading, announced to screen readers.
	 */
	public static function skeleton( int $lines = 3, string $label = '' ): string {
		$label = '' !== $label ? $label : __( 'Loading…', 'wp-custom-seo' );

		$out = sprintf(
			'<div class="wpcseo-skeleton" role="status" aria-live="polite"><span class="screen-reader-text">%s</span>',
			esc_html( $label )
		);

		$count = max( 1, $lines );

		for ( $i = 0; $i < $count; $i++ ) {
			$out .= '<span class="wpcseo-skeleton__line" aria-hidden="true"></span>';
		}

		return $out . '</div>';
	}
}

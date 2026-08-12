<?php
/**
 * Neutral AI request.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

defined( 'ABSPATH' ) || exit;

/**
 * A provider-independent completion request.
 */
final class Request {

	/**
	 * Build a request.
	 *
	 * @param string     $action      Action id, used for logging and prompt filters.
	 * @param string     $system      System instruction.
	 * @param string     $prompt      User message.
	 * @param string     $model       Model id.
	 * @param int        $max_tokens  Output ceiling.
	 * @param float|null $temperature Sampling temperature, or null to omit.
	 * @param int        $post_id     Post the request relates to, or 0.
	 */
	public function __construct(
		public readonly string $action,
		public readonly string $system,
		public readonly string $prompt,
		public readonly string $model = '',
		public readonly int $max_tokens = 1024,
		public readonly ?float $temperature = null,
		public readonly int $post_id = 0
	) {
	}

	/**
	 * A copy with a different model and temperature.
	 *
	 * @param string     $model       Model id.
	 * @param float|null $temperature Temperature, or null to omit.
	 */
	public function with_model( string $model, ?float $temperature ): self {
		return new self(
			$this->action,
			$this->system,
			$this->prompt,
			$model,
			$this->max_tokens,
			$temperature,
			$this->post_id
		);
	}

	/**
	 * Approximate size of what would be sent, for the privacy notice.
	 */
	public function length(): int {
		return mb_strlen( $this->system ) + mb_strlen( $this->prompt );
	}
}

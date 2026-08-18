<?php
/**
 * VideoObject structured data.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Entities\Registry;
use WPCustomSeo\Schema\Graph\Graph;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Describes a video that the page actually embeds.
 *
 * **Two conditions, both required.** A VideoObject is emitted only when the
 * page really does embed a video *and* an editor has supplied the properties
 * that describe it. Either half alone produces markup that lies: schema for a
 * video that is not on the page, or a video node with a guessed upload date.
 *
 * **Why the fields are typed in rather than scraped.** A YouTube embed URL
 * gives away `embedUrl` and nothing else. `name`, `description`, `uploadDate`
 * and `thumbnailUrl` — the four properties structured data guidance treats as
 * required — are not derivable from the markup, and inventing them from the
 * post title and publish date would state that the video was published when the
 * article was, which is usually false. So they are asked for, and when they are
 * absent nothing is published.
 *
 * The values live in post meta rather than a table: one video per page is the
 * shape WordPress content actually takes, and meta is what the REST API and the
 * block editor already read.
 */
final class Video {

	public const ENABLED = '_wpcseo_video_enabled';

	public const NAME = '_wpcseo_video_name';

	public const DESCRIPTION = '_wpcseo_video_description';

	public const THUMBNAIL = '_wpcseo_video_thumbnail';

	public const UPLOAD_DATE = '_wpcseo_video_upload_date';

	public const DURATION = '_wpcseo_video_duration';

	public const CONTENT_URL = '_wpcseo_video_content_url';

	public const EMBED_URL = '_wpcseo_video_embed_url';

	public const TRANSCRIPT = '_wpcseo_video_transcript';

	public const SETTING = 'enable_video_schema';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_settings_schema', array( self::class, 'settings' ) );
		add_action( 'init', array( self::class, 'register' ), 20 );
		add_filter( 'wpcseo_schema', array( self::class, 'add' ), 10, 2 );
	}

	/**
	 * Add the module toggle.
	 *
	 * @param array<string, array<string, mixed>> $schema Settings schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function settings( array $schema ): array {
		$schema['schema']['fields'][ self::SETTING ] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable video structured data', 'wp-custom-seo' ),
			'description' => __( 'Adds video fields to the editor. Markup is published only for a page that genuinely embeds a video and has the required details filled in — a name, description, thumbnail and upload date. Nothing is guessed from the post.', 'wp-custom-seo' ),
			'default'     => false,
		);

		return $schema;
	}

	/**
	 * Meta key definitions.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	public static function keys(): array {
		return array(
			self::ENABLED     => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::NAME        => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::DESCRIPTION => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_textarea_field',
			),
			self::THUMBNAIL   => array(
				'type'     => 'string',
				'sanitize' => array( \WPCustomSeo\SEO\Meta::class, 'sanitize_url_field' ),
			),
			self::UPLOAD_DATE => array(
				'type'     => 'string',
				'sanitize' => array( self::class, 'sanitize_date' ),
			),
			self::DURATION    => array(
				'type'     => 'string',
				'sanitize' => array( self::class, 'sanitize_duration' ),
			),
			self::CONTENT_URL => array(
				'type'     => 'string',
				'sanitize' => array( \WPCustomSeo\SEO\Meta::class, 'sanitize_url_field' ),
			),
			self::EMBED_URL   => array(
				'type'     => 'string',
				'sanitize' => array( \WPCustomSeo\SEO\Meta::class, 'sanitize_url_field' ),
			),
			self::TRANSCRIPT  => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * Register the keys against every SEO-enabled post type.
	 */
	public static function register(): void {
		if ( ! Settings::enabled( self::SETTING ) ) {
			return;
		}

		foreach ( \WPCustomSeo\SEO\Meta::post_types() as $post_type ) {
			foreach ( self::keys() as $key => $definition ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $definition['type'],
						'single'            => true,
						'default'           => 'boolean' === $definition['type'] ? false : '',
						'show_in_rest'      => true,
						'sanitize_callback' => $definition['sanitize'],
						'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Keep an ISO 8601 date, and drop anything else.
	 *
	 * A malformed `uploadDate` is worse than a missing one: it is a claim about
	 * when the video was published that no consumer can parse.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_date( mixed $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		// Accept a plain date or a full timestamp; both are valid ISO 8601.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)?)?$/', $value ) ) {
			return '';
		}

		return false === strtotime( $value ) ? '' : $value;
	}

	/**
	 * Keep an ISO 8601 duration such as `PT4M13S`.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_duration( mixed $value ): string {
		$value = strtoupper( trim( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		return preg_match( '/^PT(?=\d+[HMS])(\d+H)?(\d+M)?(\d+S)?$/', $value ) ? $value : '';
	}

	/**
	 * Whether the post content actually embeds a video.
	 *
	 * Covers a real `<video>` element, an oEmbed iframe from the common hosts,
	 * and the core video and embed blocks. It is deliberately conservative: a
	 * false negative withholds correct markup, a false positive publishes a
	 * claim about a video that is not there.
	 *
	 * @param string $content Raw post content.
	 */
	public static function has_embed( string $content ): bool {
		if ( preg_match( '/<video\b/i', $content ) ) {
			return true;
		}

		if ( preg_match( '#<iframe\b[^>]*\bsrc\s*=\s*["\'][^"\']*(youtube\.com|youtu\.be|youtube-nocookie\.com|player\.vimeo\.com|dailymotion\.com|wistia\.(net|com)|videopress\.com)#i', $content ) ) {
			return true;
		}

		if ( preg_match( '/<!--\s*wp:(core-embed\/)?(video|youtube|vimeo)\b/i', $content ) ) {
			return true;
		}

		// A bare oEmbed URL on its own line, which WordPress expands at render
		// time and so does not appear as an iframe in the stored content.
		return (bool) preg_match( '#^\s*https?://(www\.)?(youtube\.com/watch|youtu\.be/|vimeo\.com/)\S+\s*$#im', $content );
	}

	/**
	 * Read one value.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     One of the class constants.
	 */
	public static function get( int $post_id, string $key ): string {
		return trim( (string) get_post_meta( $post_id, $key, true ) );
	}

	/**
	 * Build a VideoObject node for a post, or null.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function node( int $post_id ): ?array {
		if ( ! Settings::enabled( self::SETTING ) || ! get_post_meta( $post_id, self::ENABLED, true ) ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! self::has_embed( (string) $post->post_content ) ) {
			return null;
		}

		$name        = self::get( $post_id, self::NAME );
		$description = self::get( $post_id, self::DESCRIPTION );
		$thumbnail   = self::get( $post_id, self::THUMBNAIL );
		$uploaded    = self::get( $post_id, self::UPLOAD_DATE );

		// All four are required by structured data guidance. A node missing any
		// of them would be published, rejected, and reported as an error against
		// the site — worse than publishing nothing.
		if ( '' === $name || '' === $description || '' === $thumbnail || '' === $uploaded ) {
			return null;
		}

		$permalink = (string) get_permalink( $post_id );

		$node = array(
			'@type'        => 'VideoObject',
			'@id'          => $permalink . '#video',
			'name'         => $name,
			'description'  => $description,
			'thumbnailUrl' => $thumbnail,
			'uploadDate'   => $uploaded,
			'publisher'    => Registry::reference( Registry::id( 'organization' ) ),
		);

		foreach ( array(
			'duration'   => self::DURATION,
			'contentUrl' => self::CONTENT_URL,
			'embedUrl'   => self::EMBED_URL,
			'transcript' => self::TRANSCRIPT,
		) as $property => $key ) {
			$value = self::get( $post_id, $key );

			if ( '' !== $value ) {
				$node[ $property ] = $value;
			}
		}

		/**
		 * Filters the video entity.
		 *
		 * @param array $node    VideoObject node.
		 * @param int   $post_id Post id.
		 */
		return (array) apply_filters( 'wpcseo_entity_video', $node, $post_id );
	}

	/**
	 * Attach the node when a page carrying a video is being viewed.
	 *
	 * @param Graph  $graph   Graph under construction.
	 * @param string $context Either `page` or `aggregate`.
	 */
	public static function add( Graph $graph, string $context = 'page' ): Graph {
		if ( 'page' !== $context || ! is_singular() ) {
			return $graph;
		}

		$node = self::node( (int) get_queried_object_id() );

		if ( null !== $node ) {
			$graph->add( $node );
		}

		return $graph;
	}
}

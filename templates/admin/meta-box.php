<?php
/**
 * Editor SEO panel markup.
 *
 * @package WPCustomSeo
 *
 * @var WP_Post              $post   Post being edited.
 * @var array<string, mixed> $values SEO meta values keyed by meta key.
 */

use WPCustomSeo\Core\Settings;
use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

$wpcseo_tabs = array(
	'general'  => __( 'General', 'wp-custom-seo' ),
	'social'   => __( 'Social', 'wp-custom-seo' ),
	'advanced' => __( 'Advanced', 'wp-custom-seo' ),
	'schema'   => __( 'Schema', 'wp-custom-seo' ),
	'ai'       => __( 'AI', 'wp-custom-seo' ),
	'search'   => __( 'Search', 'wp-custom-seo' ),
);

/**
 * Social fields, rendered from one definition.
 *
 * @var array<string, array{label: string, type: string, help: string}> $wpcseo_social_fields
 */
$wpcseo_social_fields = array(
	'wpcseo_og_title'            => array(
		'meta'  => Meta::OG_TITLE,
		'label' => __( 'Facebook / Open Graph title', 'wp-custom-seo' ),
		'type'  => 'text',
		'help'  => __( 'Leave empty to use the SEO title.', 'wp-custom-seo' ),
	),
	'wpcseo_og_description'      => array(
		'meta'  => Meta::OG_DESCRIPTION,
		'label' => __( 'Open Graph description', 'wp-custom-seo' ),
		'type'  => 'textarea',
		'help'  => __( 'Leave empty to use the meta description.', 'wp-custom-seo' ),
	),
	'wpcseo_og_image'            => array(
		'meta'  => Meta::OG_IMAGE,
		'label' => __( 'Open Graph image URL', 'wp-custom-seo' ),
		'type'  => 'url',
		'help'  => __( 'Leave empty to use the featured image, then the site default.', 'wp-custom-seo' ),
	),
	'wpcseo_twitter_title'       => array(
		'meta'  => Meta::TWITTER_TITLE,
		'label' => __( 'X/Twitter title', 'wp-custom-seo' ),
		'type'  => 'text',
		'help'  => __( 'Leave empty to use the Open Graph title.', 'wp-custom-seo' ),
	),
	'wpcseo_twitter_description' => array(
		'meta'  => Meta::TWITTER_DESCRIPTION,
		'label' => __( 'X/Twitter description', 'wp-custom-seo' ),
		'type'  => 'textarea',
		'help'  => __( 'Leave empty to use the Open Graph description.', 'wp-custom-seo' ),
	),
	'wpcseo_twitter_image'       => array(
		'meta'  => Meta::TWITTER_IMAGE,
		'label' => __( 'X/Twitter image URL', 'wp-custom-seo' ),
		'type'  => 'url',
		'help'  => __( 'Leave empty to use the Open Graph image.', 'wp-custom-seo' ),
	),
);

?>
<div class="wpcseo-panel" id="wpcseo-panel">
	<div class="wpcseo-tabs" role="tablist" aria-label="<?php esc_attr_e( 'SEO settings for this content', 'wp-custom-seo' ); ?>">
		<?php foreach ( $wpcseo_tabs as $wpcseo_id => $wpcseo_label ) : ?>
			<button
				type="button"
				role="tab"
				id="wpcseo-tab-<?php echo esc_attr( $wpcseo_id ); ?>"
				class="wpcseo-tab<?php echo 'general' === $wpcseo_id ? ' is-active' : ''; ?>"
				aria-controls="wpcseo-panel-<?php echo esc_attr( $wpcseo_id ); ?>"
				aria-selected="<?php echo 'general' === $wpcseo_id ? 'true' : 'false'; ?>"
				tabindex="<?php echo 'general' === $wpcseo_id ? '0' : '-1'; ?>"
			><?php echo esc_html( $wpcseo_label ); ?></button>
		<?php endforeach; ?>
	</div>

	<div
		class="wpcseo-tabpanel"
		id="wpcseo-panel-general"
		role="tabpanel"
		aria-labelledby="wpcseo-tab-general"
	>
		<p class="wpcseo-field">
			<label for="wpcseo_focus_keyword"><?php esc_html_e( 'Focus keyphrase', 'wp-custom-seo' ); ?></label>
			<input
				type="text"
				id="wpcseo_focus_keyword"
				name="wpcseo_focus_keyword"
				class="widefat"
				value="<?php echo esc_attr( (string) $values[ Meta::FOCUS_KEYWORD ] ); ?>"
				aria-describedby="wpcseo_focus_keyword_help"
			>
			<span class="description" id="wpcseo_focus_keyword_help">
				<?php esc_html_e( 'The phrase someone would search for to find this page. Used by the checks below.', 'wp-custom-seo' ); ?>
			</span>
		</p>

		<p class="wpcseo-field">
			<label for="wpcseo_title"><?php esc_html_e( 'SEO title', 'wp-custom-seo' ); ?></label>
			<input
				type="text"
				id="wpcseo_title"
				name="wpcseo_title"
				class="widefat"
				value="<?php echo esc_attr( (string) $values[ Meta::TITLE ] ); ?>"
				aria-describedby="wpcseo_title_help"
			>
			<span class="description" id="wpcseo_title_help">
				<?php esc_html_e( 'Leave empty to use the title template from the plugin settings. Supports %%title%%, %%sitename%% and %%sep%%.', 'wp-custom-seo' ); ?>
				<span class="wpcseo-counter" data-counter-for="wpcseo_title" data-min="30" data-max="60"></span>
			</span>
		</p>

		<p class="wpcseo-field">
			<label for="wpcseo_description"><?php esc_html_e( 'Meta description', 'wp-custom-seo' ); ?></label>
			<textarea
				id="wpcseo_description"
				name="wpcseo_description"
				class="widefat"
				rows="3"
				aria-describedby="wpcseo_description_help"
			><?php echo esc_textarea( (string) $values[ Meta::DESCRIPTION ] ); ?></textarea>
			<span class="description" id="wpcseo_description_help">
				<?php esc_html_e( 'Summarise the page and give a reason to click.', 'wp-custom-seo' ); ?>
				<span class="wpcseo-counter" data-counter-for="wpcseo_description" data-min="120" data-max="160"></span>
			</span>
		</p>

		<div class="wpcseo-preview" aria-live="polite">
			<h3><?php esc_html_e( 'Search result preview', 'wp-custom-seo' ); ?></h3>
			<p class="wpcseo-preview-note"><?php esc_html_e( 'An approximation. Search engines may show different text.', 'wp-custom-seo' ); ?></p>
			<div class="wpcseo-preview-card">
				<span class="wpcseo-preview-url" data-wpcseo="preview-url"></span>
				<span class="wpcseo-preview-title" data-wpcseo="preview-title"></span>
				<span class="wpcseo-preview-description" data-wpcseo="preview-description"></span>
			</div>
		</div>

		<?php if ( Settings::enabled( 'enable_analysis' ) ) : ?>
			<div class="wpcseo-analysis" data-wpcseo="analysis">
				<div class="wpcseo-analysis-header">
					<h3><?php esc_html_e( 'Optimization score', 'wp-custom-seo' ); ?></h3>
					<span class="wpcseo-score" data-wpcseo="score" aria-live="polite">–</span>
				</div>
				<p class="wpcseo-preview-note">
					<?php esc_html_e( 'This plugin\'s own score for how completely the page follows established on-page practices. It is not a prediction of search rankings.', 'wp-custom-seo' ); ?>
				</p>
				<ul class="wpcseo-checks" data-wpcseo="checks"></ul>
				<p class="wpcseo-analysis-status" data-wpcseo="status"></p>
			</div>
		<?php endif; ?>
	</div>

	<div
		class="wpcseo-tabpanel"
		id="wpcseo-panel-social"
		role="tabpanel"
		aria-labelledby="wpcseo-tab-social"
		hidden
	>
		<p class="wpcseo-preview-note">
			<?php esc_html_e( 'Every field here is optional. Anything left empty falls back to the SEO title, meta description and featured image, so a page shares sensibly without filling this in.', 'wp-custom-seo' ); ?>
		</p>

		<?php foreach ( $wpcseo_social_fields as $wpcseo_name => $wpcseo_field ) : ?>
			<p class="wpcseo-field">
				<label for="<?php echo esc_attr( $wpcseo_name ); ?>"><?php echo esc_html( $wpcseo_field['label'] ); ?></label>
				<?php if ( 'textarea' === $wpcseo_field['type'] ) : ?>
					<textarea
						id="<?php echo esc_attr( $wpcseo_name ); ?>"
						name="<?php echo esc_attr( $wpcseo_name ); ?>"
						class="widefat"
						rows="2"
						aria-describedby="<?php echo esc_attr( $wpcseo_name ); ?>_help"
					><?php echo esc_textarea( (string) $values[ $wpcseo_field['meta'] ] ); ?></textarea>
				<?php else : ?>
					<input
						type="<?php echo esc_attr( $wpcseo_field['type'] ); ?>"
						id="<?php echo esc_attr( $wpcseo_name ); ?>"
						name="<?php echo esc_attr( $wpcseo_name ); ?>"
						class="widefat"
						value="<?php echo esc_attr( (string) $values[ $wpcseo_field['meta'] ] ); ?>"
						aria-describedby="<?php echo esc_attr( $wpcseo_name ); ?>_help"
					>
				<?php endif; ?>
				<span class="description" id="<?php echo esc_attr( $wpcseo_name ); ?>_help">
					<?php echo esc_html( $wpcseo_field['help'] ); ?>
				</span>
			</p>
		<?php endforeach; ?>
	</div>

	<div
		class="wpcseo-tabpanel"
		id="wpcseo-panel-advanced"
		role="tabpanel"
		aria-labelledby="wpcseo-tab-advanced"
		hidden
	>
		<p class="wpcseo-field">
			<label for="wpcseo_canonical"><?php esc_html_e( 'Canonical URL', 'wp-custom-seo' ); ?></label>
			<input
				type="url"
				id="wpcseo_canonical"
				name="wpcseo_canonical"
				class="widefat"
				value="<?php echo esc_url( (string) $values[ Meta::CANONICAL ] ); ?>"
				placeholder="<?php echo esc_attr( (string) get_permalink( $post ) ); ?>"
				aria-describedby="wpcseo_canonical_help"
			>
			<span class="description" id="wpcseo_canonical_help">
				<?php esc_html_e( 'Point search engines at a different URL as the authoritative version. Leave empty unless this page duplicates another.', 'wp-custom-seo' ); ?>
			</span>
		</p>

		<p class="wpcseo-field">
			<label for="wpcseo_search_intent"><?php esc_html_e( 'Search intent', 'wp-custom-seo' ); ?></label>
			<select id="wpcseo_search_intent" name="wpcseo_search_intent" aria-describedby="wpcseo_search_intent_help">
				<?php foreach ( Meta::search_intents() as $wpcseo_value => $wpcseo_label ) : ?>
					<option value="<?php echo esc_attr( $wpcseo_value ); ?>" <?php selected( (string) $values[ Meta::SEARCH_INTENT ], $wpcseo_value ); ?>>
						<?php echo esc_html( $wpcseo_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description" id="wpcseo_search_intent_help">
				<?php esc_html_e( 'What the reader is trying to do. The AI review suggests one, but this field is yours and always wins.', 'wp-custom-seo' ); ?>
			</span>
		</p>

		<p class="wpcseo-field">
			<label for="wpcseo_breadcrumb_title"><?php esc_html_e( 'Breadcrumb title', 'wp-custom-seo' ); ?></label>
			<input
				type="text"
				id="wpcseo_breadcrumb_title"
				name="wpcseo_breadcrumb_title"
				class="widefat"
				value="<?php echo esc_attr( (string) $values[ Meta::BREADCRUMB_TITLE ] ); ?>"
				aria-describedby="wpcseo_breadcrumb_title_help"
			>
			<span class="description" id="wpcseo_breadcrumb_title_help">
				<?php esc_html_e( 'A shorter label for this page in breadcrumb trails. Leave empty to use the title.', 'wp-custom-seo' ); ?>
			</span>
		</p>

		<fieldset class="wpcseo-field">
			<legend><?php esc_html_e( 'Search engine directives', 'wp-custom-seo' ); ?></legend>

			<label for="wpcseo_noindex">
				<input
					type="checkbox"
					id="wpcseo_noindex"
					name="wpcseo_noindex"
					value="1"
					<?php checked( (bool) $values[ Meta::NOINDEX ], true ); ?>
				>
				<?php esc_html_e( 'Ask search engines not to index this content (noindex)', 'wp-custom-seo' ); ?>
			</label>

			<label for="wpcseo_nofollow">
				<input
					type="checkbox"
					id="wpcseo_nofollow"
					name="wpcseo_nofollow"
					value="1"
					<?php checked( (bool) $values[ Meta::NOFOLLOW ], true ); ?>
				>
				<?php esc_html_e( 'Ask search engines not to follow links on this page (nofollow)', 'wp-custom-seo' ); ?>
			</label>

			<label for="wpcseo_noarchive">
				<input
					type="checkbox"
					id="wpcseo_noarchive"
					name="wpcseo_noarchive"
					value="1"
					<?php checked( (bool) $values[ Meta::NOARCHIVE ], true ); ?>
				>
				<?php esc_html_e( 'Do not offer a cached copy of this page (noarchive)', 'wp-custom-seo' ); ?>
			</label>

			<label for="wpcseo_nosnippet">
				<input
					type="checkbox"
					id="wpcseo_nosnippet"
					name="wpcseo_nosnippet"
					value="1"
					<?php checked( (bool) $values[ Meta::NOSNIPPET ], true ); ?>
				>
				<?php esc_html_e( 'Show no text snippet for this page (nosnippet)', 'wp-custom-seo' ); ?>
			</label>

			<span class="description">
				<?php esc_html_e( 'These are requests, not guarantees, and they do not remove a page already indexed.', 'wp-custom-seo' ); ?>
			</span>
		</fieldset>

		<fieldset class="wpcseo-field">
			<legend><?php esc_html_e( 'Preview limits', 'wp-custom-seo' ); ?></legend>

			<?php
			$wpcseo_previews = array(
				'wpcseo_max_snippet'       => array(
					'meta'    => Meta::MAX_SNIPPET,
					'label'   => __( 'Text snippet length', 'wp-custom-seo' ),
					'options' => \WPCustomSeo\SEO\Robots::snippet_options(),
				),
				'wpcseo_max_image_preview' => array(
					'meta'    => Meta::MAX_IMAGE_PREVIEW,
					'label'   => __( 'Image preview size', 'wp-custom-seo' ),
					'options' => \WPCustomSeo\SEO\Robots::image_preview_options(),
				),
				'wpcseo_max_video_preview' => array(
					'meta'    => Meta::MAX_VIDEO_PREVIEW,
					'label'   => __( 'Video preview length', 'wp-custom-seo' ),
					'options' => \WPCustomSeo\SEO\Robots::video_preview_options(),
				),
			);

			foreach ( $wpcseo_previews as $wpcseo_name => $wpcseo_preview ) :
				?>
				<label for="<?php echo esc_attr( $wpcseo_name ); ?>"><?php echo esc_html( $wpcseo_preview['label'] ); ?></label>
				<select id="<?php echo esc_attr( $wpcseo_name ); ?>" name="<?php echo esc_attr( $wpcseo_name ); ?>">
					<?php foreach ( $wpcseo_preview['options'] as $wpcseo_value => $wpcseo_label ) : ?>
						<option
							value="<?php echo esc_attr( (string) $wpcseo_value ); ?>"
							<?php selected( (string) $values[ $wpcseo_preview['meta'] ], (string) $wpcseo_value ); ?>
						><?php echo esc_html( (string) $wpcseo_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endforeach; ?>

			<span class="description">
				<?php esc_html_e( 'How much of this page a search engine may show in a result. “Say nothing” leaves the decision to the search engine, which is usually the right answer — these exist for pages under a licence that restricts how much may be reproduced.', 'wp-custom-seo' ); ?>
			</span>
		</fieldset>
	</div>

	<div
		class="wpcseo-tabpanel"
		id="wpcseo-panel-schema"
		role="tabpanel"
		aria-labelledby="wpcseo-tab-schema"
		hidden
	>
		<p class="wpcseo-field">
			<label for="wpcseo_schema_type"><?php esc_html_e( 'Schema type', 'wp-custom-seo' ); ?></label>
			<select id="wpcseo_schema_type" name="wpcseo_schema_type" aria-describedby="wpcseo_schema_type_help">
				<?php foreach ( Meta::schema_types() as $wpcseo_value => $wpcseo_label ) : ?>
					<option
						value="<?php echo esc_attr( $wpcseo_value ); ?>"
						<?php selected( (string) $values[ Meta::SCHEMA_TYPE ], $wpcseo_value ); ?>
					><?php echo esc_html( $wpcseo_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description" id="wpcseo_schema_type_help">
				<?php esc_html_e( 'Automatic picks a type from the post type. Choose another only when it genuinely describes this page — structured data that disagrees with the visible content can cause manual action.', 'wp-custom-seo' ); ?>
			</span>
		</p>

		<p class="wpcseo-preview-note">
			<?php
			printf(
				/* translators: %s: link to the Schema screen. */
				esc_html__( 'Author and organization entities come from the user profile and %s. Validate the finished graph there.', 'wp-custom-seo' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wp-custom-seo-schema' ) ) . '">' . esc_html__( 'SEO → Schema', 'wp-custom-seo' ) . '</a>'
			);
			?>
		</p>

		<?php if ( Settings::enabled( \WPCustomSeo\Schema\Video::SETTING ) ) : ?>
			<?php
			$wpcseo_video    = \WPCustomSeo\Schema\Video::class;
			$wpcseo_embedded = $wpcseo_video::has_embed( (string) $post->post_content );
			?>
			<fieldset class="wpcseo-field">
				<legend><?php esc_html_e( 'Video', 'wp-custom-seo' ); ?></legend>

				<label for="wpcseo_video_enabled">
					<input
						type="checkbox"
						id="wpcseo_video_enabled"
						name="wpcseo_video_enabled"
						value="1"
						<?php checked( (bool) get_post_meta( $post->ID, $wpcseo_video::ENABLED, true ), true ); ?>
					>
					<?php esc_html_e( 'Describe a video on this page', 'wp-custom-seo' ); ?>
				</label>

				<?php if ( ! $wpcseo_embedded ) : ?>
					<span class="description">
						<?php esc_html_e( 'No video embed was found in this content. Nothing will be published until there is one — structured data for a video that is not on the page is a claim the page does not support.', 'wp-custom-seo' ); ?>
					</span>
				<?php endif; ?>

				<?php
				$wpcseo_video_fields = array(
					'wpcseo_video_name'        => array(
						'meta'  => $wpcseo_video::NAME,
						'label' => __( 'Video title', 'wp-custom-seo' ),
						'type'  => 'text',
						'help'  => __( 'Required. The title of the video itself, which is often not the title of this page.', 'wp-custom-seo' ),
					),
					'wpcseo_video_description' => array(
						'meta'  => $wpcseo_video::DESCRIPTION,
						'label' => __( 'Video description', 'wp-custom-seo' ),
						'type'  => 'textarea',
						'help'  => __( 'Required. What the video shows.', 'wp-custom-seo' ),
					),
					'wpcseo_video_thumbnail'   => array(
						'meta'  => $wpcseo_video::THUMBNAIL,
						'label' => __( 'Thumbnail URL', 'wp-custom-seo' ),
						'type'  => 'url',
						'help'  => __( 'Required. An absolute URL to a still from the video.', 'wp-custom-seo' ),
					),
					'wpcseo_video_upload_date' => array(
						'meta'  => $wpcseo_video::UPLOAD_DATE,
						'label' => __( 'Upload date', 'wp-custom-seo' ),
						'type'  => 'text',
						'help'  => __( 'Required. When the video was published, as YYYY-MM-DD. This is the video’s date, not this page’s.', 'wp-custom-seo' ),
					),
					'wpcseo_video_duration'    => array(
						'meta'  => $wpcseo_video::DURATION,
						'label' => __( 'Duration', 'wp-custom-seo' ),
						'type'  => 'text',
						'help'  => __( 'Optional, in ISO 8601 form — PT4M13S is four minutes thirteen seconds.', 'wp-custom-seo' ),
					),
					'wpcseo_video_content_url' => array(
						'meta'  => $wpcseo_video::CONTENT_URL,
						'label' => __( 'Content URL', 'wp-custom-seo' ),
						'type'  => 'url',
						'help'  => __( 'Optional. A direct link to the video file.', 'wp-custom-seo' ),
					),
					'wpcseo_video_embed_url'   => array(
						'meta'  => $wpcseo_video::EMBED_URL,
						'label' => __( 'Embed URL', 'wp-custom-seo' ),
						'type'  => 'url',
						'help'  => __( 'Optional. The player URL, as used in an iframe.', 'wp-custom-seo' ),
					),
					'wpcseo_video_transcript'  => array(
						'meta'  => $wpcseo_video::TRANSCRIPT,
						'label' => __( 'Transcript', 'wp-custom-seo' ),
						'type'  => 'textarea',
						'help'  => __( 'Optional. Paste it only if the transcript is genuinely of this video.', 'wp-custom-seo' ),
					),
				);

				foreach ( $wpcseo_video_fields as $wpcseo_name => $wpcseo_field ) :
					$wpcseo_value = (string) get_post_meta( $post->ID, $wpcseo_field['meta'], true );
					?>
					<label for="<?php echo esc_attr( $wpcseo_name ); ?>"><?php echo esc_html( $wpcseo_field['label'] ); ?></label>
					<?php if ( 'textarea' === $wpcseo_field['type'] ) : ?>
						<textarea
							id="<?php echo esc_attr( $wpcseo_name ); ?>"
							name="<?php echo esc_attr( $wpcseo_name ); ?>"
							class="widefat"
							rows="3"
						><?php echo esc_textarea( $wpcseo_value ); ?></textarea>
					<?php else : ?>
						<input
							type="<?php echo esc_attr( $wpcseo_field['type'] ); ?>"
							id="<?php echo esc_attr( $wpcseo_name ); ?>"
							name="<?php echo esc_attr( $wpcseo_name ); ?>"
							class="widefat"
							value="<?php echo esc_attr( $wpcseo_value ); ?>"
						>
					<?php endif; ?>
					<span class="description"><?php echo esc_html( $wpcseo_field['help'] ); ?></span>
				<?php endforeach; ?>

				<span class="description">
					<?php esc_html_e( 'The four required fields are required by structured data guidance, not by this plugin. If any is empty nothing is published, because a VideoObject missing them would be rejected and reported as an error against the site.', 'wp-custom-seo' ); ?>
				</span>
			</fieldset>
		<?php endif; ?>
	</div>

	<div
		class="wpcseo-tabpanel"
		id="wpcseo-panel-ai"
		role="tabpanel"
		aria-labelledby="wpcseo-tab-ai"
		hidden
	>
		<?php if ( ! \WPCustomSeo\AI\Manager::is_ready() ) : ?>
			<p>
				<?php esc_html_e( 'AI features are off. Choose a provider and save an API key to switch them on.', 'wp-custom-seo' ); ?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-custom-seo-ai' ) ); ?>">
					<?php esc_html_e( 'Set up AI', 'wp-custom-seo' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p class="wpcseo-preview-note">
				<?php echo esc_html( \WPCustomSeo\AI\Manager::privacy_notice( \WPCustomSeo\AI\Manager::provider() ) ); ?>
			</p>

			<p class="wpcseo-field">
				<button type="button" class="button" data-wpcseo-ai="title">
					<?php esc_html_e( 'Suggest SEO titles', 'wp-custom-seo' ); ?>
				</button>
				<button type="button" class="button" data-wpcseo-ai="meta-description">
					<?php esc_html_e( 'Suggest meta descriptions', 'wp-custom-seo' ); ?>
				</button>
				<button type="button" class="button" data-wpcseo-ai="keywords">
					<?php esc_html_e( 'Suggest keywords', 'wp-custom-seo' ); ?>
				</button>
				<button type="button" class="button" data-wpcseo-ai="content-analysis">
					<?php esc_html_e( 'Review this content', 'wp-custom-seo' ); ?>
				</button>
				<button type="button" class="button" data-wpcseo-ai="internal-links">
					<?php esc_html_e( 'Suggest internal links', 'wp-custom-seo' ); ?>
				</button>
				<button type="button" class="button" data-wpcseo-ai="faq">
					<?php esc_html_e( 'Draft an FAQ', 'wp-custom-seo' ); ?>
				</button>
			</p>

			<p class="wpcseo-analysis-status" data-wpcseo="ai-status" aria-live="polite"></p>

			<ul class="wpcseo-suggestions" data-wpcseo="ai-suggestions"></ul>
			<div class="wpcseo-ai-report" data-wpcseo="ai-report"></div>

			<p class="wpcseo-preview-note">
				<?php esc_html_e( 'Suggestions are drafts, not facts. Read each one against the page before applying it — a model can produce a confident claim the page does not support. Nothing is saved until you apply a suggestion and save the post.', 'wp-custom-seo' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<div
		class="wpcseo-tabpanel"
		id="wpcseo-panel-search"
		role="tabpanel"
		aria-labelledby="wpcseo-tab-search"
		hidden
	>
		<p>
			<?php esc_html_e( 'What Google reports this page was actually shown for. Nothing is fetched until you ask, and nothing here is estimated by this plugin.', 'wp-custom-seo' ); ?>
		</p>

		<p class="wpcseo-field">
			<button type="button" class="button" data-wpcseo="performance-load">
				<?php esc_html_e( 'Load search performance', 'wp-custom-seo' ); ?>
			</button>
		</p>

		<p class="wpcseo-analysis-status" data-wpcseo="performance-status" aria-live="polite"></p>

		<div data-wpcseo="performance"></div>
	</div>
</div>

<?php
/**
 * Location editor panel.
 *
 * @package WPCustomSeo
 *
 * @var WP_Post                                                            $post   Location being edited.
 * @var array<string, string>                                              $values Meta values keyed by meta key.
 * @var array<string, array{closed: bool, open: string, close: string}>    $hours  Opening hours.
 */

use WPCustomSeo\Local\Locations;

defined( 'ABSPATH' ) || exit;

$wpcseo_text_fields = array(
	Locations::STREET      => __( 'Street address', 'wp-custom-seo' ),
	Locations::LOCALITY    => __( 'Town or city', 'wp-custom-seo' ),
	Locations::REGION      => __( 'Region, county or state', 'wp-custom-seo' ),
	Locations::POSTCODE    => __( 'Postal code', 'wp-custom-seo' ),
	Locations::COUNTRY     => __( 'Country', 'wp-custom-seo' ),
	Locations::PHONE       => __( 'Telephone', 'wp-custom-seo' ),
	Locations::EMAIL       => __( 'Email', 'wp-custom-seo' ),
	Locations::PRICE_RANGE => __( 'Price range', 'wp-custom-seo' ),
	Locations::LATITUDE    => __( 'Latitude', 'wp-custom-seo' ),
	Locations::LONGITUDE   => __( 'Longitude', 'wp-custom-seo' ),
	Locations::IMAGE       => __( 'Image URL', 'wp-custom-seo' ),
);

?>
<div class="wpcseo-panel">
	<p class="wpcseo-preview-note">
		<?php esc_html_e( 'Only fields you fill in are published as structured data. Leave anything that does not apply empty rather than guessing.', 'wp-custom-seo' ); ?>
	</p>

	<p class="wpcseo-field">
		<label for="wpcseo_business_type"><?php esc_html_e( 'Business type', 'wp-custom-seo' ); ?></label>
		<select id="wpcseo_business_type" name="wpcseo_business_type">
			<?php foreach ( Locations::business_types() as $wpcseo_key => $wpcseo_label ) : ?>
				<option value="<?php echo esc_attr( $wpcseo_key ); ?>" <?php selected( $values[ Locations::TYPE ], $wpcseo_key ); ?>>
					<?php echo esc_html( $wpcseo_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<span class="description"><?php esc_html_e( 'Choose the most specific type that is genuinely accurate.', 'wp-custom-seo' ); ?></span>
	</p>

	<?php foreach ( $wpcseo_text_fields as $wpcseo_key => $wpcseo_label ) : ?>
		<?php $wpcseo_name = 'wpcseo_' . ltrim( $wpcseo_key, '_' ); ?>
		<p class="wpcseo-field">
			<label for="<?php echo esc_attr( $wpcseo_name ); ?>"><?php echo esc_html( $wpcseo_label ); ?></label>
			<input
				type="text"
				id="<?php echo esc_attr( $wpcseo_name ); ?>"
				name="<?php echo esc_attr( $wpcseo_name ); ?>"
				class="widefat"
				value="<?php echo esc_attr( $values[ $wpcseo_key ] ); ?>"
			>
		</p>
	<?php endforeach; ?>

	<p class="wpcseo-field">
		<label for="wpcseo_location_sameas"><?php esc_html_e( 'Profile URLs', 'wp-custom-seo' ); ?></label>
		<textarea id="wpcseo_location_sameas" name="wpcseo_location_sameas" rows="3" class="large-text code"><?php echo esc_textarea( $values[ Locations::SAME_AS ] ); ?></textarea>
		<span class="description"><?php esc_html_e( 'One absolute URL per line. Invalid lines are discarded.', 'wp-custom-seo' ); ?></span>
	</p>

	<fieldset class="wpcseo-field">
		<legend><?php esc_html_e( 'Opening hours', 'wp-custom-seo' ); ?></legend>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Day', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Opens', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Closes', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Closed all day', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( Locations::days() as $wpcseo_day => $wpcseo_day_label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $wpcseo_day_label ); ?></th>
						<td>
							<label class="screen-reader-text" for="wpcseo_open_<?php echo esc_attr( $wpcseo_day ); ?>">
								<?php echo esc_html( $wpcseo_day_label . ' ' . __( 'opens', 'wp-custom-seo' ) ); ?>
							</label>
							<input type="time" id="wpcseo_open_<?php echo esc_attr( $wpcseo_day ); ?>"
								name="wpcseo_hours[<?php echo esc_attr( $wpcseo_day ); ?>][open]"
								value="<?php echo esc_attr( $hours[ $wpcseo_day ]['open'] ); ?>">
						</td>
						<td>
							<label class="screen-reader-text" for="wpcseo_close_<?php echo esc_attr( $wpcseo_day ); ?>">
								<?php echo esc_html( $wpcseo_day_label . ' ' . __( 'closes', 'wp-custom-seo' ) ); ?>
							</label>
							<input type="time" id="wpcseo_close_<?php echo esc_attr( $wpcseo_day ); ?>"
								name="wpcseo_hours[<?php echo esc_attr( $wpcseo_day ); ?>][close]"
								value="<?php echo esc_attr( $hours[ $wpcseo_day ]['close'] ); ?>">
						</td>
						<td>
							<label for="wpcseo_closed_<?php echo esc_attr( $wpcseo_day ); ?>">
								<input type="checkbox" value="1"
									id="wpcseo_closed_<?php echo esc_attr( $wpcseo_day ); ?>"
									name="wpcseo_hours[<?php echo esc_attr( $wpcseo_day ); ?>][closed]"
									<?php checked( $hours[ $wpcseo_day ]['closed'], true ); ?>>
								<span class="screen-reader-text">
									<?php echo esc_html( $wpcseo_day_label . ' ' . __( 'closed all day', 'wp-custom-seo' ) ); ?>
								</span>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<span class="description">
			<?php esc_html_e( 'A day left blank is simply not published — that is different from stating the business is closed.', 'wp-custom-seo' ); ?>
		</span>
	</fieldset>
</div>

<?php
/**
 * Front-end business details.
 *
 * @package WPCustomSeo
 *
 * @var WP_Post[] $locations Locations to render.
 */

use WPCustomSeo\Local\Locations;

defined( 'ABSPATH' ) || exit;

?>
<div class="wpcseo-business">
	<?php foreach ( $locations as $wpcseo_location ) : ?>
		<?php
		$wpcseo_id      = (int) $wpcseo_location->ID;
		$wpcseo_street  = (string) get_post_meta( $wpcseo_id, Locations::STREET, true );
		$wpcseo_city    = (string) get_post_meta( $wpcseo_id, Locations::LOCALITY, true );
		$wpcseo_region  = (string) get_post_meta( $wpcseo_id, Locations::REGION, true );
		$wpcseo_post    = (string) get_post_meta( $wpcseo_id, Locations::POSTCODE, true );
		$wpcseo_country = (string) get_post_meta( $wpcseo_id, Locations::COUNTRY, true );
		$wpcseo_phone   = (string) get_post_meta( $wpcseo_id, Locations::PHONE, true );
		$wpcseo_email   = (string) get_post_meta( $wpcseo_id, Locations::EMAIL, true );
		$wpcseo_hours   = Locations::hours( $wpcseo_id );
		$wpcseo_lines   = array_filter( array( $wpcseo_street, $wpcseo_city, $wpcseo_region, $wpcseo_post, $wpcseo_country ) );
		?>
		<section class="wpcseo-business__item">
			<h3 class="wpcseo-business__name"><?php echo esc_html( get_the_title( $wpcseo_location ) ); ?></h3>

			<?php if ( $wpcseo_lines ) : ?>
				<address class="wpcseo-business__address">
					<?php echo esc_html( implode( ', ', $wpcseo_lines ) ); ?>
				</address>
			<?php endif; ?>

			<?php if ( '' !== $wpcseo_phone ) : ?>
				<p class="wpcseo-business__phone">
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $wpcseo_phone ) ?? '' ); ?>">
						<?php echo esc_html( $wpcseo_phone ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( is_email( $wpcseo_email ) ) : ?>
				<p class="wpcseo-business__email">
					<a href="mailto:<?php echo esc_attr( $wpcseo_email ); ?>"><?php echo esc_html( $wpcseo_email ); ?></a>
				</p>
			<?php endif; ?>

			<?php
			$wpcseo_stated = array_filter(
				$wpcseo_hours,
				static fn ( array $row ): bool => $row['closed'] || ( '' !== $row['open'] && '' !== $row['close'] )
			);
			?>

			<?php if ( $wpcseo_stated ) : ?>
				<table class="wpcseo-business__hours">
					<caption><?php esc_html_e( 'Opening hours', 'wp-custom-seo' ); ?></caption>
					<tbody>
						<?php foreach ( $wpcseo_stated as $wpcseo_day => $wpcseo_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( Locations::days()[ $wpcseo_day ] ?? $wpcseo_day ); ?></th>
								<td>
									<?php
									echo $wpcseo_row['closed']
										? esc_html__( 'Closed', 'wp-custom-seo' )
										: esc_html( $wpcseo_row['open'] . '–' . $wpcseo_row['close'] );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>

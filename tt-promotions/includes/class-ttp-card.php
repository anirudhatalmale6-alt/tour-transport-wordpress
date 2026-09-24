<?php
/**
 * One deal, reduced to the handful of values a card needs.
 *
 * Both the grid and the sidebar widget read this, so a deal never looks
 * inconsistent between the two.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Card {

	/**
	 * Build the view data for a deal.
	 *
	 * @param WP_Post $post Deal.
	 * @return array
	 */
	public static function build( $post ) {
		$link_url  = TTP_Meta::get( $post->ID, '_ttp_link_url' );
		$link_text = TTP_Meta::get( $post->ID, '_ttp_link_text' );

		return array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'text'       => self::text( $post ),
			'image_id'   => (int) get_post_thumbnail_id( $post ),
			'price'      => TTP_Meta::get( $post->ID, '_ttp_price' ),
			'price_note' => TTP_Meta::get( $post->ID, '_ttp_price_note' ),
			'old_price'  => TTP_Meta::get( $post->ID, '_ttp_old_price' ),
			'badge'      => TTP_Meta::get( $post->ID, '_ttp_badge' ),
			'link_url'   => $link_url,
			'link_text'  => $link_text ? $link_text : __( 'Book this deal', 'tt-promotions' ),
			'end'        => TTP_Meta::get( $post->ID, '_ttp_end' ),
		);
	}

	/**
	 * The short description: the excerpt if there is one, otherwise a trim of
	 * the editor content.
	 *
	 * @param WP_Post $post Deal.
	 * @return string
	 */
	protected static function text( $post ) {
		if ( $post->post_excerpt ) {
			return $post->post_excerpt;
		}

		return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 28 );
	}

	/**
	 * The deal image, with the deal title as its alt text when the media
	 * library has none - the SEO basics ask for alt tags everywhere.
	 *
	 * @param array  $card Card data.
	 * @param string $size Image size.
	 * @return string
	 */
	public static function image( $card, $size = 'medium_large' ) {
		if ( ! $card['image_id'] ) {
			return '';
		}

		$alt = trim( (string) get_post_meta( $card['image_id'], '_wp_attachment_image_alt', true ) );

		return wp_get_attachment_image(
			$card['image_id'],
			$size,
			false,
			array(
				'alt'     => $alt ? $alt : $card['title'],
				'loading' => 'lazy',
				'class'   => 'ttp-card__img',
			)
		);
	}

	/**
	 * "Ends 30 September" for a deal with an end date in the future.
	 *
	 * @param array $card Card data.
	 * @return string
	 */
	public static function ends_label( $card ) {
		if ( empty( $card['end'] ) ) {
			return '';
		}

		$timestamp = strtotime( $card['end'] );

		if ( ! $timestamp ) {
			return '';
		}

		return sprintf(
			/* translators: %s: end date */
			__( 'Ends %s', 'tt-promotions' ),
			date_i18n( get_option( 'date_format' ), $timestamp )
		);
	}
}

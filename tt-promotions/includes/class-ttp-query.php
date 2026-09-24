<?php
/**
 * Fetching deals.
 *
 * "Live" means published, already started and not past its end date - worked
 * out against the site's own timezone, not the server's.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Query {

	/**
	 * The meta conditions that make a deal current.
	 *
	 * @return array
	 */
	protected static function window_clauses() {
		$today = current_time( 'Y-m-d' );

		return array(
			array(
				'relation' => 'OR',
				array(
					'key'     => '_ttp_start',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_ttp_start',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => '_ttp_start',
					'value'   => $today,
					'compare' => '<=',
					'type'    => 'DATE',
				),
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_ttp_end',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_ttp_end',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => '_ttp_end',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);
	}

	/**
	 * Live deals.
	 *
	 * @param array $args limit (int), featured (bool), ids (int[]).
	 * @return WP_Post[]
	 */
	public static function live( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'    => -1,
				'featured' => false,
				'ids'      => array(),
			)
		);

		$meta = self::window_clauses();

		if ( $args['featured'] ) {
			$meta[] = array(
				'key'     => '_ttp_featured',
				'value'   => '1',
				'compare' => '=',
			);
		}

		$meta['relation'] = 'AND';

		$query = array(
			'post_type'              => TTP_Post_Type::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $args['limit'],
			'orderby'                => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'meta_query'             => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $args['ids'] ) ) {
			$query['post__in'] = array_map( 'absint', $args['ids'] );
		}

		return get_posts( $query );
	}

	/**
	 * Whether a deal's end date has passed - used for the admin badge.
	 *
	 * @param int $post_id Deal ID.
	 * @return bool
	 */
	public static function is_expired( $post_id ) {
		$end = TTP_Meta::get( $post_id, '_ttp_end' );

		return $end && $end < current_time( 'Y-m-d' );
	}

	/**
	 * Whether a deal has not started yet - used for the admin badge.
	 *
	 * @param int $post_id Deal ID.
	 * @return bool
	 */
	public static function is_scheduled( $post_id ) {
		$start = TTP_Meta::get( $post_id, '_ttp_start' );

		return $start && $start > current_time( 'Y-m-d' );
	}
}

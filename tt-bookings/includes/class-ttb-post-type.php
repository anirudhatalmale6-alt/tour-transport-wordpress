<?php
/**
 * Every submission is stored as a post so nothing is lost if an email bounces.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Post_Type {

	const POST_TYPE = 'tt_booking';

	/** Meta key holding the whole submission as an associative array. */
	const META_DATA = '_ttb_data';

	/** Meta key holding the form type: airport | tour | hire. */
	const META_TYPE = '_ttb_type';

	/** Meta key holding the handling status: new | confirmed | cancelled. */
	const META_STATUS = '_ttb_status';

	/** Meta key holding the submitter IP, kept for spam triage. */
	const META_IP = '_ttb_ip';

	/** Meta key recording what happened to each of the two emails. */
	const META_MAIL = '_ttb_mail_log';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Bookings', 'tt-bookings' ),
					'singular_name'      => __( 'Booking', 'tt-bookings' ),
					'menu_name'          => __( 'Bookings', 'tt-bookings' ),
					'all_items'          => __( 'All Bookings', 'tt-bookings' ),
					'search_items'       => __( 'Search bookings', 'tt-bookings' ),
					'not_found'          => __( 'No bookings yet.', 'tt-bookings' ),
					'not_found_in_trash' => __( 'No bookings in the trash.', 'tt-bookings' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-tickets-alt',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					// Bookings arrive from the front end; nobody hand-writes one.
					'create_posts' => 'do_not_allow',
				),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/**
	 * Persist one submission.
	 *
	 * @param string $type   Form type.
	 * @param array  $data   Sanitised field values keyed by field key.
	 * @param string $ip     Submitter IP.
	 * @return int|WP_Error Post ID on success.
	 */
	public static function store( $type, $data, $ip = '' ) {
		$form  = TTB_Fields::get_form( $type );
		$label = $form ? $form['label'] : $type;

		$name = isset( $data['full_name'] ) ? $data['full_name'] : __( 'Unnamed', 'tt-bookings' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s - %s', $label, $name ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_TYPE, $type );
		update_post_meta( $post_id, self::META_DATA, $data );
		update_post_meta( $post_id, self::META_STATUS, 'new' );
		update_post_meta( $post_id, self::META_IP, $ip );

		return $post_id;
	}

	/**
	 * Human-readable reference shown to the customer and in the admin.
	 *
	 * @param int $post_id Booking post ID.
	 * @return string
	 */
	public static function reference( $post_id ) {
		$type   = get_post_meta( $post_id, self::META_TYPE, true );
		$prefix = array(
			'airport' => 'AT',
			'tour'    => 'TR',
			'hire'    => 'HV',
		);

		$code = isset( $prefix[ $type ] ) ? $prefix[ $type ] : 'BK';

		return sprintf( '%s-%s', $code, str_pad( (string) $post_id, 5, '0', STR_PAD_LEFT ) );
	}

	/**
	 * The handling statuses a booking can be in.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'new'       => __( 'New', 'tt-bookings' ),
			'confirmed' => __( 'Confirmed', 'tt-bookings' ),
			'cancelled' => __( 'Cancelled', 'tt-bookings' ),
		);
	}
}

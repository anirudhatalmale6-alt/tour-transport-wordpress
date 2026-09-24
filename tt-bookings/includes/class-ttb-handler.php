<?php
/**
 * Receives a submission, validates it, stores it and triggers the two emails.
 *
 * The same core runs for both the JavaScript path (admin-ajax) and the
 * no-JavaScript fallback (admin-post), so the form works either way.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Handler {

	/** Submissions allowed from one IP inside the rate-limit window. */
	const RATE_LIMIT = 5;

	/** Rate-limit window in seconds. */
	const RATE_WINDOW = 600;

	/** A human needs at least this many seconds to fill the form in. */
	const MIN_SECONDS = 3;

	public static function init() {
		add_action( 'admin_post_nopriv_ttb_submit', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_post_ttb_submit', array( __CLASS__, 'handle_post' ) );
		add_action( 'wp_ajax_nopriv_ttb_submit', array( __CLASS__, 'handle_ajax' ) );
		add_action( 'wp_ajax_ttb_submit', array( __CLASS__, 'handle_ajax' ) );
	}

	/** JavaScript path. */
	public static function handle_ajax() {
		$result = self::process();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'fields'  => (array) $result->get_error_data(),
				),
				200
			);
		}

		wp_send_json_success( $result );
	}

	/** No-JavaScript fallback: process, then redirect back to the page. */
	public static function handle_post() {
		$result   = self::process();
		$redirect = self::source_url();

		if ( is_wp_error( $result ) ) {
			$token = wp_generate_password( 12, false, false );
			set_transient(
				'ttb_replay_' . $token,
				array(
					'type'    => self::posted_type(),
					'values'  => self::raw_values(),
					'errors'  => (array) $result->get_error_data(),
					'message' => $result->get_error_message(),
				),
				15 * MINUTE_IN_SECONDS
			);

			$redirect = add_query_arg( 'ttb_token', $token, $redirect ) . '#ttb';
		} else {
			$redirect = add_query_arg(
				array(
					'ttb_ok'  => $result['type'],
					'ttb_ref' => rawurlencode( $result['reference'] ),
				),
				$redirect
			) . '#ttb';
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * The page the form was submitted from, restricted to this site.
	 *
	 * @return string
	 */
	protected static function source_url() {
		$source = isset( $_POST['ttb_source'] ) ? esc_url_raw( wp_unslash( $_POST['ttb_source'] ) ) : '';

		if ( $source && 0 === strpos( $source, home_url() ) ) {
			return remove_query_arg( array( 'ttb_token', 'ttb_ok', 'ttb_ref' ), $source );
		}

		return home_url( '/' );
	}

	/**
	 * The submitted form type, if it is one we know about.
	 *
	 * @return string
	 */
	protected static function posted_type() {
		$type = isset( $_POST['ttb_type'] ) ? sanitize_key( wp_unslash( $_POST['ttb_type'] ) ) : '';
		return TTB_Fields::get_form( $type ) ? $type : '';
	}

	/**
	 * The raw posted values, lightly sanitised, for redisplay after an error.
	 *
	 * @return array
	 */
	protected static function raw_values() {
		$posted = isset( $_POST['ttb'] ) && is_array( $_POST['ttb'] ) ? wp_unslash( $_POST['ttb'] ) : array();
		$clean  = array();

		foreach ( $posted as $key => $value ) {
			$key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$clean[ $key ] = sanitize_textarea_field( $value );
			}
		}

		if ( ! empty( $_POST['ttb_consent'] ) ) {
			$clean['ttb_consent'] = '1';
		}

		return $clean;
	}

	/**
	 * Validate, store and mail one submission.
	 *
	 * @return array|WP_Error Success payload, or an error carrying per-field messages.
	 */
	protected static function process() {
		$type = self::posted_type();

		if ( ! $type ) {
			return new WP_Error( 'ttb_type', __( 'That booking form is not available.', 'tt-bookings' ) );
		}

		if ( ! TTB_Settings::form_enabled( $type ) ) {
			return new WP_Error( 'ttb_disabled', __( 'This booking form is currently switched off.', 'tt-bookings' ) );
		}

		$nonce = isset( $_POST['ttb_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ttb_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ttb_submit_' . $type ) ) {
			return new WP_Error(
				'ttb_nonce',
				__( 'This page has been open for a while. Please reload it and send the form again.', 'tt-bookings' )
			);
		}

		$spam = self::spam_check();
		if ( is_wp_error( $spam ) ) {
			return $spam;
		}

		$validated = self::validate( $type );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$post_id = TTB_Post_Type::store( $type, $validated, self::client_ip() );
		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'ttb_store', __( 'We could not save your request. Please try again.', 'tt-bookings' ) );
		}

		self::bump_rate_limit();

		$mail = TTB_Mailer::send_all( $post_id, $type, $validated );
		update_post_meta( $post_id, TTB_Post_Type::META_MAIL, $mail );

		$reference = TTB_Post_Type::reference( $post_id );

		/**
		 * Fires after a booking has been stored and its emails attempted.
		 *
		 * @param int    $post_id   Booking post ID.
		 * @param string $type      Form type.
		 * @param array  $validated Sanitised values.
		 */
		do_action( 'ttb_booking_created', $post_id, $type, $validated );

		return array(
			'type'      => $type,
			'reference' => $reference,
			'message'   => sprintf(
				/* translators: %s: booking reference */
				__( 'Your reference is %s. A confirmation is on its way to your inbox and our team will be in touch shortly.', 'tt-bookings' ),
				$reference
			),
		);
	}

	/**
	 * Honeypot, time trap and per-IP rate limit.
	 *
	 * @return true|WP_Error
	 */
	protected static function spam_check() {
		if ( ! empty( $_POST['ttb_website'] ) ) {
			return new WP_Error( 'ttb_spam', __( 'Your request could not be sent.', 'tt-bookings' ) );
		}

		$started = isset( $_POST['ttb_started'] ) ? absint( $_POST['ttb_started'] ) : 0;
		if ( $started && ( time() - $started ) < self::MIN_SECONDS ) {
			return new WP_Error( 'ttb_speed', __( 'That was a little too quick - please try again.', 'tt-bookings' ) );
		}

		/**
		 * Filter how many requests one IP may send inside the window.
		 *
		 * @param int $limit Default 5.
		 */
		$limit = (int) apply_filters( 'ttb_rate_limit', self::RATE_LIMIT );

		if ( $limit > 0 && self::rate_limit_hits() >= $limit ) {
			return new WP_Error(
				'ttb_rate',
				__( 'You have sent several requests already. Please give us a few minutes to reply before sending another.', 'tt-bookings' )
			);
		}

		return true;
	}

	/**
	 * How many submissions this IP has made inside the window.
	 *
	 * @return int
	 */
	protected static function rate_limit_hits() {
		$key = 'ttb_rl_' . md5( self::client_ip() );
		return (int) get_transient( $key );
	}

	/** Record one more submission against this IP. */
	protected static function bump_rate_limit() {
		$key = 'ttb_rl_' . md5( self::client_ip() );
		set_transient( $key, self::rate_limit_hits() + 1, self::RATE_WINDOW );
	}

	/**
	 * Validate every field in the form against its schema.
	 *
	 * @param string $type Form type.
	 * @return array|WP_Error
	 */
	protected static function validate( $type ) {
		$fields = TTB_Fields::flat_fields( $type );
		$posted = isset( $_POST['ttb'] ) && is_array( $_POST['ttb'] ) ? wp_unslash( $_POST['ttb'] ) : array();
		$errors = array();
		$clean  = array();

		foreach ( $fields as $key => $field ) {
			$raw = isset( $posted[ $key ] ) ? $posted[ $key ] : '';

			// A conditional field the visitor never saw is simply not collected.
			if ( ! empty( $field['show_if'] ) && ! self::condition_met( $field['show_if'], $posted ) ) {
				continue;
			}

			$value = self::sanitize_value( $field, $raw );
			$empty = is_array( $value ) ? empty( $value ) : ( '' === trim( (string) $value ) );

			if ( ! empty( $field['required'] ) && $empty ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'tt-bookings' ),
					$field['label']
				);
				continue;
			}

			if ( $empty ) {
				$clean[ $key ] = is_array( $value ) ? array() : '';
				continue;
			}

			$error = self::validate_value( $field, $value );
			if ( $error ) {
				$errors[ $key ] = $error;
				continue;
			}

			$clean[ $key ] = $value;
		}

		if ( TTB_Settings::get( 'consent_text' ) && empty( $_POST['ttb_consent'] ) ) {
			$errors['ttb_consent'] = __( 'Please tick the box to continue.', 'tt-bookings' );
		}

		if ( $errors ) {
			return new WP_Error(
				'ttb_validation',
				__( 'Please check the highlighted fields below.', 'tt-bookings' ),
				$errors
			);
		}

		return $clean;
	}

	/**
	 * Whether a conditional field's trigger matches what was posted.
	 *
	 * @param array $condition Map of field key => required value.
	 * @param array $posted    Raw posted values.
	 * @return bool
	 */
	protected static function condition_met( $condition, $posted ) {
		foreach ( $condition as $key => $expected ) {
			$actual = isset( $posted[ $key ] ) ? $posted[ $key ] : '';
			if ( (string) $actual !== (string) $expected ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitise one posted value according to its field type.
	 *
	 * @param array $field Field definition.
	 * @param mixed $raw   Raw posted value.
	 * @return mixed
	 */
	protected static function sanitize_value( $field, $raw ) {
		switch ( $field['type'] ) {

			case 'checkbox':
				$raw    = is_array( $raw ) ? $raw : array();
				$allowed = array_keys( $field['options'] );
				return array_values( array_intersect( array_map( 'sanitize_text_field', $raw ), $allowed ) );

			case 'select':
			case 'radio':
				$value   = sanitize_text_field( is_scalar( $raw ) ? $raw : '' );
				$allowed = array_map( 'strval', array_keys( $field['options'] ) );
				return in_array( $value, $allowed, true ) ? $value : '';

			case 'email':
				return sanitize_email( is_scalar( $raw ) ? $raw : '' );

			case 'number':
				return is_scalar( $raw ) && '' !== trim( (string) $raw ) ? (string) intval( $raw ) : '';

			case 'textarea':
				return sanitize_textarea_field( is_scalar( $raw ) ? $raw : '' );

			default:
				return sanitize_text_field( is_scalar( $raw ) ? $raw : '' );
		}
	}

	/**
	 * Type-specific validation of a non-empty value.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Sanitised value.
	 * @return string Error message, or an empty string when valid.
	 */
	protected static function validate_value( $field, $value ) {
		switch ( $field['type'] ) {

			case 'email':
				if ( ! is_email( $value ) ) {
					return __( 'Please enter a valid email address.', 'tt-bookings' );
				}
				break;

			case 'tel':
				if ( ! preg_match( '/^[0-9+().\-\s]{6,25}$/', $value ) ) {
					return __( 'Please enter a valid phone number.', 'tt-bookings' );
				}
				break;

			case 'number':
				$number = (int) $value;
				if ( isset( $field['min'] ) && is_numeric( $field['min'] ) && $number < (int) $field['min'] ) {
					return sprintf(
						/* translators: %d: minimum value */
						__( 'Please enter %d or more.', 'tt-bookings' ),
						(int) $field['min']
					);
				}
				if ( isset( $field['max'] ) && is_numeric( $field['max'] ) && $number > (int) $field['max'] ) {
					return sprintf(
						/* translators: %d: maximum value */
						__( 'Please enter %d or fewer.', 'tt-bookings' ),
						(int) $field['max']
					);
				}
				break;

			case 'date':
			case 'datetime-local':
				$timestamp = strtotime( $value );
				if ( ! $timestamp ) {
					return __( 'Please enter a valid date.', 'tt-bookings' );
				}
				// Compare against the site's own day, not the server's UTC day.
				$today = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
				if ( isset( $field['min'] ) && 'today' === $field['min'] && $timestamp < $today ) {
					return __( 'Please choose a date that has not passed.', 'tt-bookings' );
				}
				break;
		}

		return '';
	}

	/**
	 * Best available client IP, kept only for spam triage.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}

<?php
/**
 * Builds and sends the two emails: a confirmation to the customer and a
 * notification to the agency inbox.
 *
 * The HTML content type is switched on only around our own sends, so other
 * plugins' plain-text mail is left alone.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Mailer {

	/** Collects wp_mail failures for the delivery log. */
	protected static $last_error = '';

	/**
	 * Send both emails for one booking.
	 *
	 * @param int    $post_id Booking post ID.
	 * @param string $type    Form type.
	 * @param array  $data    Sanitised values.
	 * @return array Delivery log.
	 */
	public static function send_all( $post_id, $type, $data ) {
		$log = array(
			'customer' => array( 'sent' => false, 'to' => '', 'error' => '' ),
			'admin'    => array( 'sent' => false, 'to' => '', 'error' => '' ),
			'time'     => current_time( 'mysql' ),
		);

		$customer_email = isset( $data['email'] ) ? $data['email'] : '';
		if ( $customer_email && is_email( $customer_email ) ) {
			$log['customer']['to'] = $customer_email;
			$sent                  = self::send_customer( $post_id, $type, $data );
			$log['customer']['sent']  = $sent;
			$log['customer']['error'] = $sent ? '' : self::$last_error;
		}

		$recipients = TTB_Settings::recipients();
		if ( $recipients ) {
			$log['admin']['to'] = implode( ', ', $recipients );
			$sent               = self::send_admin( $post_id, $type, $data, $recipients );
			$log['admin']['sent']  = $sent;
			$log['admin']['error'] = $sent ? '' : self::$last_error;
		}

		return $log;
	}

	/**
	 * The confirmation the customer receives.
	 *
	 * @param int    $post_id Booking post ID.
	 * @param string $type    Form type.
	 * @param array  $data    Sanitised values.
	 * @return bool
	 */
	protected static function send_customer( $post_id, $type, $data ) {
		$form      = TTB_Fields::get_form( $type );
		$reference = TTB_Post_Type::reference( $post_id );

		$subject = TTB_Settings::get( 'customer_subject' );
		$subject = strtr(
			$subject,
			array(
				'{reference}' => $reference,
				'{type}'      => $form['label'],
				'{company}'   => TTB_Settings::company_name(),
			)
		);

		$body = self::render(
			'email-customer.php',
			array(
				'post_id'    => $post_id,
				'type'       => $type,
				'form'       => $form,
				'data'       => $data,
				'reference'  => $reference,
				'rows'       => TTB_Format::summary( $type, $data ),
				'highlights' => TTB_Format::highlights( $type, $data ),
			)
		);

		$headers = array(
			'Reply-To: ' . self::reply_to_agency(),
		);

		return self::send( $data['email'], $subject, $body, $headers );
	}

	/**
	 * The notification the agency receives.
	 *
	 * @param int      $post_id    Booking post ID.
	 * @param string   $type       Form type.
	 * @param array    $data       Sanitised values.
	 * @param string[] $recipients Agency inbox addresses.
	 * @return bool
	 */
	protected static function send_admin( $post_id, $type, $data, $recipients ) {
		$form      = TTB_Fields::get_form( $type );
		$reference = TTB_Post_Type::reference( $post_id );

		$subject = TTB_Settings::get( 'admin_subject' );
		$subject = strtr(
			$subject,
			array(
				'{reference}' => $reference,
				'{type}'      => $form['label'],
				'{name}'      => isset( $data['full_name'] ) ? $data['full_name'] : '',
			)
		);

		$body = self::render(
			'email-admin.php',
			array(
				'post_id'   => $post_id,
				'type'      => $type,
				'form'      => $form,
				'data'      => $data,
				'reference' => $reference,
				'rows'      => TTB_Format::summary( $type, $data ),
				// Built by hand: get_edit_post_link() returns nothing here,
				// because the request is made by a logged-out visitor.
				'edit_link' => $post_id
					? admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' )
					: '',
			)
		);

		$headers = array();
		if ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
			// Hitting reply in the inbox answers the customer directly.
			$name      = isset( $data['full_name'] ) ? $data['full_name'] : $data['email'];
			$headers[] = sprintf( 'Reply-To: %s <%s>', $name, $data['email'] );
		}

		return self::send( $recipients, $subject, $body, $headers );
	}

	/**
	 * Send one HTML email with our From identity applied.
	 *
	 * @param string|string[] $to      Recipients.
	 * @param string          $subject Subject line.
	 * @param string          $body    HTML body.
	 * @param string[]        $headers Extra headers.
	 * @return bool
	 */
	protected static function send( $to, $subject, $body, $headers = array() ) {
		self::$last_error = '';

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'content_type' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_error' ) );

		$sent = wp_mail( $to, $subject, $body, $headers );

		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'content_type' ) );
		remove_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ) );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
		remove_action( 'wp_mail_failed', array( __CLASS__, 'capture_error' ) );

		return (bool) $sent;
	}

	public static function content_type() {
		return 'text/html';
	}

	public static function from_email() {
		return TTB_Settings::from_email();
	}

	public static function from_name() {
		return TTB_Settings::company_name();
	}

	/**
	 * Remember why a send failed so the admin screen can show it.
	 *
	 * @param WP_Error $error Mail error.
	 */
	public static function capture_error( $error ) {
		self::$last_error = $error->get_error_message();
	}

	/**
	 * The address a customer's reply should reach.
	 *
	 * @return string
	 */
	protected static function reply_to_agency() {
		$recipients = TTB_Settings::recipients();
		$address    = $recipients ? $recipients[0] : get_option( 'admin_email' );

		return sprintf( '%s <%s>', TTB_Settings::company_name(), $address );
	}

	/**
	 * Render an email template.
	 *
	 * @param string $template Template file name.
	 * @param array  $vars     Variables exposed to the template.
	 * @return string
	 */
	protected static function render( $template, $vars ) {
		$path = TTB_PATH . 'templates/' . $template;

		/**
		 * Filter the template path so a theme can override the email design.
		 *
		 * @param string $path     Absolute template path.
		 * @param string $template Template file name.
		 */
		$path = apply_filters( 'ttb_email_template', $path, $template );

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$vars['brand']   = TTB_Settings::brand_colour();
		$vars['company'] = TTB_Settings::company_name();
		$vars['phone']   = TTB_Settings::get( 'company_phone' );
		$vars['site']    = home_url( '/' );
		$vars['logo']    = TTB_Settings::logo_url();

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables.

		ob_start();
		include $path;

		return ob_get_clean();
	}
}

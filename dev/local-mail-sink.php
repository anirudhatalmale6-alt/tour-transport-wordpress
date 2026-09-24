<?php
/**
 * Plugin Name: Local Mail Sink (development only)
 * Description: Writes every outgoing email to a file instead of sending it, so the booking emails can be inspected on this machine. NOT part of the deliverable.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) {
		$dir = defined( 'TTB_MAIL_SINK' ) ? TTB_MAIL_SINK : WP_CONTENT_DIR . '/mail-sink';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$to      = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'];
		$headers = is_array( $atts['headers'] ) ? implode( "\n", $atts['headers'] ) : $atts['headers'];

		$name = sprintf(
			'%s-%s.html',
			gmdate( 'Ymd-His' ),
			substr( md5( $to . $atts['subject'] . microtime() ), 0, 8 )
		);

		file_put_contents(
			trailingslashit( $dir ) . $name,
			"<!-- To: {$to}\n     Subject: {$atts['subject']}\n     Headers:\n{$headers}\n-->\n" . $atts['message']
		);

		return true;
	},
	10,
	2
);

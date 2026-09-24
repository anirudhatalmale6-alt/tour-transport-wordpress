<?php
/**
 * Turns stored values into the wording a human should read.
 *
 * Used by both emails and by the admin detail screen, so the customer, the
 * agency inbox and wp-admin always describe a booking the same way.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Format {

	/**
	 * One value, rendered for display.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Stored value.
	 * @return string
	 */
	public static function value( $field, $value ) {
		if ( is_array( $value ) ) {
			$labels = array();
			foreach ( $value as $item ) {
				$labels[] = isset( $field['options'][ $item ] ) ? $field['options'][ $item ] : $item;
			}
			return implode( ', ', $labels );
		}

		if ( '' === $value || null === $value ) {
			return '';
		}

		switch ( $field['type'] ) {

			case 'select':
			case 'radio':
				return isset( $field['options'][ $value ] ) ? $field['options'][ $value ] : (string) $value;

			case 'date':
				$timestamp = strtotime( $value );
				return $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : (string) $value;

			case 'datetime-local':
				$timestamp = strtotime( $value );
				return $timestamp
					? date_i18n( get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ), $timestamp )
					: (string) $value;

			case 'time':
				$timestamp = strtotime( $value );
				return $timestamp ? date_i18n( get_option( 'time_format' ), $timestamp ) : (string) $value;

			default:
				return (string) $value;
		}
	}

	/**
	 * A booking as an ordered list of label/value pairs, empties dropped.
	 *
	 * @param string $type Form type.
	 * @param array  $data Stored values.
	 * @return array List of array( 'label' => string, 'value' => string ).
	 */
	public static function summary( $type, $data ) {
		$fields = TTB_Fields::flat_fields( $type );
		$rows   = array();

		foreach ( $fields as $key => $field ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$display = self::value( $field, $data[ $key ] );
			if ( '' === trim( $display ) ) {
				continue;
			}

			$rows[] = array(
				'key'   => $key,
				'label' => $field['label'],
				'value' => $display,
			);
		}

		return $rows;
	}

	/**
	 * The few details worth repeating at the top of an email.
	 *
	 * @param string $type Form type.
	 * @param array  $data Stored values.
	 * @return array Label/value pairs, at most three.
	 */
	public static function highlights( $type, $data ) {
		$wanted = array(
			'airport' => array( 'pickup_date', 'pickup_time', 'passengers' ),
			'tour'    => array( 'tour_name', 'tour_date', 'adults' ),
			'hire'    => array( 'vehicle_type', 'pickup_date', 'return_date' ),
		);

		if ( empty( $wanted[ $type ] ) ) {
			return array();
		}

		$fields = TTB_Fields::flat_fields( $type );
		$rows   = array();

		foreach ( $wanted[ $type ] as $key ) {
			if ( ! isset( $fields[ $key ] ) || ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$display = self::value( $fields[ $key ], $data[ $key ] );
			if ( '' === trim( $display ) ) {
				continue;
			}

			$rows[] = array(
				'label' => $fields[ $key ]['label'],
				'value' => $display,
			);
		}

		return $rows;
	}
}

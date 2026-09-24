<?php
/**
 * The single source of truth for what each booking form asks.
 *
 * Every other part of the plugin - rendering, validation, storage, the admin
 * detail screen and both emails - reads this schema, so adding or removing a
 * question is a one-line change here.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Fields {

	/**
	 * Contact questions shared by all three forms.
	 *
	 * @return array
	 */
	protected static function contact_fields() {
		return array(
			array(
				'key'      => 'full_name',
				'label'    => __( 'Full name', 'tt-bookings' ),
				'type'     => 'text',
				'required' => true,
				'width'    => 'half',
				'autocomplete' => 'name',
			),
			array(
				'key'      => 'email',
				'label'    => __( 'Email address', 'tt-bookings' ),
				'type'     => 'email',
				'required' => true,
				'width'    => 'half',
				'help'     => __( 'Your confirmation is sent here.', 'tt-bookings' ),
				'autocomplete' => 'email',
			),
			array(
				'key'      => 'phone',
				'label'    => __( 'Phone / WhatsApp', 'tt-bookings' ),
				'type'     => 'tel',
				'required' => true,
				'width'    => 'half',
				'autocomplete' => 'tel',
			),
			array(
				'key'      => 'country',
				'label'    => __( 'Country of residence', 'tt-bookings' ),
				'type'     => 'text',
				'required' => false,
				'width'    => 'half',
			),
		);
	}

	/**
	 * A free-text box shared by all three forms.
	 *
	 * @param string $placeholder Prompt shown inside the box.
	 * @return array
	 */
	protected static function notes_field( $placeholder ) {
		return array(
			'key'         => 'notes',
			'label'       => __( 'Anything else we should know?', 'tt-bookings' ),
			'type'        => 'textarea',
			'required'    => false,
			'width'       => 'full',
			'placeholder' => $placeholder,
		);
	}

	/**
	 * The full schema, keyed by form type.
	 *
	 * @return array
	 */
	public static function all() {
		$schema = array(

			'airport' => array(
				'label'    => __( 'Airport Transfer', 'tt-bookings' ),
				'tagline'  => __( 'Door-to-door pickups and drop-offs, tracked against your flight.', 'tt-bookings' ),
				'icon'     => 'airport',
				'submit'   => __( 'Request my transfer', 'tt-bookings' ),
				'sections' => array(
					array(
						'title'  => __( 'Trip details', 'tt-bookings' ),
						'fields' => array(
							array(
								'key'      => 'direction',
								'label'    => __( 'Transfer direction', 'tt-bookings' ),
								'type'     => 'radio',
								'required' => true,
								'width'    => 'full',
								'options'  => array(
									'arrival'   => __( 'Airport to my accommodation', 'tt-bookings' ),
									'departure' => __( 'My accommodation to the airport', 'tt-bookings' ),
									'return'    => __( 'Both ways (return)', 'tt-bookings' ),
								),
								'default'  => 'arrival',
							),
							array(
								'key'         => 'pickup_location',
								'label'       => __( 'Pickup location', 'tt-bookings' ),
								'type'        => 'text',
								'required'    => true,
								'width'       => 'half',
								'placeholder' => __( 'Airport terminal, hotel or address', 'tt-bookings' ),
							),
							array(
								'key'         => 'dropoff_location',
								'label'       => __( 'Drop-off location', 'tt-bookings' ),
								'type'        => 'text',
								'required'    => true,
								'width'       => 'half',
								'placeholder' => __( 'Hotel, address or airport terminal', 'tt-bookings' ),
							),
							array(
								'key'      => 'pickup_date',
								'label'    => __( 'Pickup date', 'tt-bookings' ),
								'type'     => 'date',
								'required' => true,
								'width'    => 'third',
								'min'      => 'today',
							),
							array(
								'key'      => 'pickup_time',
								'label'    => __( 'Pickup time', 'tt-bookings' ),
								'type'     => 'time',
								'required' => true,
								'width'    => 'third',
							),
							array(
								'key'         => 'flight_number',
								'label'       => __( 'Flight number', 'tt-bookings' ),
								'type'        => 'text',
								'required'    => false,
								'width'       => 'third',
								'placeholder' => __( 'e.g. EK 524', 'tt-bookings' ),
								'help'        => __( 'Lets us track delays and wait for you.', 'tt-bookings' ),
							),
							array(
								'key'         => 'return_date',
								'label'       => __( 'Return pickup date & time', 'tt-bookings' ),
								'type'        => 'datetime-local',
								'required'    => false,
								'width'       => 'half',
								'help'        => __( 'Only needed for a return transfer.', 'tt-bookings' ),
								'show_if'     => array( 'direction' => 'return' ),
							),
							array(
								'key'      => 'passengers',
								'label'    => __( 'Passengers', 'tt-bookings' ),
								'type'     => 'number',
								'required' => true,
								'width'    => 'third',
								'min'      => 1,
								'max'      => 60,
								'default'  => 2,
							),
							array(
								'key'      => 'luggage',
								'label'    => __( 'Suitcases', 'tt-bookings' ),
								'type'     => 'number',
								'required' => false,
								'width'    => 'third',
								'min'      => 0,
								'max'      => 60,
								'default'  => 2,
							),
							array(
								'key'      => 'child_seats',
								'label'    => __( 'Child seats needed', 'tt-bookings' ),
								'type'     => 'number',
								'required' => false,
								'width'    => 'third',
								'min'      => 0,
								'max'      => 10,
								'default'  => 0,
							),
							array(
								'key'      => 'vehicle_class',
								'label'    => __( 'Vehicle class', 'tt-bookings' ),
								'type'     => 'select',
								'required' => false,
								'width'    => 'full',
								'options'  => array(
									''          => __( 'Recommend one for me', 'tt-bookings' ),
									'sedan'     => __( 'Sedan - up to 3 passengers', 'tt-bookings' ),
									'suv'       => __( 'SUV - up to 5 passengers', 'tt-bookings' ),
									'van'       => __( 'Minivan - up to 8 passengers', 'tt-bookings' ),
									'minibus'   => __( 'Minibus - up to 16 passengers', 'tt-bookings' ),
									'coach'     => __( 'Coach - 17 passengers and over', 'tt-bookings' ),
								),
							),
						),
					),
					array(
						'title'  => __( 'Your details', 'tt-bookings' ),
						'fields' => self::contact_fields(),
					),
					array(
						'title'  => '',
						'fields' => array(
							self::notes_field( __( 'Wheelchair access, extra stops, oversized luggage...', 'tt-bookings' ) ),
						),
					),
				),
			),

			'tour' => array(
				'label'    => __( 'Tour', 'tt-bookings' ),
				'tagline'  => __( 'Guided day trips and multi-day itineraries, built around your group.', 'tt-bookings' ),
				'icon'     => 'tour',
				'submit'   => __( 'Request my tour', 'tt-bookings' ),
				'sections' => array(
					array(
						'title'  => __( 'Tour details', 'tt-bookings' ),
						'fields' => array(
							array(
								'key'         => 'tour_name',
								'label'       => __( 'Tour or destination', 'tt-bookings' ),
								'type'        => 'text',
								'required'    => true,
								'width'       => 'full',
								'placeholder' => __( 'Name the tour, or tell us where you would like to go', 'tt-bookings' ),
							),
							array(
								'key'      => 'tour_date',
								'label'    => __( 'Preferred start date', 'tt-bookings' ),
								'type'     => 'date',
								'required' => true,
								'width'    => 'third',
								'min'      => 'today',
							),
							array(
								'key'      => 'duration',
								'label'    => __( 'Duration', 'tt-bookings' ),
								'type'     => 'select',
								'required' => false,
								'width'    => 'third',
								'options'  => array(
									'half-day' => __( 'Half day', 'tt-bookings' ),
									'full-day' => __( 'Full day', 'tt-bookings' ),
									'2-days'   => __( '2 days', 'tt-bookings' ),
									'3-days'   => __( '3 days', 'tt-bookings' ),
									'longer'   => __( 'Longer / custom', 'tt-bookings' ),
								),
								'default'  => 'full-day',
							),
							array(
								'key'      => 'start_time',
								'label'    => __( 'Preferred start time', 'tt-bookings' ),
								'type'     => 'time',
								'required' => false,
								'width'    => 'third',
							),
							array(
								'key'      => 'adults',
								'label'    => __( 'Adults', 'tt-bookings' ),
								'type'     => 'number',
								'required' => true,
								'width'    => 'third',
								'min'      => 1,
								'max'      => 99,
								'default'  => 2,
							),
							array(
								'key'      => 'children',
								'label'    => __( 'Children', 'tt-bookings' ),
								'type'     => 'number',
								'required' => false,
								'width'    => 'third',
								'min'      => 0,
								'max'      => 99,
								'default'  => 0,
							),
							array(
								'key'      => 'language',
								'label'    => __( 'Preferred guide language', 'tt-bookings' ),
								'type'     => 'text',
								'required' => false,
								'width'    => 'third',
								'placeholder' => __( 'English', 'tt-bookings' ),
							),
							array(
								'key'         => 'hotel',
								'label'       => __( 'Hotel / pickup address', 'tt-bookings' ),
								'type'        => 'text',
								'required'    => false,
								'width'       => 'half',
								'placeholder' => __( 'Where should we collect you?', 'tt-bookings' ),
							),
							array(
								'key'      => 'pickup_required',
								'label'    => __( 'Hotel pickup', 'tt-bookings' ),
								'type'     => 'select',
								'required' => false,
								'width'    => 'half',
								'options'  => array(
									'yes' => __( 'Yes, please collect us', 'tt-bookings' ),
									'no'  => __( 'No, we will meet you', 'tt-bookings' ),
								),
								'default'  => 'yes',
							),
							array(
								'key'      => 'dietary',
								'label'    => __( 'Dietary requirements', 'tt-bookings' ),
								'type'     => 'text',
								'required' => false,
								'width'    => 'full',
								'placeholder' => __( 'Vegetarian, halal, allergies...', 'tt-bookings' ),
							),
						),
					),
					array(
						'title'  => __( 'Your details', 'tt-bookings' ),
						'fields' => self::contact_fields(),
					),
					array(
						'title'  => '',
						'fields' => array(
							self::notes_field( __( 'Special occasions, accessibility needs, places you must see...', 'tt-bookings' ) ),
						),
					),
				),
			),

			'hire' => array(
				'label'    => __( 'Hire a Vehicle', 'tt-bookings' ),
				'tagline'  => __( 'Self-drive or with a driver, by the day or by the week.', 'tt-bookings' ),
				'icon'     => 'hire',
				'submit'   => __( 'Request this vehicle', 'tt-bookings' ),
				'sections' => array(
					array(
						'title'  => __( 'Hire details', 'tt-bookings' ),
						'fields' => array(
							array(
								'key'      => 'vehicle_type',
								'label'    => __( 'Vehicle type', 'tt-bookings' ),
								'type'     => 'select',
								'required' => true,
								'width'    => 'half',
								'options'  => array(
									''        => __( 'Please choose', 'tt-bookings' ),
									'economy' => __( 'Economy car', 'tt-bookings' ),
									'sedan'   => __( 'Sedan', 'tt-bookings' ),
									'suv'     => __( 'SUV / 4x4', 'tt-bookings' ),
									'van'     => __( 'Minivan (up to 8 seats)', 'tt-bookings' ),
									'minibus' => __( 'Minibus (up to 16 seats)', 'tt-bookings' ),
									'coach'   => __( 'Coach (17+ seats)', 'tt-bookings' ),
									'luxury'  => __( 'Luxury / executive', 'tt-bookings' ),
								),
							),
							array(
								'key'      => 'with_driver',
								'label'    => __( 'Driver', 'tt-bookings' ),
								'type'     => 'radio',
								'required' => true,
								'width'    => 'half',
								'options'  => array(
									'with'    => __( 'With a driver', 'tt-bookings' ),
									'without' => __( 'Self-drive', 'tt-bookings' ),
								),
								'default'  => 'with',
							),
							array(
								'key'      => 'pickup_date',
								'label'    => __( 'Pickup date & time', 'tt-bookings' ),
								'type'     => 'datetime-local',
								'required' => true,
								'width'    => 'half',
								'min'      => 'today',
							),
							array(
								'key'      => 'return_date',
								'label'    => __( 'Return date & time', 'tt-bookings' ),
								'type'     => 'datetime-local',
								'required' => true,
								'width'    => 'half',
								'min'      => 'today',
							),
							array(
								'key'         => 'pickup_location',
								'label'       => __( 'Pickup location', 'tt-bookings' ),
								'type'        => 'text',
								'required'    => true,
								'width'       => 'half',
								'placeholder' => __( 'Our depot, your hotel or the airport', 'tt-bookings' ),
							),
							array(
								'key'         => 'dropoff_location',
								'label'       => __( 'Return location', 'tt-bookings' ),
								'type'        => 'text',
								'required'    => false,
								'width'       => 'half',
								'placeholder' => __( 'Leave blank to return to the pickup point', 'tt-bookings' ),
							),
							array(
								'key'      => 'passengers',
								'label'    => __( 'Passengers', 'tt-bookings' ),
								'type'     => 'number',
								'required' => false,
								'width'    => 'third',
								'min'      => 1,
								'max'      => 60,
								'default'  => 2,
							),
							array(
								'key'      => 'driver_age',
								'label'    => __( 'Driver age', 'tt-bookings' ),
								'type'     => 'number',
								'required' => false,
								'width'    => 'third',
								'min'      => 18,
								'max'      => 99,
								'help'     => __( 'Self-drive only.', 'tt-bookings' ),
								'show_if'  => array( 'with_driver' => 'without' ),
							),
							array(
								'key'      => 'licence_country',
								'label'    => __( 'Licence issued in', 'tt-bookings' ),
								'type'     => 'text',
								'required' => false,
								'width'    => 'third',
								'help'     => __( 'Self-drive only.', 'tt-bookings' ),
								'show_if'  => array( 'with_driver' => 'without' ),
							),
							array(
								'key'      => 'extras',
								'label'    => __( 'Extras', 'tt-bookings' ),
								'type'     => 'checkbox',
								'required' => false,
								'width'    => 'full',
								'options'  => array(
									'gps'        => __( 'GPS / navigation', 'tt-bookings' ),
									'child_seat' => __( 'Child seat', 'tt-bookings' ),
									'roof_rack'  => __( 'Roof rack', 'tt-bookings' ),
									'wifi'       => __( 'Mobile Wi-Fi', 'tt-bookings' ),
									'insurance'  => __( 'Full insurance cover', 'tt-bookings' ),
								),
							),
						),
					),
					array(
						'title'  => __( 'Your details', 'tt-bookings' ),
						'fields' => self::contact_fields(),
					),
					array(
						'title'  => '',
						'fields' => array(
							self::notes_field( __( 'Cross-border travel, one-way drop-off, long-term rates...', 'tt-bookings' ) ),
						),
					),
				),
			),
		);

		/**
		 * Filter the booking form schema.
		 *
		 * @param array $schema Form definitions keyed by type.
		 */
		return apply_filters( 'ttb_form_schema', $schema );
	}

	/**
	 * One form definition.
	 *
	 * @param string $type Form type.
	 * @return array|null
	 */
	public static function get_form( $type ) {
		$all = self::all();
		return isset( $all[ $type ] ) ? $all[ $type ] : null;
	}

	/**
	 * Valid form types.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array_keys( self::all() );
	}

	/**
	 * A flat list of the fields in one form, without their section grouping.
	 *
	 * @param string $type Form type.
	 * @return array
	 */
	public static function flat_fields( $type ) {
		$form = self::get_form( $type );
		if ( ! $form ) {
			return array();
		}

		$fields = array();
		foreach ( $form['sections'] as $section ) {
			foreach ( $section['fields'] as $field ) {
				$fields[ $field['key'] ] = $field;
			}
		}

		return $fields;
	}
}

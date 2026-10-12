<?php
/**
 * Renders the booking forms and registers the shortcodes.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Form {

	/** Set once a form has been rendered, so assets are only enqueued when needed. */
	protected static $rendered = false;

	/** Set once the #ttb scroll anchor has been printed, so ids stay unique. */
	protected static $anchor_done = false;

	public static function init() {
		add_shortcode( 'tt_booking_form', array( __CLASS__, 'shortcode_single' ) );
		add_shortcode( 'tt_booking_forms', array( __CLASS__, 'shortcode_tabs' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register the handles.
	 *
	 * Called both on wp_enqueue_scripts and from enqueue() itself: block
	 * themes render a template before that hook fires, and an unregistered
	 * handle would silently drop the inline colours and the script config.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( 'tt-bookings', 'registered' ) ) {
			wp_register_style( 'tt-bookings', TTB_URL . 'assets/tt-bookings.css', array(), TTB_VERSION );
		}

		if ( ! wp_script_is( 'tt-bookings', 'registered' ) ) {
			wp_register_script( 'tt-bookings', TTB_URL . 'assets/tt-bookings.js', array(), TTB_VERSION, true );
		}
	}

	/**
	 * The anchor the no-JavaScript redirect scrolls back to, printed once.
	 *
	 * @return string
	 */
	protected static function anchor() {
		if ( self::$anchor_done ) {
			return '';
		}

		self::$anchor_done = true;

		return '<span id="ttb" class="ttb-anchor" aria-hidden="true"></span>';
	}

	protected static function enqueue() {
		if ( self::$rendered ) {
			return;
		}
		self::$rendered = true;

		self::register_assets();
		wp_enqueue_style( 'tt-bookings' );
		wp_enqueue_script( 'tt-bookings' );

		// The agency's brand colour drives the whole form palette.
		wp_add_inline_style(
			'tt-bookings',
			sprintf(
				'.ttb{--ttb-brand:%1$s;--ttb-brand-dark:%2$s;}',
				TTB_Settings::brand_colour(),
				self::darken( TTB_Settings::brand_colour(), 14 )
			)
		);
		wp_localize_script(
			'tt-bookings',
			'TTBookings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'sending'     => __( 'Sending...', 'tt-bookings' ),
					'required'    => __( 'This field is required.', 'tt-bookings' ),
					'email'       => __( 'Please enter a valid email address.', 'tt-bookings' ),
					'checkFields' => __( 'Please check the highlighted fields below.', 'tt-bookings' ),
					'failed'      => __( 'Sorry, something went wrong. Please try again, or call us.', 'tt-bookings' ),
				),
			)
		);
	}

	/**
	 * [tt_booking_form type="airport"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_single( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'    => 'airport',
				'title'   => '',
				'compact' => 'no',
			),
			$atts,
			'tt_booking_form'
		);

		$type = sanitize_key( $atts['type'] );
		if ( ! TTB_Fields::get_form( $type ) ) {
			return self::notice_html( sprintf( __( 'Unknown booking form "%s".', 'tt-bookings' ), esc_html( $atts['type'] ) ) );
		}

		if ( ! TTB_Settings::form_enabled( $type ) ) {
			return self::notice_html( __( 'This booking form is currently switched off.', 'tt-bookings' ) );
		}

		self::enqueue();

		ob_start();
		$compact = in_array( strtolower( (string) $atts['compact'] ), array( 'yes', 'true', '1' ), true );

		echo '<div class="ttb' . ( $compact ? ' ttb--compact' : '' ) . '">';
		echo self::anchor(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
		self::render_form( $type, $atts['title'], $compact );
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * [tt_booking_forms] - all three forms behind tabs.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_tabs( $atts ) {
		$atts = shortcode_atts(
			array(
				'types'   => implode( ',', TTB_Fields::types() ),
				'default' => '',
				'compact' => 'no',
			),
			$atts,
			'tt_booking_forms'
		);

		$types = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $atts['types'] ) ) ) );
		$types = array_values(
			array_filter(
				$types,
				static function ( $type ) {
					return TTB_Fields::get_form( $type ) && TTB_Settings::form_enabled( $type );
				}
			)
		);

		if ( empty( $types ) ) {
			return self::notice_html( __( 'No booking forms are switched on.', 'tt-bookings' ) );
		}

		self::enqueue();

		// If a submission just came back with errors, open that tab.
		$active = in_array( $atts['default'], $types, true ) ? $atts['default'] : $types[0];

		// Arriving from a destination card means they want the tour form.
		if ( ! empty( $_GET['tour'] ) && in_array( 'tour', $types, true ) ) {
			$active = 'tour';
		}

		$replay = self::replay();
		if ( $replay && in_array( $replay['type'], $types, true ) ) {
			$active = $replay['type'];
		}

		$uid     = wp_unique_id( 'ttb-tabs-' );
		$compact = in_array( strtolower( (string) $atts['compact'] ), array( 'yes', 'true', '1' ), true );

		ob_start();
		?>
		<div class="ttb ttb-tabs<?php echo $compact ? ' ttb--compact' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>">
			<?php echo self::anchor(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
			<div class="ttb-tablist" role="tablist" aria-label="<?php esc_attr_e( 'Booking type', 'tt-bookings' ); ?>">
				<?php foreach ( $types as $type ) : ?>
					<?php $form = TTB_Fields::get_form( $type ); ?>
					<button type="button"
						class="ttb-tab<?php echo $type === $active ? ' is-active' : ''; ?>"
						role="tab"
						id="<?php echo esc_attr( $uid . '-tab-' . $type ); ?>"
						aria-controls="<?php echo esc_attr( $uid . '-panel-' . $type ); ?>"
						aria-selected="<?php echo $type === $active ? 'true' : 'false'; ?>"
						data-ttb-tab="<?php echo esc_attr( $type ); ?>">
						<?php echo self::icon( $form['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><?php echo esc_html( $form['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<?php foreach ( $types as $type ) : ?>
				<div class="ttb-panel<?php echo $type === $active ? ' is-active' : ''; ?>"
					role="tabpanel"
					id="<?php echo esc_attr( $uid . '-panel-' . $type ); ?>"
					aria-labelledby="<?php echo esc_attr( $uid . '-tab-' . $type ); ?>"
					data-ttb-panel="<?php echo esc_attr( $type ); ?>"
					<?php echo $type === $active ? '' : 'hidden'; ?>>
					<?php self::render_form( $type, '', $compact ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The values and errors of a submission that bounced back, if any.
	 *
	 * @return array|null
	 */
	protected static function replay() {
		static $cache = false;

		if ( false !== $cache ) {
			return $cache;
		}

		$cache = null;
		$token = isset( $_GET['ttb_token'] ) ? sanitize_key( wp_unslash( $_GET['ttb_token'] ) ) : '';

		if ( $token ) {
			$stored = get_transient( 'ttb_replay_' . $token );
			if ( is_array( $stored ) ) {
				$cache = $stored;
			}
		}

		return $cache;
	}

	/**
	 * Render one complete form.
	 *
	 * @param string $type  Form type.
	 * @param string $title Optional heading override.
	 */
	public static function render_form( $type, $title = '', $compact = false ) {
		$form   = TTB_Fields::get_form( $type );

		// A hero panel is narrow; show only the questions needed to start a
		// booking and send the visitor to the full form for the rest.
		if ( $compact ) {
			$form = self::compact_form( $form );
		}
		$replay = self::replay();
		$is_replay = ( $replay && $replay['type'] === $type );
		$values    = $is_replay ? $replay['values'] : array();
		$errors    = $is_replay ? $replay['errors'] : array();

		// A rejection with no field errors - a stale nonce, the rate limit,
		// the spam traps - still has to say something to the visitor.
		$error_message = $is_replay && ! empty( $replay['message'] ) ? $replay['message'] : '';

		$success_ref  = isset( $_GET['ttb_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ttb_ref'] ) ) : '';
		$success_type = isset( $_GET['ttb_ok'] ) ? sanitize_key( wp_unslash( $_GET['ttb_ok'] ) ) : '';
		$is_success   = ( $success_ref && $success_type === $type );

		$uid = wp_unique_id( 'ttb-form-' );
		?>
		<form class="ttb-form<?php echo $is_success ? ' ttb-form--done' : ''; ?>"
			id="<?php echo esc_attr( $uid ); ?>"
			method="post"
			novalidate
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			data-ttb-form="<?php echo esc_attr( $type ); ?>">

			<input type="hidden" name="action" value="ttb_submit">
			<input type="hidden" name="ttb_type" value="<?php echo esc_attr( $type ); ?>">
			<input type="hidden" name="ttb_source" value="<?php echo esc_attr( self::current_url() ); ?>">
			<input type="hidden" name="ttb_started" value="<?php echo esc_attr( time() ); ?>">
			<?php wp_nonce_field( 'ttb_submit_' . $type, 'ttb_nonce' ); ?>

			<?php // Honeypot - a real visitor never sees this, a bot fills it in. ?>
			<div class="ttb-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'tt-bookings' ); ?>
					<input type="text" name="ttb_website" value="" tabindex="-1" autocomplete="off">
				</label>
			</div>

			<header class="ttb-form__head">
				<h3 class="ttb-form__title"><?php echo esc_html( $title ? $title : $form['label'] ); ?></h3>
				<?php if ( ! empty( $form['tagline'] ) ) : ?>
					<p class="ttb-form__tagline"><?php echo esc_html( $form['tagline'] ); ?></p>
				<?php endif; ?>
			</header>

			<div class="ttb-alert ttb-alert--success" data-ttb-success <?php echo $is_success ? '' : 'hidden'; ?>>
				<strong><?php esc_html_e( 'Request received.', 'tt-bookings' ); ?></strong>
				<span data-ttb-success-text>
					<?php
					if ( $is_success ) {
						printf(
							/* translators: %s: booking reference */
							esc_html__( 'Your reference is %s. A confirmation is on its way to your inbox and our team will be in touch shortly.', 'tt-bookings' ),
							'<strong>' . esc_html( $success_ref ) . '</strong>'
						);
					}
					?>
				</span>
			</div>

			<div class="ttb-alert ttb-alert--error" data-ttb-error <?php echo $error_message ? '' : 'hidden'; ?>>
				<span data-ttb-error-text><?php echo esc_html( $error_message ); ?></span>
			</div>

			<div class="ttb-body">
				<?php foreach ( $form['sections'] as $section ) : ?>
					<?php
					// A section can carry the same trigger as a field, so its
					// heading disappears along with the questions under it.
					$section_if = ! empty( $section['show_if'] ) ? wp_json_encode( $section['show_if'] ) : '';
					?>
					<section class="ttb-section"
						<?php echo $section_if ? 'data-ttb-showif="' . esc_attr( $section_if ) . '"' : ''; ?>>
						<?php if ( ! empty( $section['title'] ) ) : ?>
							<h4 class="ttb-section__title"><?php echo esc_html( $section['title'] ); ?></h4>
						<?php endif; ?>
						<?php if ( ! empty( $section['description'] ) ) : ?>
							<p class="ttb-section__desc"><?php echo esc_html( $section['description'] ); ?></p>
						<?php endif; ?>
						<div class="ttb-grid">
							<?php foreach ( $section['fields'] as $field ) : ?>
								<?php self::render_field( $field, $uid, $values, $errors ); ?>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>

				<?php if ( TTB_Settings::get( 'consent_text' ) ) : ?>
					<div class="ttb-consent">
						<label class="ttb-check">
							<input type="checkbox" name="ttb_consent" value="1" data-ttb-required
								<?php checked( ! empty( $values['ttb_consent'] ) ); ?>>
							<span><?php echo wp_kses_post( TTB_Settings::get( 'consent_text' ) ); ?></span>
						</label>
						<?php if ( isset( $errors['ttb_consent'] ) ) : ?>
							<p class="ttb-field__error"><?php echo esc_html( $errors['ttb_consent'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<footer class="ttb-form__foot">
				<button type="submit" class="ttb-submit">
					<span class="ttb-submit__label"><?php echo esc_html( $form['submit'] ); ?></span>
					<span class="ttb-spinner" aria-hidden="true"></span>
				</button>
				<?php if ( $compact && self::full_form_url() ) : ?>
					<p class="ttb-reassure">
						<a href="<?php echo esc_url( self::full_form_url() ); ?>">
							<?php esc_html_e( 'More options', 'tt-bookings' ); ?>
						</a>
						<?php esc_html_e( '- flight number, luggage, vehicle class and more.', 'tt-bookings' ); ?>
					</p>
				<?php else : ?>
					<p class="ttb-reassure"><?php esc_html_e( 'No payment now - we confirm availability and price by email first.', 'tt-bookings' ); ?></p>
				<?php endif; ?>
			</footer>
		</form>
		<?php
	}

	/**
	 * Render one field, including its label, control, help text and error.
	 *
	 * @param array  $field  Field definition.
	 * @param string $uid    Unique form id, used to build input ids.
	 * @param array  $values Previously submitted values.
	 * @param array  $errors Validation errors keyed by field key.
	 */
	protected static function render_field( $field, $uid, $values, $errors ) {
		$key   = $field['key'];
		$id    = $uid . '-' . $key;
		$name  = 'ttb[' . $key . ']';
		$type  = $field['type'];
		$width = isset( $field['width'] ) ? $field['width'] : 'full';
		$error = isset( $errors[ $key ] ) ? $errors[ $key ] : '';

		$value = isset( $values[ $key ] ) ? $values[ $key ] : ( isset( $field['default'] ) ? $field['default'] : '' );

		// A destination card links in as ?tour=Blue+Mountains, so the visitor
		// lands on the form with their choice already filled in.
		if ( 'tour_name' === $key && ! isset( $values[ $key ] ) && ! empty( $_GET['tour'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['tour'] ) );
		}

		$classes = array( 'ttb-field', 'ttb-field--' . $width, 'ttb-field--' . $type );
		if ( $error ) {
			$classes[] = 'has-error';
		}

		$show_if = '';
		if ( ! empty( $field['show_if'] ) ) {
			$show_if = wp_json_encode( $field['show_if'] );
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php echo $show_if ? 'data-ttb-showif="' . esc_attr( $show_if ) . '"' : ''; ?>>

			<?php if ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) : ?>
				<span class="ttb-field__label<?php echo ! empty( $field['required'] ) ? ' is-required' : ''; ?>">
					<?php echo esc_html( $field['label'] ); ?>
				</span>
			<?php else : ?>
				<label class="ttb-field__label<?php echo ! empty( $field['required'] ) ? ' is-required' : ''; ?>"
					for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
				</label>
			<?php endif; ?>

			<?php self::render_control( $field, $id, $name, $value ); ?>

			<?php if ( ! empty( $field['help'] ) ) : ?>
				<p class="ttb-field__help"><?php echo esc_html( $field['help'] ); ?></p>
			<?php endif; ?>

			<p class="ttb-field__error" data-ttb-field-error<?php echo $error ? '' : ' hidden'; ?>>
				<?php echo esc_html( $error ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the input control itself.
	 *
	 * @param array  $field Field definition.
	 * @param string $id    Input id.
	 * @param string $name  Input name.
	 * @param mixed  $value Current value.
	 */
	protected static function render_control( $field, $id, $name, $value ) {
		$type     = $field['type'];
		$required = ! empty( $field['required'] );

		$common = sprintf(
			' id="%s" name="%s" class="ttb-input"%s%s',
			esc_attr( $id ),
			esc_attr( $name ),
			$required ? ' data-ttb-required' : '',
			! empty( $field['autocomplete'] ) ? ' autocomplete="' . esc_attr( $field['autocomplete'] ) . '"' : ''
		);

		switch ( $type ) {

			case 'textarea':
				printf(
					'<textarea%s rows="4" placeholder="%s">%s</textarea>',
					$common, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
					esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
					esc_textarea( is_scalar( $value ) ? $value : '' )
				);
				break;

			case 'select':
				echo '<select' . $common . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $opt_value ),
						selected( (string) $value, (string) $opt_value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'radio':
				echo '<div class="ttb-options">';
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<label class="ttb-option"><input type="radio" name="%s" value="%s"%s%s><span>%s</span></label>',
						esc_attr( $name ),
						esc_attr( $opt_value ),
						checked( (string) $value, (string) $opt_value, false ),
						$required ? ' data-ttb-required' : '',
						esc_html( $opt_label )
					);
				}
				echo '</div>';
				break;

			case 'checkbox':
				$selected = is_array( $value ) ? $value : array();
				echo '<div class="ttb-options ttb-options--check">';
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<label class="ttb-option"><input type="checkbox" name="%s[]" value="%s"%s><span>%s</span></label>',
						esc_attr( $name ),
						esc_attr( $opt_value ),
						checked( in_array( (string) $opt_value, array_map( 'strval', $selected ), true ), true, false ),
						esc_html( $opt_label )
					);
				}
				echo '</div>';
				break;

			default:
				$attrs = '';
				if ( isset( $field['min'] ) ) {
					$min    = ( 'today' === $field['min'] ) ? self::today_attr( $type ) : $field['min'];
					$attrs .= ' min="' . esc_attr( $min ) . '"';
				}
				if ( isset( $field['max'] ) ) {
					$attrs .= ' max="' . esc_attr( $field['max'] ) . '"';
				}
				if ( ! empty( $field['placeholder'] ) ) {
					$attrs .= ' placeholder="' . esc_attr( $field['placeholder'] ) . '"';
				}
				printf(
					'<input type="%s"%s value="%s"%s>',
					esc_attr( $type ),
					$common, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_attr( is_scalar( $value ) ? $value : '' ),
					$attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
		}
	}

	/**
	 * Today's date in the format the given input type expects.
	 *
	 * @param string $type Input type.
	 * @return string
	 */
	protected static function today_attr( $type ) {
		return ( 'datetime-local' === $type )
			? current_time( 'Y-m-d\TH:i' )
			: current_time( 'Y-m-d' );
	}

	/**
	 * The URL the form is being shown on, used to send the visitor back.
	 *
	 * @return string
	 */
	public static function current_url() {
		$permalink = is_singular() ? get_permalink() : '';
		return $permalink ? $permalink : home_url( add_query_arg( array() ) );
	}

	/**
	 * Small inline icon used on the tabs.
	 *
	 * @param string $name Icon name.
	 * @return string SVG markup.
	 */
	public static function icon( $name ) {
		$paths = array(
			'airport' => '<path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/>',
			'tour'    => '<path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/>',
			'hire'    => '<path d="M18.9 6.5A1.5 1.5 0 0 0 17.5 5.5h-11A1.5 1.5 0 0 0 5.1 6.5L3 12.5V20a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h12v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-7.5zM6.8 7.5h10.4l1.3 3.8H5.5zM6.5 16A1.5 1.5 0 1 1 8 14.5 1.5 1.5 0 0 1 6.5 16zm11 0a1.5 1.5 0 1 1 1.5-1.5 1.5 1.5 0 0 1-1.5 1.5z"/>',
		);

		$path = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['tour'];

		return '<svg class="ttb-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/**
	 * Reduce a form to the questions marked for the compact hero panel.
	 *
	 * Everything dropped here is optional, so a compact submission is still a
	 * complete, valid booking request.
	 *
	 * @param array $form Full form definition.
	 * @return array
	 */
	protected static function compact_form( $form ) {
		$sections = array();

		foreach ( $form['sections'] as $section ) {
			$fields = array_values(
				array_filter(
					$section['fields'],
					static function ( $field ) {
						return ! empty( $field['compact'] );
					}
				)
			);

			if ( $fields ) {
				$section['fields'] = $fields;
				$sections[]        = $section;
			}
		}

		$form['sections'] = $sections;

		return $form;
	}

	/**
	 * The page holding the full booking form, found by its own shortcode so
	 * there is nothing to configure.
	 *
	 * @return string
	 */
	protected static function full_form_url() {
		static $url = null;

		if ( null !== $url ) {
			return $url;
		}

		$url   = '';
		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, 'tt_booking_forms' )
				|| has_shortcode( $page->post_content, 'tt_booking_form' ) ) {
				$url = get_permalink( $page );
				break;
			}
		}

		return $url;
	}
	/**
	 * A darker shade of a hex colour, used for the button hover state.
	 *
	 * @param string $hex     Colour as #rrggbb.
	 * @param int    $percent How much darker, 0-100.
	 * @return string
	 */
	protected static function darken( $hex, $percent ) {
		$hex    = ltrim( $hex, '#' );
		$factor = max( 0, 1 - ( $percent / 100 ) );
		$out    = '#';

		for ( $i = 0; $i < 3; $i++ ) {
			$channel = hexdec( substr( $hex, $i * 2, 2 ) );
			$out    .= str_pad( dechex( (int) round( $channel * $factor ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * A plain notice, used when a shortcode is misconfigured.
	 *
	 * @param string $message Notice text.
	 * @return string
	 */
	protected static function notice_html( $message ) {
		return '<p class="ttb-notice">' . esc_html( $message ) . '</p>';
	}
}

<?php
/**
 * Settings screen - Bookings > Settings.
 *
 * Everything the agency is likely to want to change without a developer:
 * which inbox receives requests, the From identity, subject lines, branding
 * and which of the three forms are live.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Settings {

	const OPTION = 'ttb_settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Defaults, used until the agency saves the screen.
	 *
	 * @return array
	 */
	public static function defaults() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = $host ? preg_replace( '/^www\./', '', $host ) : 'example.com';

		return array(
			'recipients'       => get_option( 'admin_email' ),
			'from_email'       => 'bookings@' . $host,
			'company_name'     => get_bloginfo( 'name' ),
			'company_phone'    => '',
			'brand_colour'     => '#0f766e',
			'logo_id'          => 0,
			'customer_subject' => __( 'Your {type} request - {reference}', 'tt-bookings' ),
			'admin_subject'    => __( 'New {type} request from {name} ({reference})', 'tt-bookings' ),
			'consent_text'     => __( 'I agree to be contacted about this request.', 'tt-bookings' ),
			'enabled'          => array( 'airport' => 1, 'tour' => 1, 'hire' => 1 ),
		);
	}

	/**
	 * All settings, defaults merged in.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		return array_merge( self::defaults(), $saved );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	/**
	 * Whether a given form is switched on.
	 *
	 * @param string $type Form type.
	 * @return bool
	 */
	public static function form_enabled( $type ) {
		$enabled = self::get( 'enabled' );
		return is_array( $enabled ) && ! empty( $enabled[ $type ] );
	}

	/**
	 * The agency inbox addresses, comma separated in the setting.
	 *
	 * @return string[]
	 */
	public static function recipients() {
		$raw   = (string) self::get( 'recipients' );
		$parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );

		return array_values( array_filter( $parts, 'is_email' ) );
	}

	/**
	 * The From address. Must be on the site's own domain or mail lands in spam.
	 *
	 * @return string
	 */
	public static function from_email() {
		$email = (string) self::get( 'from_email' );
		return is_email( $email ) ? $email : get_option( 'admin_email' );
	}

	public static function company_name() {
		$name = (string) self::get( 'company_name' );
		return $name ? $name : get_bloginfo( 'name' );
	}

	public static function brand_colour() {
		$colour = (string) self::get( 'brand_colour' );
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $colour ) ? $colour : '#0f766e';
	}

	/**
	 * The logo used in the email header, if one has been chosen.
	 *
	 * @return string
	 */
	public static function logo_url() {
		$id = (int) self::get( 'logo_id' );
		if ( ! $id ) {
			return '';
		}

		$src = wp_get_attachment_image_src( $id, 'medium' );

		return $src ? $src[0] : '';
	}

	public static function register() {
		register_setting(
			'ttb_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitise the settings screen on save.
	 *
	 * @param array $input Raw posted settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$out    = self::all();

		if ( isset( $input['recipients'] ) ) {
			$emails = array_filter( array_map( 'trim', explode( ',', $input['recipients'] ) ) );
			$emails = array_filter( array_map( 'sanitize_email', $emails ), 'is_email' );
			$out['recipients'] = implode( ', ', $emails );
		}

		if ( isset( $input['from_email'] ) ) {
			$from = sanitize_email( $input['from_email'] );
			$out['from_email'] = is_email( $from ) ? $from : $out['from_email'];
		}

		foreach ( array( 'company_name', 'company_phone', 'customer_subject', 'admin_subject' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		if ( isset( $input['consent_text'] ) ) {
			$out['consent_text'] = wp_kses_post( $input['consent_text'] );
		}

		if ( isset( $input['brand_colour'] ) ) {
			$colour = sanitize_hex_color( $input['brand_colour'] );
			$out['brand_colour'] = $colour ? $colour : $out['brand_colour'];
		}

		if ( isset( $input['logo_id'] ) ) {
			$out['logo_id'] = absint( $input['logo_id'] );
		}

		$enabled = array();
		foreach ( TTB_Fields::types() as $type ) {
			$enabled[ $type ] = ! empty( $input['enabled'][ $type ] ) ? 1 : 0;
		}
		$out['enabled'] = $enabled;

		return $out;
	}

	/** Render the settings screen. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = self::all();
		$from_host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$from_ok    = $from_host && false !== strpos( self::from_email(), preg_replace( '/^www\./', '', $from_host ) );
		?>
		<div class="wrap ttb-settings">
			<h1><?php esc_html_e( 'Booking Settings', 'tt-bookings' ); ?></h1>

			<?php if ( ! $from_ok ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: site domain */
							esc_html__( 'The "From" address is not on %s. Mail providers treat that as forged and send it to spam - use an address on your own domain and put the real inbox in "Send requests to" instead.', 'tt-bookings' ),
							'<code>' . esc_html( preg_replace( '/^www\./', '', (string) $from_host ) ) . '</code>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'ttb_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Where requests go', 'tt-bookings' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ttb-recipients"><?php esc_html_e( 'Send requests to', 'tt-bookings' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="ttb-recipients"
								name="<?php echo esc_attr( self::OPTION ); ?>[recipients]"
								value="<?php echo esc_attr( $settings['recipients'] ); ?>">
							<p class="description"><?php esc_html_e( 'Your inbox. Separate several addresses with commas.', 'tt-bookings' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ttb-from"><?php esc_html_e( '"From" address', 'tt-bookings' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="ttb-from"
								name="<?php echo esc_attr( self::OPTION ); ?>[from_email]"
								value="<?php echo esc_attr( $settings['from_email'] ); ?>">
							<p class="description"><?php esc_html_e( 'Must be an address on this website\'s own domain.', 'tt-bookings' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'How the emails look', 'tt-bookings' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ttb-company"><?php esc_html_e( 'Company name', 'tt-bookings' ); ?></label></th>
						<td><input type="text" class="regular-text" id="ttb-company"
							name="<?php echo esc_attr( self::OPTION ); ?>[company_name]"
							value="<?php echo esc_attr( $settings['company_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ttb-phone"><?php esc_html_e( 'Contact phone', 'tt-bookings' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="ttb-phone"
								name="<?php echo esc_attr( self::OPTION ); ?>[company_phone]"
								value="<?php echo esc_attr( $settings['company_phone'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown in the footer of every confirmation email.', 'tt-bookings' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ttb-colour"><?php esc_html_e( 'Brand colour', 'tt-bookings' ); ?></label></th>
						<td><input type="color" id="ttb-colour"
							name="<?php echo esc_attr( self::OPTION ); ?>[brand_colour]"
							value="<?php echo esc_attr( self::brand_colour() ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ttb-logo"><?php esc_html_e( 'Email logo', 'tt-bookings' ); ?></label></th>
						<td>
							<div class="ttb-logo-picker">
								<img src="<?php echo esc_url( self::logo_url() ); ?>"
									alt="" class="ttb-logo-preview"
									<?php echo self::logo_url() ? '' : 'hidden'; ?>>
								<input type="hidden" id="ttb-logo"
									name="<?php echo esc_attr( self::OPTION ); ?>[logo_id]"
									value="<?php echo esc_attr( (int) $settings['logo_id'] ); ?>">
								<button type="button" class="button" data-ttb-logo-choose><?php esc_html_e( 'Choose logo', 'tt-bookings' ); ?></button>
								<button type="button" class="button-link" data-ttb-logo-clear><?php esc_html_e( 'Remove', 'tt-bookings' ); ?></button>
							</div>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Subject lines', 'tt-bookings' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ttb-subj-c"><?php esc_html_e( 'To the customer', 'tt-bookings' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="ttb-subj-c"
								name="<?php echo esc_attr( self::OPTION ); ?>[customer_subject]"
								value="<?php echo esc_attr( $settings['customer_subject'] ); ?>">
							<p class="description"><?php esc_html_e( 'Available tags: {reference} {type} {company}', 'tt-bookings' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ttb-subj-a"><?php esc_html_e( 'To your inbox', 'tt-bookings' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="ttb-subj-a"
								name="<?php echo esc_attr( self::OPTION ); ?>[admin_subject]"
								value="<?php echo esc_attr( $settings['admin_subject'] ); ?>">
							<p class="description"><?php esc_html_e( 'Available tags: {reference} {type} {name}', 'tt-bookings' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ttb-consent"><?php esc_html_e( 'Consent checkbox', 'tt-bookings' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="ttb-consent"
								name="<?php echo esc_attr( self::OPTION ); ?>[consent_text]"
								value="<?php echo esc_attr( $settings['consent_text'] ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to remove the checkbox from every form.', 'tt-bookings' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Which forms are live', 'tt-bookings' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( TTB_Fields::all() as $type => $form ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $form['label'] ); ?></th>
							<td>
								<label>
									<input type="checkbox" value="1"
										name="<?php echo esc_attr( self::OPTION ); ?>[enabled][<?php echo esc_attr( $type ); ?>]"
										<?php checked( self::form_enabled( $type ) ); ?>>
									<?php esc_html_e( 'Accept requests', 'tt-bookings' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Shortcode:', 'tt-bookings' ); ?>
									<code>[tt_booking_form type="<?php echo esc_attr( $type ); ?>"]</code>
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'All three in tabs', 'tt-bookings' ); ?></th>
						<td><code>[tt_booking_forms]</code></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

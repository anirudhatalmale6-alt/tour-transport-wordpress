<?php
/**
 * The "Deal details" box: price, badge, validity window, link and the
 * sidebar-highlight switch.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Meta {

	const NONCE = 'ttp_meta_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post_' . TTP_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * The fields stored against a deal, with their sanitiser.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'_ttp_price'      => 'sanitize_text_field',
			'_ttp_price_note' => 'sanitize_text_field',
			'_ttp_old_price'  => 'sanitize_text_field',
			'_ttp_badge'      => 'sanitize_text_field',
			'_ttp_link_url'   => 'esc_url_raw',
			'_ttp_link_text'  => 'sanitize_text_field',
			'_ttp_start'      => array( __CLASS__, 'sanitize_date' ),
			'_ttp_end'        => array( __CLASS__, 'sanitize_date' ),
			'_ttp_featured'   => array( __CLASS__, 'sanitize_bool' ),
		);
	}

	/**
	 * Keep only a valid Y-m-d date.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function sanitize_date( $value ) {
		$value = sanitize_text_field( $value );

		if ( ! $value ) {
			return '';
		}

		$parsed = DateTime::createFromFormat( 'Y-m-d', $value );

		return ( $parsed && $parsed->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/**
	 * Store a checkbox as 1 or an empty string.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_bool( $value ) {
		return $value ? '1' : '';
	}

	/**
	 * One stored value.
	 *
	 * @param int    $post_id Deal ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public static function get( $post_id, $key ) {
		return (string) get_post_meta( $post_id, $key, true );
	}

	/**
	 * Load the date picker polyfill styling on the deal editor only.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen || TTP_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'tt-promotions-admin', TTP_URL . 'assets/tt-promotions-admin.css', array(), TTP_VERSION );
	}

	public static function add_box() {
		add_meta_box(
			'ttp-details',
			__( 'Deal details', 'tt-promotions' ),
			array( __CLASS__, 'render' ),
			TTP_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the box.
	 *
	 * @param WP_Post $post Deal being edited.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'ttp_save_meta', self::NONCE );

		$price      = self::get( $post->ID, '_ttp_price' );
		$price_note = self::get( $post->ID, '_ttp_price_note' );
		$old_price  = self::get( $post->ID, '_ttp_old_price' );
		$badge      = self::get( $post->ID, '_ttp_badge' );
		$link_url   = self::get( $post->ID, '_ttp_link_url' );
		$link_text  = self::get( $post->ID, '_ttp_link_text' );
		$start      = self::get( $post->ID, '_ttp_start' );
		$end        = self::get( $post->ID, '_ttp_end' );
		$featured   = self::get( $post->ID, '_ttp_featured' );
		?>
		<div class="ttp-meta">
			<p class="ttp-meta__intro">
				<?php esc_html_e( 'Set the deal image with "Deal image" on the right, write the short description in the main editor above, then fill in the price and dates here.', 'tt-promotions' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ttp-price"><?php esc_html_e( 'Price', 'tt-promotions' ); ?></label></th>
					<td>
						<input type="text" id="ttp-price" name="_ttp_price" class="regular-text"
							value="<?php echo esc_attr( $price ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. $49 or From $120', 'tt-promotions' ); ?>">
						<p class="description"><?php esc_html_e( 'Type it exactly as you want it shown, with your currency symbol.', 'tt-promotions' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ttp-price-note"><?php esc_html_e( 'Price note', 'tt-promotions' ); ?></label></th>
					<td>
						<input type="text" id="ttp-price-note" name="_ttp_price_note" class="regular-text"
							value="<?php echo esc_attr( $price_note ); ?>"
							placeholder="<?php esc_attr_e( 'per person', 'tt-promotions' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ttp-old-price"><?php esc_html_e( 'Was (crossed out)', 'tt-promotions' ); ?></label></th>
					<td>
						<input type="text" id="ttp-old-price" name="_ttp_old_price" class="regular-text"
							value="<?php echo esc_attr( $old_price ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. $79', 'tt-promotions' ); ?>">
						<p class="description"><?php esc_html_e( 'Optional. Leave blank if there is no previous price.', 'tt-promotions' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ttp-badge"><?php esc_html_e( 'Badge', 'tt-promotions' ); ?></label></th>
					<td>
						<input type="text" id="ttp-badge" name="_ttp_badge" class="regular-text"
							value="<?php echo esc_attr( $badge ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. Save 20% or Limited seats', 'tt-promotions' ); ?>">
						<p class="description"><?php esc_html_e( 'A small label printed over the image.', 'tt-promotions' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ttp-start"><?php esc_html_e( 'Runs from', 'tt-promotions' ); ?></label></th>
					<td>
						<input type="date" id="ttp-start" name="_ttp_start" value="<?php echo esc_attr( $start ); ?>">
						<label for="ttp-end" style="margin-left:14px"><?php esc_html_e( 'until', 'tt-promotions' ); ?></label>
						<input type="date" id="ttp-end" name="_ttp_end" value="<?php echo esc_attr( $end ); ?>">
						<p class="description">
							<?php esc_html_e( 'Optional. A deal appears from the start date and hides itself the day after the end date - no need to come back and delete it.', 'tt-promotions' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ttp-link-url"><?php esc_html_e( 'Button link', 'tt-promotions' ); ?></label></th>
					<td>
						<input type="url" id="ttp-link-url" name="_ttp_link_url" class="regular-text"
							value="<?php echo esc_attr( $link_url ); ?>"
							placeholder="<?php echo esc_attr( home_url( '/bookings/' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Where the button sends the visitor. Usually your Bookings page.', 'tt-promotions' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ttp-link-text"><?php esc_html_e( 'Button text', 'tt-promotions' ); ?></label></th>
					<td>
						<input type="text" id="ttp-link-text" name="_ttp_link_text" class="regular-text"
							value="<?php echo esc_attr( $link_text ); ?>"
							placeholder="<?php esc_attr_e( 'Book this deal', 'tt-promotions' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sidebar highlight', 'tt-promotions' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="_ttp_featured" value="1" <?php checked( '1', $featured ); ?>>
							<?php esc_html_e( 'Show this deal in the Promotions widget across the site', 'tt-promotions' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save the box.
	 *
	 * @param int     $post_id Deal ID.
	 * @param WP_Post $post    Deal.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ttp_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( self::fields() as $key => $sanitizer ) {
			$raw   = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$value = call_user_func( $sanitizer, $raw );

			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}
}

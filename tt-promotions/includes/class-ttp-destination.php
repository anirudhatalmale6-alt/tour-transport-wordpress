<?php
/**
 * Destinations: an image card per place the agency runs tours to.
 *
 * Clicking a card sends the visitor to the booking form with that
 * destination already filled in, which is the whole point of the feature.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Destination {

	const POST_TYPE = 'tt_destination';

	const NONCE = 'ttd_meta_nonce';

	/** Optional per-destination link override. */
	const META_LINK = '_ttd_link_url';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_shortcode( 'tt_destinations', array( __CLASS__, 'shortcode' ) );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Destinations', 'tt-promotions' ),
					'singular_name'         => __( 'Destination', 'tt-promotions' ),
					'menu_name'             => __( 'Destinations', 'tt-promotions' ),
					'add_new'               => __( 'Add destination', 'tt-promotions' ),
					'add_new_item'          => __( 'Add a new destination', 'tt-promotions' ),
					'edit_item'             => __( 'Edit destination', 'tt-promotions' ),
					'not_found'             => __( 'No destinations yet. Add your first one.', 'tt-promotions' ),
					'featured_image'        => __( 'Destination photo', 'tt-promotions' ),
					'set_featured_image'    => __( 'Set destination photo', 'tt-promotions' ),
					'remove_featured_image' => __( 'Remove destination photo', 'tt-promotions' ),
					'use_featured_image'    => __( 'Use as destination photo', 'tt-promotions' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_position'       => 24,
				'menu_icon'           => 'dashicons-location-alt',
				'supports'            => array( 'title', 'thumbnail', 'excerpt', 'page-attributes' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/**
	 * A clearer prompt than "Add title".
	 *
	 * @param string  $text Current placeholder.
	 * @param WP_Post $post Post being edited.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		if ( $post && self::POST_TYPE === $post->post_type ) {
			return __( 'Destination name, e.g. Blue Mountains', 'tt-promotions' );
		}

		return $text;
	}

	public static function add_box() {
		add_meta_box(
			'ttd-details',
			__( 'Where this card links to', 'tt-promotions' ),
			array( __CLASS__, 'render_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the link box.
	 *
	 * @param WP_Post $post Destination being edited.
	 */
	public static function render_box( $post ) {
		wp_nonce_field( 'ttd_save_meta', self::NONCE );

		$link    = (string) get_post_meta( $post->ID, self::META_LINK, true );
		$default = self::default_link( get_the_title( $post ) );
		?>
		<p class="ttp-meta__intro">
			<?php esc_html_e( 'Set the photo with "Destination photo" on the right, and write one short line in the Excerpt box if you want text under the name.', 'tt-promotions' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ttd-link"><?php esc_html_e( 'Link', 'tt-promotions' ); ?></label></th>
				<td>
					<input type="url" id="ttd-link" name="<?php echo esc_attr( self::META_LINK ); ?>"
						class="large-text" value="<?php echo esc_attr( $link ); ?>"
						placeholder="<?php echo esc_attr( $default ); ?>">
					<p class="description">
						<?php esc_html_e( 'Leave blank and the card goes to your booking form with this destination already filled in. That is what you want in almost every case.', 'tt-promotions' ); ?>
						<?php if ( $default ) : ?>
							<br><?php esc_html_e( 'Right now that would be:', 'tt-promotions' ); ?>
							<code><?php echo esc_html( $default ); ?></code>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the link box.
	 *
	 * @param int     $post_id Destination ID.
	 * @param WP_Post $post    Destination.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ttd_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$link = isset( $_POST[ self::META_LINK ] ) ? esc_url_raw( wp_unslash( $_POST[ self::META_LINK ] ) ) : '';

		if ( $link ) {
			update_post_meta( $post_id, self::META_LINK, $link );
		} else {
			delete_post_meta( $post_id, self::META_LINK );
		}
	}

	/**
	 * The page holding the booking form, found by looking for the shortcode
	 * so nobody has to configure it.
	 *
	 * @return string Permalink, or an empty string when no such page exists.
	 */
	public static function booking_page_url() {
		$cached = wp_cache_get( 'ttd_booking_page' );

		if ( false !== $cached ) {
			return $cached;
		}

		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'orderby'                => 'menu_order',
				'order'                  => 'ASC',
			)
		);

		$url = '';

		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, 'tt_booking_forms' )
				|| has_shortcode( $page->post_content, 'tt_booking_form' ) ) {
				$url = get_permalink( $page );
				break;
			}
		}

		wp_cache_set( 'ttd_booking_page', $url );

		return $url;
	}

	/**
	 * Where a destination card should send the visitor.
	 *
	 * @param string $title Destination name.
	 * @return string
	 */
	public static function default_link( $title ) {
		$booking = self::booking_page_url();

		if ( ! $booking ) {
			return '';
		}

		return add_query_arg( 'tour', rawurlencode( $title ), $booking ) . '#ttb';
	}

	/**
	 * The resolved link for one destination.
	 *
	 * @param WP_Post $post Destination.
	 * @return string
	 */
	public static function link_for( $post ) {
		$override = (string) get_post_meta( $post->ID, self::META_LINK, true );

		return $override ? $override : self::default_link( get_the_title( $post ) );
	}

	/**
	 * [tt_destinations columns="5" limit="-1"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'columns' => 5,
				'limit'   => -1,
				'empty'   => __( 'No destinations added yet.', 'tt-promotions' ),
			),
			$atts,
			'tt_destinations'
		);

		$columns = max( 2, min( 6, (int) $atts['columns'] ) );

		$items = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => (int) $atts['limit'],
				'orderby'                => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $items ) ) {
			return '<p class="ttp-empty">' . esc_html( $atts['empty'] ) . '</p>';
		}

		TTP_Render::enqueue_public();

		ob_start();
		?>
		<div class="ttp ttp-dest ttp-dest--<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$url   = self::link_for( $item );
				$tag   = $url ? 'a' : 'div';
				$image = get_the_post_thumbnail(
					$item,
					'medium_large',
					array(
						'class'   => 'ttp-dest__img',
						'alt'     => get_the_title( $item ),
						'loading' => 'lazy',
					)
				);
				?>
				<<?php echo esc_attr( $tag ); ?> class="ttp-dest__card"
					<?php echo $url ? 'href="' . esc_url( $url ) . '"' : ''; ?>>
					<span class="ttp-dest__media">
						<?php
						echo $image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
							? $image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							: '<span class="ttp-dest__placeholder" aria-hidden="true"></span>';
						?>
					</span>
					<span class="ttp-dest__body">
						<span class="ttp-dest__title"><?php echo esc_html( get_the_title( $item ) ); ?></span>
						<?php if ( $item->post_excerpt ) : ?>
							<span class="ttp-dest__text"><?php echo esc_html( $item->post_excerpt ); ?></span>
						<?php endif; ?>
					</span>
				</<?php echo esc_attr( $tag ); ?>>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Admin list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'ttd_image'  => __( 'Photo', 'tt-promotions' ),
			'title'      => __( 'Destination', 'tt-promotions' ),
			'ttd_link'   => __( 'Links to', 'tt-promotions' ),
			'menu_order' => __( 'Order', 'tt-promotions' ),
		);
	}

	/**
	 * Render one admin column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Destination ID.
	 */
	public static function column( $column, $post_id ) {
		switch ( $column ) {

			case 'ttd_image':
				$thumb = get_the_post_thumbnail( $post_id, array( 72, 54 ), array( 'class' => 'ttp-thumb' ) );
				echo $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
					? $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					: '<span class="ttp-thumb ttp-thumb--empty" aria-hidden="true"></span>';
				break;

			case 'ttd_link':
				$url = self::link_for( get_post( $post_id ) );
				if ( $url ) {
					printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $url ) );
				} else {
					echo '<span class="ttp-state ttp-state--off">'
						. esc_html__( 'No booking page found', 'tt-promotions' ) . '</span>';
				}
				break;

			case 'menu_order':
				echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
				break;
		}
	}

	/**
	 * Let the list be ordered the way it appears on the page.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['menu_order'] = 'menu_order';
		return $columns;
	}
}

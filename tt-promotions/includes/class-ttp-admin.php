<?php
/**
 * Admin conveniences for the Promotions list: a thumbnail, the price, the
 * validity window and a "How to use" screen with the shortcodes.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Admin {

	public static function init() {
		$pt = TTP_Post_Type::POST_TYPE;

		add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( "manage_edit-{$pt}_sortable_columns", array( __CLASS__, 'sortable' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Replace the default columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'           => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'ttp_image'    => __( 'Image', 'tt-promotions' ),
			'title'        => __( 'Deal', 'tt-promotions' ),
			'ttp_price'    => __( 'Price', 'tt-promotions' ),
			'ttp_window'   => __( 'Runs', 'tt-promotions' ),
			'ttp_state'    => __( 'Showing', 'tt-promotions' ),
			'ttp_featured' => __( 'Sidebar', 'tt-promotions' ),
			'menu_order'   => __( 'Order', 'tt-promotions' ),
		);
	}

	/**
	 * Render one column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Deal ID.
	 */
	public static function column( $column, $post_id ) {
		switch ( $column ) {

			case 'ttp_image':
				$thumb = get_the_post_thumbnail( $post_id, array( 72, 54 ), array( 'class' => 'ttp-thumb' ) );
				echo $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
					? $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					: '<span class="ttp-thumb ttp-thumb--empty" aria-hidden="true"></span>';
				break;

			case 'ttp_price':
				$price = TTP_Meta::get( $post_id, '_ttp_price' );
				$old   = TTP_Meta::get( $post_id, '_ttp_old_price' );
				if ( $old ) {
					echo '<s>' . esc_html( $old ) . '</s> ';
				}
				echo $price ? '<strong>' . esc_html( $price ) . '</strong>' : '&mdash;';
				break;

			case 'ttp_window':
				$start = TTP_Meta::get( $post_id, '_ttp_start' );
				$end   = TTP_Meta::get( $post_id, '_ttp_end' );

				if ( ! $start && ! $end ) {
					esc_html_e( 'Always', 'tt-promotions' );
					break;
				}

				$format = get_option( 'date_format' );
				echo esc_html(
					sprintf(
						'%s - %s',
						$start ? date_i18n( $format, strtotime( $start ) ) : __( 'now', 'tt-promotions' ),
						$end ? date_i18n( $format, strtotime( $end ) ) : __( 'no end', 'tt-promotions' )
					)
				);
				break;

			case 'ttp_state':
				if ( 'publish' !== get_post_status( $post_id ) ) {
					echo '<span class="ttp-state ttp-state--off">' . esc_html__( 'Draft', 'tt-promotions' ) . '</span>';
				} elseif ( TTP_Query::is_expired( $post_id ) ) {
					echo '<span class="ttp-state ttp-state--off">' . esc_html__( 'Expired', 'tt-promotions' ) . '</span>';
				} elseif ( TTP_Query::is_scheduled( $post_id ) ) {
					echo '<span class="ttp-state ttp-state--soon">' . esc_html__( 'Scheduled', 'tt-promotions' ) . '</span>';
				} else {
					echo '<span class="ttp-state ttp-state--on">' . esc_html__( 'Live', 'tt-promotions' ) . '</span>';
				}
				break;

			case 'ttp_featured':
				echo TTP_Meta::get( $post_id, '_ttp_featured' )
					? '<span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'In the sidebar widget', 'tt-promotions' ) . '"></span>'
					: '<span class="dashicons dashicons-star-empty ttp-dim"></span>';
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

	/**
	 * A clearer prompt than "Add title".
	 *
	 * @param string  $text Current placeholder.
	 * @param WP_Post $post Post being edited.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		if ( $post && TTP_Post_Type::POST_TYPE === $post->post_type ) {
			return __( 'Deal name, e.g. Airport transfer - 20% off', 'tt-promotions' );
		}

		return $text;
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . TTP_Post_Type::POST_TYPE,
			__( 'How to use Promotions', 'tt-promotions' ),
			__( 'How to use', 'tt-promotions' ),
			'edit_posts',
			'ttp-help',
			array( __CLASS__, 'render_help' )
		);
	}

	/** A short in-dashboard guide so nobody has to email me to add a deal. */
	public static function render_help() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'How to use Promotions', 'tt-promotions' ); ?></h1>

			<h2><?php esc_html_e( 'Adding a deal', 'tt-promotions' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Promotions > Add deal.', 'tt-promotions' ); ?></li>
				<li><?php esc_html_e( 'Type the deal name in the title, and the short description in the main editor.', 'tt-promotions' ); ?></li>
				<li><?php esc_html_e( 'Set the "Deal image" on the right - this is the picture on the card.', 'tt-promotions' ); ?></li>
				<li><?php esc_html_e( 'Fill in the price, the optional badge and the button link in "Deal details".', 'tt-promotions' ); ?></li>
				<li><?php esc_html_e( 'Tick "Sidebar highlight" if you want it in the widget as well, then Publish.', 'tt-promotions' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'It appears on the Promotions page straight away. No page editing needed.', 'tt-promotions' ); ?></p>

			<h2><?php esc_html_e( 'Where the deals appear', 'tt-promotions' ); ?></h2>
			<table class="widefat striped" style="max-width:820px">
				<tbody>
					<tr>
						<th style="width:260px"><?php esc_html_e( 'Promotions page grid', 'tt-promotions' ); ?></th>
						<td><code>[tt_promotions]</code><br>
							<span class="description"><?php esc_html_e( 'Options: columns="3" limit="-1"', 'tt-promotions' ); ?></span></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sidebar highlight', 'tt-promotions' ); ?></th>
						<td><?php esc_html_e( 'Appearance > Widgets > "Promotions Highlight"', 'tt-promotions' ); ?><br>
							<span class="description"><?php esc_html_e( 'Or anywhere in a page:', 'tt-promotions' ); ?> <code>[tt_promotions_highlight limit="2"]</code></span></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Ending a deal', 'tt-promotions' ); ?></h2>
			<p><?php esc_html_e( 'Set an end date and the deal hides itself the day after. To take one down immediately, switch it to Draft.', 'tt-promotions' ); ?></p>

			<h2><?php esc_html_e( 'Changing the order', 'tt-promotions' ); ?></h2>
			<p><?php esc_html_e( 'The "Order" box under Page Attributes controls the position - 1 shows first. Deals with the same order fall back to newest first.', 'tt-promotions' ); ?></p>
		</div>
		<?php
	}
}

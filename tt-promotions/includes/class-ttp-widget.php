<?php
/**
 * The sidebar widget.
 *
 * Registered as a classic widget so it works in every theme's sidebar, and
 * exposed to block themes through the Legacy Widget block.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Widget extends WP_Widget {

	public static function init() {
		add_action(
			'widgets_init',
			static function () {
				register_widget( 'TTP_Widget' );
			}
		);
	}

	public function __construct() {
		parent::__construct(
			'ttp_widget',
			__( 'Promotions Highlight', 'tt-promotions' ),
			array(
				'description' => __( 'Shows your current deals in the sidebar. Tick "Sidebar highlight" on a deal to choose which ones appear.', 'tt-promotions' ),
				'classname'   => 'widget_ttp',
			)
		);
	}

	/**
	 * Front-end output.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Saved widget settings.
	 */
	public function widget( $args, $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Current deals', 'tt-promotions' );
		$limit = isset( $instance['limit'] ) ? (int) $instance['limit'] : 2;

		$html = TTP_Render::highlight_html( $limit );

		// An empty box in the sidebar looks broken - print nothing instead.
		if ( '' === $html ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.

		if ( $title ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in TTP_Render.

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
	}

	/**
	 * The widget settings form.
	 *
	 * @param array $instance Saved settings.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Current deals', 'tt-promotions' );
		$limit = isset( $instance['limit'] ) ? (int) $instance['limit'] : 2;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title', 'tt-promotions' ); ?>
			</label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>">
				<?php esc_html_e( 'How many deals', 'tt-promotions' ); ?>
			</label>
			<input class="tiny-text" type="number" min="1" max="6" step="1"
				id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>"
				value="<?php echo esc_attr( $limit ); ?>">
		</p>
		<?php
	}

	/**
	 * Save the widget settings.
	 *
	 * @param array $new_instance Submitted settings.
	 * @param array $old_instance Previous settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'limit' => isset( $new_instance['limit'] ) ? max( 1, min( 6, (int) $new_instance['limit'] ) ) : 2,
		);
	}
}

<?php
/**
 * Front-end output: the Promotions page grid and the sidebar highlight.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Render {

	/** Set once markup has been produced, so the stylesheet loads only then. */
	protected static $rendered = false;

	public static function init() {
		add_shortcode( 'tt_promotions', array( __CLASS__, 'shortcode_grid' ) );
		add_shortcode( 'tt_promotions_highlight', array( __CLASS__, 'shortcode_highlight' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register the stylesheet. Also called from enqueue(), because a block
	 * theme renders its template before wp_enqueue_scripts fires.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( 'tt-promotions', 'registered' ) ) {
			wp_register_style( 'tt-promotions', TTP_URL . 'assets/tt-promotions.css', array(), TTP_VERSION );
		}
	}

	protected static function enqueue() {
		if ( self::$rendered ) {
			return;
		}

		self::$rendered = true;

		self::register_assets();
		wp_enqueue_style( 'tt-promotions' );
	}

	/**
	 * Public wrapper so the destinations grid shares this stylesheet.
	 */
	public static function enqueue_public() {
		self::enqueue();
	}

	/**
	 * [tt_promotions columns="3" limit="-1"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_grid( $atts ) {
		$atts = shortcode_atts(
			array(
				'columns' => 3,
				'limit'   => -1,
				'empty'   => __( 'No promotions are running right now - check back soon.', 'tt-promotions' ),
			),
			$atts,
			'tt_promotions'
		);

		$columns = max( 1, min( 4, (int) $atts['columns'] ) );
		$deals   = TTP_Query::live( array( 'limit' => (int) $atts['limit'] ) );

		if ( empty( $deals ) ) {
			return '<p class="ttp-empty">' . esc_html( $atts['empty'] ) . '</p>';
		}

		self::enqueue();

		ob_start();
		?>
		<div class="ttp ttp-grid ttp-grid--<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $deals as $deal ) : ?>
				<?php self::card( TTP_Card::build( $deal ) ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [tt_promotions_highlight limit="2"] - the compact sidebar version.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_highlight( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 2,
				'title' => '',
			),
			$atts,
			'tt_promotions_highlight'
		);

		return self::highlight_html( (int) $atts['limit'], $atts['title'] );
	}

	/**
	 * The sidebar highlight markup, shared by the shortcode and the widget.
	 *
	 * @param int    $limit How many deals to show.
	 * @param string $title Optional heading.
	 * @return string
	 */
	public static function highlight_html( $limit, $title = '' ) {
		$deals = TTP_Query::live(
			array(
				'limit'    => max( 1, $limit ),
				'featured' => true,
			)
		);

		// Nothing flagged for the sidebar? Fall back to the newest live deals
		// so the widget is never an empty box.
		if ( empty( $deals ) ) {
			$deals = TTP_Query::live( array( 'limit' => max( 1, $limit ) ) );
		}

		if ( empty( $deals ) ) {
			return '';
		}

		self::enqueue();

		ob_start();
		?>
		<div class="ttp ttp-highlight">
			<?php if ( $title ) : ?>
				<h2 class="ttp-highlight__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php foreach ( $deals as $deal ) : ?>
				<?php self::mini_card( TTP_Card::build( $deal ) ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * A full card for the Promotions page.
	 *
	 * @param array $card Card data.
	 */
	public static function card( $card ) {
		$image = TTP_Card::image( $card );
		$ends  = TTP_Card::ends_label( $card );
		?>
		<article class="ttp-card">
			<div class="ttp-card__media<?php echo $image ? '' : ' is-empty'; ?>">
				<?php
				echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image output.
				?>
				<?php if ( $card['badge'] ) : ?>
					<span class="ttp-card__badge"><?php echo esc_html( $card['badge'] ); ?></span>
				<?php endif; ?>
			</div>

			<div class="ttp-card__body">
				<h3 class="ttp-card__title"><?php echo esc_html( $card['title'] ); ?></h3>

				<?php if ( $card['text'] ) : ?>
					<p class="ttp-card__text"><?php echo esc_html( $card['text'] ); ?></p>
				<?php endif; ?>

				<?php if ( $ends ) : ?>
					<p class="ttp-card__ends"><?php echo esc_html( $ends ); ?></p>
				<?php endif; ?>
			</div>

			<footer class="ttp-card__foot">
				<?php if ( $card['price'] ) : ?>
					<p class="ttp-card__price">
						<?php if ( $card['old_price'] ) : ?>
							<span class="ttp-card__was"><?php echo esc_html( $card['old_price'] ); ?></span>
						<?php endif; ?>
						<span class="ttp-card__now"><?php echo esc_html( $card['price'] ); ?></span>
						<?php if ( $card['price_note'] ) : ?>
							<span class="ttp-card__note"><?php echo esc_html( $card['price_note'] ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<?php if ( $card['link_url'] ) : ?>
					<a class="ttp-card__cta" href="<?php echo esc_url( $card['link_url'] ); ?>">
						<?php echo esc_html( $card['link_text'] ); ?>
					</a>
				<?php endif; ?>
			</footer>
		</article>
		<?php
	}

	/**
	 * The compact card used in the sidebar.
	 *
	 * @param array $card Card data.
	 */
	public static function mini_card( $card ) {
		$image = TTP_Card::image( $card, 'medium' );
		?>
		<article class="ttp-mini">
			<?php if ( $image ) : ?>
				<div class="ttp-mini__media">
					<?php
					echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image output.
					?>
					<?php if ( $card['badge'] ) : ?>
						<span class="ttp-mini__badge"><?php echo esc_html( $card['badge'] ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="ttp-mini__body">
				<h4 class="ttp-mini__title"><?php echo esc_html( $card['title'] ); ?></h4>
				<?php if ( $card['price'] ) : ?>
					<p class="ttp-mini__price">
						<?php if ( $card['old_price'] ) : ?>
							<span class="ttp-card__was"><?php echo esc_html( $card['old_price'] ); ?></span>
						<?php endif; ?>
						<span class="ttp-card__now"><?php echo esc_html( $card['price'] ); ?></span>
						<?php if ( $card['price_note'] ) : ?>
							<span class="ttp-card__note"><?php echo esc_html( $card['price_note'] ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( $card['link_url'] ) : ?>
					<a class="ttp-mini__cta" href="<?php echo esc_url( $card['link_url'] ); ?>">
						<?php echo esc_html( $card['link_text'] ); ?>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}
}

<?php
/**
 * The admin experience: booking list columns, the detail screen, the settings
 * submenu, a CSV export and a one-click test email.
 */

defined( 'ABSPATH' ) || exit;

class TTB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		$pt = TTB_Post_Type::POST_TYPE;
		add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( "manage_edit-{$pt}_sortable_columns", array( __CLASS__, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters_query' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . $pt, array( __CLASS__, 'save_status' ), 10, 2 );

		add_action( 'admin_post_ttb_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_ttb_test_email', array( __CLASS__, 'send_test_email' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . TTB_Post_Type::POST_TYPE,
			__( 'Booking Settings', 'tt-bookings' ),
			__( 'Settings', 'tt-bookings' ),
			'manage_options',
			'ttb-settings',
			array( 'TTB_Settings', 'render_page' )
		);

		add_submenu_page(
			'edit.php?post_type=' . TTB_Post_Type::POST_TYPE,
			__( 'Export & Email Test', 'tt-bookings' ),
			__( 'Export & Test', 'tt-bookings' ),
			'manage_options',
			'ttb-tools',
			array( __CLASS__, 'render_tools' )
		);
	}

	/**
	 * Load admin styles and the media picker only on our own screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		$ours   = ( $screen && TTB_Post_Type::POST_TYPE === $screen->post_type )
			|| false !== strpos( (string) $hook, 'ttb-settings' )
			|| false !== strpos( (string) $hook, 'ttb-tools' );

		if ( ! $ours ) {
			return;
		}

		wp_enqueue_style( 'tt-bookings-admin', TTB_URL . 'assets/tt-bookings-admin.css', array(), TTB_VERSION );

		if ( false !== strpos( (string) $hook, 'ttb-settings' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'tt-bookings-admin', TTB_URL . 'assets/tt-bookings-admin.js', array( 'jquery' ), TTB_VERSION, true );
		}
	}

	/**
	 * Replace the default post columns with booking-specific ones.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'            => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'ttb_reference' => __( 'Reference', 'tt-bookings' ),
			'ttb_type'      => __( 'Type', 'tt-bookings' ),
			'ttb_customer'  => __( 'Customer', 'tt-bookings' ),
			'ttb_when'      => __( 'Travel date', 'tt-bookings' ),
			'ttb_status'    => __( 'Status', 'tt-bookings' ),
			'ttb_mail'      => __( 'Emails', 'tt-bookings' ),
			'date'          => __( 'Received', 'tt-bookings' ),
		);
	}

	/**
	 * Render one custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Booking post ID.
	 */
	public static function column( $column, $post_id ) {
		$type = get_post_meta( $post_id, TTB_Post_Type::META_TYPE, true );
		$data = get_post_meta( $post_id, TTB_Post_Type::META_DATA, true );
		$data = is_array( $data ) ? $data : array();

		switch ( $column ) {

			case 'ttb_reference':
				printf(
					'<a href="%s"><strong>%s</strong></a>',
					esc_url( (string) get_edit_post_link( $post_id ) ),
					esc_html( TTB_Post_Type::reference( $post_id ) )
				);
				break;

			case 'ttb_type':
				$form = TTB_Fields::get_form( $type );
				echo '<span class="ttb-pill ttb-pill--' . esc_attr( $type ) . '">'
					. esc_html( $form ? $form['label'] : $type )
					. '</span>';
				break;

			case 'ttb_customer':
				echo esc_html( isset( $data['full_name'] ) ? $data['full_name'] : '' );
				if ( ! empty( $data['email'] ) ) {
					printf(
						'<br><a href="mailto:%1$s">%1$s</a>',
						esc_attr( $data['email'] )
					);
				}
				if ( ! empty( $data['phone'] ) ) {
					echo '<br>' . esc_html( $data['phone'] );
				}
				break;

			case 'ttb_when':
				echo esc_html( self::travel_date( $type, $data ) );
				break;

			case 'ttb_status':
				$status   = get_post_meta( $post_id, TTB_Post_Type::META_STATUS, true );
				$statuses = TTB_Post_Type::statuses();
				$label    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['new'];
				echo '<span class="ttb-status ttb-status--' . esc_attr( $status ? $status : 'new' ) . '">'
					. esc_html( $label ) . '</span>';
				break;

			case 'ttb_mail':
				$log = get_post_meta( $post_id, TTB_Post_Type::META_MAIL, true );
				if ( ! is_array( $log ) ) {
					echo '&mdash;';
					break;
				}
				foreach ( array( 'customer' => __( 'Customer', 'tt-bookings' ), 'admin' => __( 'Inbox', 'tt-bookings' ) ) as $who => $label ) {
					$ok = ! empty( $log[ $who ]['sent'] );
					printf(
						'<span class="ttb-mail %s" title="%s">%s %s</span><br>',
						esc_attr( $ok ? 'is-ok' : 'is-bad' ),
						esc_attr( $ok ? '' : ( isset( $log[ $who ]['error'] ) ? $log[ $who ]['error'] : '' ) ),
						$ok ? '&#10003;' : '&#10007;',
						esc_html( $label )
					);
				}
				break;
		}
	}

	/**
	 * The date the customer is actually travelling, whichever field holds it.
	 *
	 * @param string $type Form type.
	 * @param array  $data Stored values.
	 * @return string
	 */
	protected static function travel_date( $type, $data ) {
		$keys   = array( 'pickup_date', 'tour_date' );
		$fields = TTB_Fields::flat_fields( $type );

		foreach ( $keys as $key ) {
			if ( ! empty( $data[ $key ] ) && isset( $fields[ $key ] ) ) {
				$out = TTB_Format::value( $fields[ $key ], $data[ $key ] );
				if ( ! empty( $data['pickup_time'] ) && isset( $fields['pickup_time'] ) ) {
					$out .= ' ' . TTB_Format::value( $fields['pickup_time'], $data['pickup_time'] );
				}
				return $out;
			}
		}

		return '—';
	}

	/**
	 * Make the received date sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['date'] = 'date';
		return $columns;
	}

	/** Dropdown filters above the booking list. */
	public static function filters() {
		$screen = get_current_screen();
		if ( ! $screen || TTB_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$type   = isset( $_GET['ttb_filter_type'] ) ? sanitize_key( wp_unslash( $_GET['ttb_filter_type'] ) ) : '';
		$status = isset( $_GET['ttb_filter_status'] ) ? sanitize_key( wp_unslash( $_GET['ttb_filter_status'] ) ) : '';
		?>
		<select name="ttb_filter_type">
			<option value=""><?php esc_html_e( 'All booking types', 'tt-bookings' ); ?></option>
			<?php foreach ( TTB_Fields::all() as $key => $form ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>>
					<?php echo esc_html( $form['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="ttb_filter_status">
			<option value=""><?php esc_html_e( 'Any status', 'tt-bookings' ); ?></option>
			<?php foreach ( TTB_Post_Type::statuses() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply those filters to the list query.
	 *
	 * @param WP_Query $query Current query.
	 */
	public static function apply_filters_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( TTB_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta = array();

		if ( ! empty( $_GET['ttb_filter_type'] ) ) {
			$meta[] = array(
				'key'   => TTB_Post_Type::META_TYPE,
				'value' => sanitize_key( wp_unslash( $_GET['ttb_filter_type'] ) ),
			);
		}

		if ( ! empty( $_GET['ttb_filter_status'] ) ) {
			$meta[] = array(
				'key'   => TTB_Post_Type::META_STATUS,
				'value' => sanitize_key( wp_unslash( $_GET['ttb_filter_status'] ) ),
			);
		}

		if ( $meta ) {
			$meta['relation'] = 'AND';
			$query->set( 'meta_query', $meta );
		}
	}

	public static function meta_boxes() {
		add_meta_box(
			'ttb-details',
			__( 'Booking details', 'tt-bookings' ),
			array( __CLASS__, 'render_details' ),
			TTB_Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ttb-handling',
			__( 'Handling', 'tt-bookings' ),
			array( __CLASS__, 'render_handling' ),
			TTB_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * The read-only detail table.
	 *
	 * @param WP_Post $post Booking post.
	 */
	public static function render_details( $post ) {
		$type = get_post_meta( $post->ID, TTB_Post_Type::META_TYPE, true );
		$data = get_post_meta( $post->ID, TTB_Post_Type::META_DATA, true );
		$data = is_array( $data ) ? $data : array();
		$rows = TTB_Format::summary( $type, $data );
		$form = TTB_Fields::get_form( $type );
		?>
		<p class="ttb-detail-head">
			<strong><?php echo esc_html( TTB_Post_Type::reference( $post->ID ) ); ?></strong>
			&nbsp;&middot;&nbsp;
			<?php echo esc_html( $form ? $form['label'] : $type ); ?>
		</p>
		<table class="widefat striped ttb-detail">
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<th scope="row" style="width:220px"><?php echo esc_html( $row['label'] ); ?></th>
						<td>
							<?php
							if ( 'email' === $row['key'] ) {
								printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $row['value'] ) );
							} elseif ( 'phone' === $row['key'] ) {
								printf( '<a href="tel:%s">%s</a>', esc_attr( preg_replace( '/\s+/', '', $row['value'] ) ), esc_html( $row['value'] ) );
							} else {
								echo nl2br( esc_html( $row['value'] ) );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Status control and the email delivery log.
	 *
	 * @param WP_Post $post Booking post.
	 */
	public static function render_handling( $post ) {
		$status = get_post_meta( $post->ID, TTB_Post_Type::META_STATUS, true );
		$status = $status ? $status : 'new';
		$log    = get_post_meta( $post->ID, TTB_Post_Type::META_MAIL, true );
		$ip     = get_post_meta( $post->ID, TTB_Post_Type::META_IP, true );

		wp_nonce_field( 'ttb_save_status', 'ttb_status_nonce' );
		?>
		<p>
			<label for="ttb-status"><strong><?php esc_html_e( 'Status', 'tt-bookings' ); ?></strong></label><br>
			<select name="ttb_status" id="ttb-status" style="width:100%">
				<?php foreach ( TTB_Post_Type::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p><strong><?php esc_html_e( 'Emails', 'tt-bookings' ); ?></strong></p>
		<?php if ( is_array( $log ) ) : ?>
			<ul class="ttb-maillog">
				<?php
				$labels = array(
					'customer' => __( 'Confirmation to customer', 'tt-bookings' ),
					'admin'    => __( 'Notification to your inbox', 'tt-bookings' ),
				);
				foreach ( $labels as $who => $label ) :
					$entry = isset( $log[ $who ] ) ? $log[ $who ] : array();
					$ok    = ! empty( $entry['sent'] );
					?>
					<li class="<?php echo esc_attr( $ok ? 'is-ok' : 'is-bad' ); ?>">
						<?php echo $ok ? '&#10003;' : '&#10007;'; ?>
						<?php echo esc_html( $label ); ?>
						<?php if ( ! empty( $entry['to'] ) ) : ?>
							<br><small><?php echo esc_html( $entry['to'] ); ?></small>
						<?php endif; ?>
						<?php if ( ! $ok && ! empty( $entry['error'] ) ) : ?>
							<br><small class="ttb-err"><?php echo esc_html( $entry['error'] ); ?></small>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><em><?php esc_html_e( 'No delivery log recorded.', 'tt-bookings' ); ?></em></p>
		<?php endif; ?>

		<?php if ( $ip ) : ?>
			<p><small><?php echo esc_html( sprintf( __( 'Submitted from %s', 'tt-bookings' ), $ip ) ); ?></small></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Persist the status dropdown.
	 *
	 * @param int     $post_id Booking post ID.
	 * @param WP_Post $post    Booking post.
	 */
	public static function save_status( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['ttb_status_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ttb_status_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ttb_save_status' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$status   = isset( $_POST['ttb_status'] ) ? sanitize_key( wp_unslash( $_POST['ttb_status'] ) ) : 'new';
		$statuses = TTB_Post_Type::statuses();

		update_post_meta( $post_id, TTB_Post_Type::META_STATUS, isset( $statuses[ $status ] ) ? $status : 'new' );
	}

	/** The Export & Test screen. */
	public static function render_tools() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export & Email Test', 'tt-bookings' ); ?></h1>

			<h2 class="title"><?php esc_html_e( 'Download bookings', 'tt-bookings' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ttb_export">
				<?php wp_nonce_field( 'ttb_export' ); ?>
				<p>
					<label for="ttb-export-type"><?php esc_html_e( 'Booking type', 'tt-bookings' ); ?></label>
					<select name="type" id="ttb-export-type">
						<option value=""><?php esc_html_e( 'All types', 'tt-bookings' ); ?></option>
						<?php foreach ( TTB_Fields::all() as $key => $form ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $form['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php submit_button( __( 'Download CSV', 'tt-bookings' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2 class="title" style="margin-top:2em"><?php esc_html_e( 'Send a test email', 'tt-bookings' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Sends a sample confirmation using your current settings, so you can check it arrives in the inbox rather than the spam folder.', 'tt-bookings' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ttb_test_email">
				<?php wp_nonce_field( 'ttb_test_email' ); ?>
				<p>
					<label for="ttb-test-to"><?php esc_html_e( 'Send to', 'tt-bookings' ); ?></label>
					<input type="email" class="regular-text" id="ttb-test-to" name="to"
						value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
				</p>
				<?php submit_button( __( 'Send test email', 'tt-bookings' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/** Stream the bookings as CSV. */
	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export bookings.', 'tt-bookings' ) );
		}

		check_admin_referer( 'ttb_export' );

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$args = array(
			'post_type'      => TTB_Post_Type::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $type && TTB_Fields::get_form( $type ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => TTB_Post_Type::META_TYPE,
					'value' => $type,
				),
			);
		}

		$posts = get_posts( $args );

		// One column per question, so a mixed export still lines up.
		$columns = array();
		$types   = $type ? array( $type ) : TTB_Fields::types();
		foreach ( $types as $one ) {
			foreach ( TTB_Fields::flat_fields( $one ) as $key => $field ) {
				if ( ! isset( $columns[ $key ] ) ) {
					$columns[ $key ] = $field['label'];
				}
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bookings-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );

		fputcsv(
			$out,
			array_merge(
				array(
					__( 'Reference', 'tt-bookings' ),
					__( 'Type', 'tt-bookings' ),
					__( 'Received', 'tt-bookings' ),
					__( 'Status', 'tt-bookings' ),
				),
				array_values( $columns )
			)
		);

		$statuses = TTB_Post_Type::statuses();

		foreach ( $posts as $post ) {
			$post_type = get_post_meta( $post->ID, TTB_Post_Type::META_TYPE, true );
			$data      = get_post_meta( $post->ID, TTB_Post_Type::META_DATA, true );
			$data      = is_array( $data ) ? $data : array();
			$fields    = TTB_Fields::flat_fields( $post_type );
			$status    = get_post_meta( $post->ID, TTB_Post_Type::META_STATUS, true );
			$form      = TTB_Fields::get_form( $post_type );

			$row = array(
				TTB_Post_Type::reference( $post->ID ),
				$form ? $form['label'] : $post_type,
				get_the_date( 'Y-m-d H:i', $post ),
				isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['new'],
			);

			foreach ( array_keys( $columns ) as $key ) {
				$row[] = ( isset( $fields[ $key ] ) && array_key_exists( $key, $data ) )
					? TTB_Format::value( $fields[ $key ], $data[ $key ] )
					: '';
			}

			fputcsv( $out, $row );
		}

		fclose( $out );
		exit;
	}

	/** Send a sample confirmation so the agency can check deliverability. */
	public static function send_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to send test emails.', 'tt-bookings' ) );
		}

		check_admin_referer( 'ttb_test_email' );

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$redirect = admin_url( 'edit.php?post_type=' . TTB_Post_Type::POST_TYPE . '&page=ttb-tools' );

		if ( ! is_email( $to ) ) {
			wp_safe_redirect( add_query_arg( 'ttb_test', 'bad-address', $redirect ) );
			exit;
		}

		$sample = array(
			'full_name'        => __( 'Test Traveller', 'tt-bookings' ),
			'email'            => $to,
			'phone'            => '+1 555 0100',
			'country'          => __( 'Test', 'tt-bookings' ),
			'direction'        => 'arrival',
			'pickup_location'  => __( 'International Airport, Terminal 1', 'tt-bookings' ),
			'dropoff_location' => __( 'City centre hotel', 'tt-bookings' ),
			'pickup_date'      => current_time( 'Y-m-d' ),
			'pickup_time'      => '09:30',
			'flight_number'    => 'TT 100',
			'passengers'       => '2',
			'luggage'          => '2',
			'vehicle_class'    => 'sedan',
			'notes'            => __( 'This is a test message sent from the WordPress admin.', 'tt-bookings' ),
		);

		$log = TTB_Mailer::send_all( 0, 'airport', $sample );

		wp_safe_redirect(
			add_query_arg( 'ttb_test', ! empty( $log['customer']['sent'] ) ? 'sent' : 'failed', $redirect )
		);
		exit;
	}

	/** Result notice for the test email. */
	public static function notices() {
		if ( empty( $_GET['ttb_test'] ) ) {
			return;
		}

		$result = sanitize_key( wp_unslash( $_GET['ttb_test'] ) );

		$messages = array(
			'sent'        => array( 'success', __( 'Test email sent. If it does not arrive within a few minutes, check the spam folder and your SMTP settings.', 'tt-bookings' ) ),
			'failed'      => array( 'error', __( 'WordPress could not send the test email. The site needs an SMTP plugin or a working mail server.', 'tt-bookings' ) ),
			'bad-address' => array( 'error', __( 'That is not a valid email address.', 'tt-bookings' ) ),
		);

		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $result ][0] ),
			esc_html( $messages[ $result ][1] )
		);
	}
}

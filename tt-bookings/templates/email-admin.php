<?php
/**
 * Notification email sent to the agency inbox.
 *
 * Denser than the customer version - the point is to act on it quickly, so
 * the contact details sit at the top next to call and email buttons.
 *
 * @var array  $form
 * @var array  $data
 * @var string $reference
 * @var array  $rows
 * @var string $edit_link
 * @var string $brand
 * @var string $company
 * @var string $site
 */

defined( 'ABSPATH' ) || exit;

$customer_name  = isset( $data['full_name'] ) ? $data['full_name'] : __( 'Unnamed', 'tt-bookings' );
$customer_email = isset( $data['email'] ) ? $data['email'] : '';
$customer_phone = isset( $data['phone'] ) ? $data['phone'] : '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $reference ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
	<tr>
		<td align="center">
			<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.08);">

				<tr>
					<td style="background:#102a43;padding:22px 28px;">
						<div style="font-size:12px;text-transform:uppercase;letter-spacing:1.2px;color:#9fb3c8;">
							<?php esc_html_e( 'New booking request', 'tt-bookings' ); ?>
						</div>
						<div style="font-size:20px;font-weight:700;color:#ffffff;margin-top:4px;">
							<?php echo esc_html( $form['label'] ); ?>
							<span style="color:<?php echo esc_attr( $brand ); ?>;">&middot;</span>
							<?php echo esc_html( $reference ); ?>
						</div>
					</td>
				</tr>

				<tr>
					<td style="padding:24px 28px 8px 28px;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;border-radius:10px;">
							<tr>
								<td style="padding:16px 18px;">
									<div style="font-size:17px;font-weight:700;color:#102a43;">
										<?php echo esc_html( $customer_name ); ?>
									</div>
									<div style="font-size:14px;color:#486581;margin-top:4px;">
										<?php if ( $customer_email ) : ?>
											<a href="mailto:<?php echo esc_attr( $customer_email ); ?>" style="color:#334e68;text-decoration:none;">
												<?php echo esc_html( $customer_email ); ?>
											</a>
										<?php endif; ?>
										<?php if ( $customer_email && $customer_phone ) : ?>
											&nbsp;&middot;&nbsp;
										<?php endif; ?>
										<?php if ( $customer_phone ) : ?>
											<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $customer_phone ) ); ?>" style="color:#334e68;text-decoration:none;">
												<?php echo esc_html( $customer_phone ); ?>
											</a>
										<?php endif; ?>
									</div>
									<div style="margin-top:14px;">
										<?php if ( $customer_email ) : ?>
											<a href="mailto:<?php echo esc_attr( $customer_email ); ?>?subject=<?php echo rawurlencode( sprintf( '%s - %s', $form['label'], $reference ) ); ?>"
												style="display:inline-block;background:<?php echo esc_attr( $brand ); ?>;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:9px 16px;border-radius:8px;margin-right:8px;">
												<?php esc_html_e( 'Reply to customer', 'tt-bookings' ); ?>
											</a>
										<?php endif; ?>
										<?php if ( $edit_link ) : ?>
											<a href="<?php echo esc_url( $edit_link ); ?>"
												style="display:inline-block;background:#ffffff;border:1px solid #d9e2ec;color:#334e68;text-decoration:none;font-size:14px;font-weight:600;padding:8px 16px;border-radius:8px;">
												<?php esc_html_e( 'Open in dashboard', 'tt-bookings' ); ?>
											</a>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<tr>
					<td style="padding:16px 28px 28px 28px;">
						<h2 style="margin:0 0 10px 0;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#829ab1;">
							<?php esc_html_e( 'Request details', 'tt-bookings' ); ?>
						</h2>
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td style="padding:8px 0;border-bottom:1px solid #f0f4f8;font-size:14px;color:#829ab1;width:42%;vertical-align:top;">
										<?php echo esc_html( $row['label'] ); ?>
									</td>
									<td style="padding:8px 0;border-bottom:1px solid #f0f4f8;font-size:14px;color:#243b53;font-weight:600;">
										<?php echo nl2br( esc_html( $row['value'] ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</td>
				</tr>

				<tr>
					<td style="background:#f0f4f8;padding:14px 28px;text-align:center;font-size:12px;color:#9fb3c8;">
						<?php
						printf(
							/* translators: %s: website host */
							esc_html__( 'Sent automatically by %s', 'tt-bookings' ),
							esc_html( wp_parse_url( $site, PHP_URL_HOST ) )
						);
						?>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>
</body>
</html>

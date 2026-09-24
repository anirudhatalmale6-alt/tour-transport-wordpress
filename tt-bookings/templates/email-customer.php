<?php
/**
 * Confirmation email sent to the customer.
 *
 * Table-based with inline styles, because that is what email clients render
 * reliably. Available: $form, $data, $reference, $rows, $highlights, $brand,
 * $company, $phone, $site, $logo.
 *
 * @var array  $form
 * @var array  $data
 * @var string $reference
 * @var array  $rows
 * @var array  $highlights
 * @var string $brand
 * @var string $company
 * @var string $phone
 * @var string $site
 * @var string $logo
 */

defined( 'ABSPATH' ) || exit;

$first_name = '';
if ( ! empty( $data['full_name'] ) ) {
	$parts      = preg_split( '/\s+/', trim( $data['full_name'] ) );
	$first_name = $parts[0];
}
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
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.08);">

				<tr>
					<td style="background:<?php echo esc_attr( $brand ); ?>;padding:28px 32px;">
						<?php if ( $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $company ); ?>" style="max-height:44px;display:block;border:0;">
						<?php else : ?>
							<div style="font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.2px;">
								<?php echo esc_html( $company ); ?>
							</div>
						<?php endif; ?>
						<div style="margin-top:10px;font-size:13px;color:rgba(255,255,255,.85);text-transform:uppercase;letter-spacing:1.2px;">
							<?php echo esc_html( $form['label'] ); ?>
						</div>
					</td>
				</tr>

				<tr>
					<td style="padding:32px 32px 8px 32px;">
						<h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;color:#102a43;">
							<?php
							echo esc_html(
								$first_name
									/* translators: %s: customer first name */
									? sprintf( __( 'Thank you, %s - we have your request', 'tt-bookings' ), $first_name )
									: __( 'Thank you - we have your request', 'tt-bookings' )
							);
							?>
						</h1>
						<p style="margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#486581;">
							<?php
							echo esc_html__( 'This is a confirmation that your request reached us. Our team is checking availability and will reply with the price and final details shortly. Nothing is charged at this stage.', 'tt-bookings' );
							?>
						</p>

						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;border-radius:10px;margin:0 0 24px 0;">
							<tr>
								<td style="padding:16px 20px;">
									<div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#829ab1;">
										<?php esc_html_e( 'Your reference', 'tt-bookings' ); ?>
									</div>
									<div style="font-size:20px;font-weight:700;color:#102a43;letter-spacing:.5px;">
										<?php echo esc_html( $reference ); ?>
									</div>
								</td>
							</tr>
						</table>

						<?php if ( ! empty( $highlights ) ) : ?>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px 0;">
								<tr>
									<?php foreach ( $highlights as $highlight ) : ?>
										<td style="padding:0 8px 0 0;vertical-align:top;">
											<div style="border:1px solid #e4e7eb;border-radius:10px;padding:12px 14px;">
												<div style="font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#829ab1;">
													<?php echo esc_html( $highlight['label'] ); ?>
												</div>
												<div style="font-size:15px;font-weight:600;color:#243b53;margin-top:2px;">
													<?php echo esc_html( $highlight['value'] ); ?>
												</div>
											</div>
										</td>
									<?php endforeach; ?>
								</tr>
							</table>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<td style="padding:0 32px 8px 32px;">
						<h2 style="margin:0 0 10px 0;font-size:14px;text-transform:uppercase;letter-spacing:1px;color:#829ab1;">
							<?php esc_html_e( 'What you told us', 'tt-bookings' ); ?>
						</h2>
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td style="padding:9px 0;border-bottom:1px solid #f0f4f8;font-size:14px;color:#829ab1;width:45%;vertical-align:top;">
										<?php echo esc_html( $row['label'] ); ?>
									</td>
									<td style="padding:9px 0;border-bottom:1px solid #f0f4f8;font-size:14px;color:#243b53;font-weight:600;">
										<?php echo nl2br( esc_html( $row['value'] ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</td>
				</tr>

				<tr>
					<td style="padding:24px 32px 32px 32px;">
						<p style="margin:0 0 4px 0;font-size:14px;line-height:1.6;color:#486581;">
							<?php esc_html_e( 'Need to change something? Just reply to this email and quote your reference.', 'tt-bookings' ); ?>
						</p>
						<?php if ( $phone ) : ?>
							<p style="margin:0;font-size:14px;line-height:1.6;color:#486581;">
								<?php
								printf(
									/* translators: %s: agency phone number */
									esc_html__( 'In a hurry? Call us on %s.', 'tt-bookings' ),
									'<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $phone ) ) . '" style="color:' . esc_attr( $brand ) . ';text-decoration:none;font-weight:600;">' . esc_html( $phone ) . '</a>'
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<td style="background:#f0f4f8;padding:18px 32px;text-align:center;">
						<div style="font-size:13px;color:#829ab1;">
							<?php echo esc_html( $company ); ?>
							&middot;
							<a href="<?php echo esc_url( $site ); ?>" style="color:#627d98;text-decoration:none;">
								<?php echo esc_html( wp_parse_url( $site, PHP_URL_HOST ) ); ?>
							</a>
						</div>
						<div style="font-size:12px;color:#9fb3c8;margin-top:6px;">
							<?php esc_html_e( 'You are receiving this because you sent a booking request on our website.', 'tt-bookings' ); ?>
						</div>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>
</body>
</html>

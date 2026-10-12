<?php
/**
 * Plugin Name:       Tour & Transport Bookings
 * Description:       Three booking forms - Airport Transfer, Tour and Hire a Vehicle. Each submission is stored in the WordPress admin, confirmed to the customer by email and forwarded to the agency inbox.
 * Version:           1.3.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Anirudha Talmale
 * Text Domain:       tt-bookings
 */

defined( 'ABSPATH' ) || exit;

define( 'TTB_VERSION', '1.3.1' );
define( 'TTB_FILE', __FILE__ );
define( 'TTB_PATH', plugin_dir_path( __FILE__ ) );
define( 'TTB_URL', plugin_dir_url( __FILE__ ) );

require_once TTB_PATH . 'includes/class-ttb-fields.php';
require_once TTB_PATH . 'includes/class-ttb-format.php';
require_once TTB_PATH . 'includes/class-ttb-post-type.php';
require_once TTB_PATH . 'includes/class-ttb-form.php';
require_once TTB_PATH . 'includes/class-ttb-handler.php';
require_once TTB_PATH . 'includes/class-ttb-mailer.php';
require_once TTB_PATH . 'includes/class-ttb-settings.php';
require_once TTB_PATH . 'includes/class-ttb-admin.php';

/**
 * Boot every component once WordPress is ready.
 */
function ttb_bootstrap() {
	TTB_Post_Type::init();
	TTB_Form::init();
	TTB_Handler::init();
	TTB_Settings::init();
	TTB_Admin::init();
}
add_action( 'plugins_loaded', 'ttb_bootstrap' );

/**
 * Flush permalinks once on activation so the booking post type resolves.
 */
function ttb_activate() {
	TTB_Post_Type::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ttb_activate' );

/**
 * Leave the rewrite rules clean on deactivation. Submissions are kept.
 */
function ttb_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ttb_deactivate' );

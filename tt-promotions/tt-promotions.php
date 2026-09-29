<?php
/**
 * Plugin Name:       Tour & Transport Promotions
 * Description:       Add a deal or a destination in the WordPress admin - image, short text and price - and it appears on the Promotions page and as a sidebar highlight across the site. Expired deals disappear on their own.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Anirudha Talmale
 * Text Domain:       tt-promotions
 */

defined( 'ABSPATH' ) || exit;

define( 'TTP_VERSION', '1.1.0' );
define( 'TTP_FILE', __FILE__ );
define( 'TTP_PATH', plugin_dir_path( __FILE__ ) );
define( 'TTP_URL', plugin_dir_url( __FILE__ ) );

require_once TTP_PATH . 'includes/class-ttp-post-type.php';
require_once TTP_PATH . 'includes/class-ttp-meta.php';
require_once TTP_PATH . 'includes/class-ttp-query.php';
require_once TTP_PATH . 'includes/class-ttp-card.php';
require_once TTP_PATH . 'includes/class-ttp-render.php';
require_once TTP_PATH . 'includes/class-ttp-widget.php';
require_once TTP_PATH . 'includes/class-ttp-destination.php';
require_once TTP_PATH . 'includes/class-ttp-admin.php';

/**
 * Boot every component once WordPress is ready.
 */
function ttp_bootstrap() {
	TTP_Post_Type::init();
	TTP_Meta::init();
	TTP_Render::init();
	TTP_Widget::init();
	TTP_Destination::init();
	TTP_Admin::init();
}
add_action( 'plugins_loaded', 'ttp_bootstrap' );

function ttp_activate() {
	TTP_Post_Type::register();
	TTP_Destination::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ttp_activate' );

function ttp_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ttp_deactivate' );

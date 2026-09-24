<?php
/**
 * The Promotions post type.
 *
 * Kept out of the public query so a deal never becomes an orphan page that
 * search engines index; each card links wherever the agency points it.
 */

defined( 'ABSPATH' ) || exit;

class TTP_Post_Type {

	const POST_TYPE = 'tt_promotion';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Promotions', 'tt-promotions' ),
					'singular_name'         => __( 'Promotion', 'tt-promotions' ),
					'menu_name'             => __( 'Promotions', 'tt-promotions' ),
					'add_new'               => __( 'Add deal', 'tt-promotions' ),
					'add_new_item'          => __( 'Add a new deal', 'tt-promotions' ),
					'edit_item'             => __( 'Edit deal', 'tt-promotions' ),
					'new_item'              => __( 'New deal', 'tt-promotions' ),
					'view_item'             => __( 'View deal', 'tt-promotions' ),
					'search_items'          => __( 'Search deals', 'tt-promotions' ),
					'not_found'             => __( 'No deals yet. Add your first one.', 'tt-promotions' ),
					'not_found_in_trash'    => __( 'No deals in the trash.', 'tt-promotions' ),
					'featured_image'        => __( 'Deal image', 'tt-promotions' ),
					'set_featured_image'    => __( 'Set deal image', 'tt-promotions' ),
					'remove_featured_image' => __( 'Remove deal image', 'tt-promotions' ),
					'use_featured_image'    => __( 'Use as deal image', 'tt-promotions' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_position'       => 25,
				'menu_icon'           => 'dashicons-megaphone',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}
}

<?php
/**
 * Video custom post type + taxonomies.
 *
 * Registered in code (not CPT-UI) so it deploys local -> staging -> prod via
 * files. Reuses the existing `audience` taxonomy for the card label; adds a
 * hierarchical `video_topic` taxonomy used to auto-group related videos.
 */

defined( 'ABSPATH' ) || exit;

function hcp_videos_register_cpt(): void {
	register_post_type( 'video', array(
		'labels'       => array(
			'name'               => __( 'Videos', 'hcp-videos' ),
			'singular_name'      => __( 'Video', 'hcp-videos' ),
			'add_new_item'       => __( 'Add New Video', 'hcp-videos' ),
			'edit_item'          => __( 'Edit Video', 'hcp-videos' ),
			'new_item'           => __( 'New Video', 'hcp-videos' ),
			'view_item'          => __( 'View Video', 'hcp-videos' ),
			'search_items'       => __( 'Search Videos', 'hcp-videos' ),
			'all_items'          => __( 'All Videos', 'hcp-videos' ),
			'menu_name'          => __( 'Videos', 'hcp-videos' ),
		),
		'public'       => true,
		'show_ui'      => true,
		'show_in_menu' => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-video-alt3',
		'menu_position'=> 22,
		'has_archive'  => false, // the Tools & Videos page is the listing
		'rewrite'      => array( 'slug' => 'video', 'with_front' => false ),
		'supports'     => array( 'title', 'editor', 'thumbnail' ), // editor body = description
	) );

	// Group/topic taxonomy for related-video auto-fallback (e.g. FESS, Hydralyte).
	register_taxonomy( 'video_topic', 'video', array(
		'labels'            => array(
			'name'          => __( 'Video Topics', 'hcp-videos' ),
			'singular_name' => __( 'Video Topic', 'hcp-videos' ),
			'menu_name'     => __( 'Topics', 'hcp-videos' ),
		),
		'hierarchical'      => true,
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => false,
	) );

	// Reuse the site's existing `audience` taxonomy for the card label, if present.
	if ( taxonomy_exists( 'audience' ) ) {
		register_taxonomy_for_object_type( 'audience', 'video' );
	}
}
add_action( 'init', 'hcp_videos_register_cpt' );

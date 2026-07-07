<?php
/**
 * ACF field group for the Video CPT, registered in code so it deploys via files.
 *
 * Field NAME `vimeo` intentionally matches the existing resources convention
 * (get_field('vimeo')); the field KEY is namespaced to avoid collisions.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'      => 'group_hcp_video',
		'title'    => 'Video Details',
		'fields'   => array(
			array(
				'key'          => 'field_hcpvid_vimeo',
				'label'        => 'Vimeo URL or ID',
				'name'         => 'vimeo',
				'type'         => 'text',
				'instructions' => 'Paste the Vimeo share URL or just the numeric ID (e.g. 1122083239).',
				'required'     => 1,
			),
			array(
				'key'          => 'field_hcpvid_duration',
				'label'        => 'Duration (optional)',
				'name'         => 'duration',
				'type'         => 'text',
				'instructions' => 'e.g. 2:24. Leave blank to auto-pull from Vimeo on save.',
				'placeholder'  => 'auto from Vimeo',
			),
			array(
				'key'           => 'field_hcpvid_listed',
				'label'         => 'Show in Tools & Videos listing',
				'name'          => 'video_listed',
				'type'          => 'true_false',
				'instructions'  => 'On = appears in the listing grid. Off = unlisted (reachable by direct link only).',
				'default_value' => 1,
				'ui'            => 1,
			),
			array(
				'key'           => 'field_hcpvid_related_autofill',
				'label'         => 'Auto-fill related videos',
				'name'          => 'related_autofill',
				'type'          => 'true_false',
				'instructions'  => 'On = fill the sidebar with other videos from the same series/topic when you have not picked any below. Off = show only the videos you pick below (none if empty).',
				'default_value' => 1,
				'ui'            => 1,
			),
			array(
				'key'          => 'field_hcpvid_related',
				'label'        => 'Related videos',
				'name'         => 'related_videos',
				'type'         => 'post_object',
				'instructions' => 'Search and pick specific related videos, in order. Leave empty to auto-fill (unless auto-fill is off above).',
				'post_type'    => array( 'video' ),
				'multiple'     => 1,
				'ui'           => 1,
				'return_format'=> 'id',
			),
			array(
				'key'          => 'field_hcpvid_ad_image',
				'label'        => 'Sidebar ad image (300×250)',
				'name'         => 'ad_image',
				'type'         => 'image',
				'instructions' => 'Medium-rectangle banner (300×250). Leave empty to use the site default (if set).',
				'return_format'=> 'array',
				'preview_size' => 'medium',
			),
			array(
				'key'          => 'field_hcpvid_ad_link',
				'label'        => 'Sidebar ad link',
				'name'         => 'ad_link',
				'type'         => 'url',
				'instructions' => 'Where the ad banner clicks through to.',
			),
			array(
				'key'          => 'field_hcpvid_resources',
				'label'        => 'Related Resources',
				'name'         => 'video_resources',
				'type'         => 'post_object',
				'instructions' => 'Search and pick resources (tools, leave-behinds, downloads) or blog articles to show at the bottom of this video. Leave empty for no resources section.',
				'post_type'    => array( 'resources', 'post' ),
				'multiple'     => 1,
				'ui'           => 1,
				'return_format'=> 'id',
			),
		),
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'video',
				),
			),
		),
		'menu_order' => 0,
		'position'   => 'normal',
		'style'      => 'default',
	) );
} );

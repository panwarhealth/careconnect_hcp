<?php
/**
 * ACF field group for the Video CPT, registered in code so it deploys via files.
 *
 * The video file, poster, duration and captions are managed by the "Video
 * file" box (admin-video-file.php), not ACF. The original Vimeo ID stays in
 * the `vimeo` postmeta (migrations match on it) but is no longer edited.
 * Field KEYS are namespaced to avoid collisions.
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

	$custom_only = array( array( array( 'field' => 'field_hcpvid_endcard_mode', 'operator' => '==', 'value' => 'custom' ) ) );

	acf_add_local_field_group( array(
		'key'      => 'group_hcp_video_endcard',
		'title'    => 'End card',
		'fields'   => array(
			array(
				'key'           => 'field_hcpvid_endcard_mode',
				'label'         => 'When the video ends, show',
				'name'          => 'endcard_mode',
				'type'          => 'select',
				'choices'       => array(
					'auto'   => 'Automatic (next episode, series page or a related video)',
					'custom' => 'Custom link',
					'off'    => 'Nothing',
				),
				'default_value' => 'auto',
			),
			array(
				'key'               => 'field_hcpvid_endcard_preview',
				'label'             => 'Automatic end card',
				'name'              => '',
				'type'              => 'message',
				'message'           => '',
				'conditional_logic' => array( array( array( 'field' => 'field_hcpvid_endcard_mode', 'operator' => '==', 'value' => 'auto' ) ) ),
			),
			array(
				'key'               => 'field_hcpvid_endcard_heading',
				'label'             => 'Heading',
				'name'              => 'endcard_heading',
				'type'              => 'text',
				'placeholder'       => 'e.g. Want to try it with your patients?',
				'conditional_logic' => $custom_only,
			),
			array(
				'key'               => 'field_hcpvid_endcard_title',
				'label'             => 'Title',
				'name'              => 'endcard_title',
				'type'              => 'text',
				'placeholder'       => 'e.g. Order free FESS samples',
				'conditional_logic' => $custom_only,
			),
			array(
				'key'               => 'field_hcpvid_endcard_url',
				'label'             => 'Link',
				'name'              => 'endcard_url',
				'type'              => 'url',
				'required'          => 1,
				'conditional_logic' => $custom_only,
			),
			array(
				'key'               => 'field_hcpvid_endcard_label',
				'label'             => 'Button text',
				'name'              => 'endcard_label',
				'type'              => 'text',
				'placeholder'       => 'Find out more',
				'conditional_logic' => $custom_only,
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
		'menu_order' => 1,
		'position'   => 'normal',
		'style'      => 'default',
	) );
} );

/**
 * Show what "Automatic" resolves to for the video being edited.
 */
add_filter( 'acf/load_field/key=field_hcpvid_endcard_preview', function ( $field ) {
	$post_id = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview.
	$card    = $post_id ? hcp_videos_endcard_auto( $post_id ) : null;

	$field['message'] = $card
		? sprintf(
			'<strong>%s:</strong> %s<br><a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_html( $card['heading'] ),
			esc_html( $card['title'] ),
			esc_url( $card['url'] ),
			esc_html( $card['url'] )
		)
		: 'Nothing yet. Add this video to a series or pick related videos, or choose a custom link.';
	return $field;
} );

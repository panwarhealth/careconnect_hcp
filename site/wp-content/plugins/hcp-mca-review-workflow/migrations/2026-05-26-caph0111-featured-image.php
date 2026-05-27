<?php
/**
 * Set featured image and excerpt for CAPH0111 article post.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Set featured image (v7) and excerpt for CAPH0111 paediatric URTIs article.',

	'up' => function (): string {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$post = get_page_by_path( 'rethink-your-approach-to-paediatric-urtis', OBJECT, 'post' );
		if ( ! $post ) {
			throw new \RuntimeException( 'CAPH0111 article post not found.' );
		}

		$upload_dir = wp_upload_dir();
		$thumb_file = 'dr-johnny-taitz-feature-v7.png';
		$thumb_path = $upload_dir['basedir'] . '/2026/05/' . $thumb_file;

		$thumb_id = wp_insert_attachment(
			[
				'post_title'     => 'Dr Jonny Taitz Feature Image',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			],
			$thumb_path
		);

		update_post_meta( $thumb_id, '_wp_attached_file', '2026/05/' . $thumb_file );
		$metadata = wp_generate_attachment_metadata( $thumb_id, $thumb_path );
		wp_update_attachment_metadata( $thumb_id, $metadata );

		update_post_meta( $post->ID, '_thumbnail_id', $thumb_id );

		wp_update_post( [
			'ID'           => $post->ID,
			'post_excerpt' => 'Think upper respiratory tract infections (URTIs) in young children are run of the mill? Pressure from parents and caregivers for a prescription-based solution, and the time required to provide reassurance that recurrent presentations are normal, can challenge even the most experienced physicians.',
		] );

		return "Set featured image (attachment $thumb_id) and excerpt on post {$post->ID}.";
	},
];

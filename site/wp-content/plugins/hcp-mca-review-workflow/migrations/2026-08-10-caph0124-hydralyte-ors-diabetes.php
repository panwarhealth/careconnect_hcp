<?php
/**
 * CAPH0124 Hydralyte — Using Oral Rehydration Solutions in Diabetes.
 *
 * Resource post only. Tagged for Pharmacist and GP so both filters match;
 * the card itself prints "Healthcare Professional" alone (resources_fn).
 *
 * Files must exist in wp-content/uploads/2026/08/ before running:
 *   caph0124-hydralyte-ors-diabetes.pdf
 *   caph0124-hydralyte-ors-diabetes-thumb.jpg
 *
 * Idempotent: an existing post is skipped.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0124 Hydralyte "Using Oral Rehydration Solutions in Diabetes" resource.',

	'up' => function (): string {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$title      = 'Using Oral Rehydration Solutions in Diabetes';
		$slug       = 'using-oral-rehydration-solutions-in-diabetes';
		$pdf_file   = 'caph0124-hydralyte-ors-diabetes.pdf';
		$thumb_file = 'caph0124-hydralyte-ors-diabetes-thumb.jpg';
		$subdir     = '2026/08/';

		$therapy_area = [ 12 ];              // Rehydration
		$brand        = [ 23 ];              // Hydralyte
		$audience     = [ 32, 214, 198 ];    // Healthcare Professional, Pharmacist, GP

		$upload_dir = wp_upload_dir();
		$base       = $upload_dir['basedir'] . '/' . $subdir;

		foreach ( [ $pdf_file, $thumb_file ] as $f ) {
			if ( ! file_exists( $base . $f ) ) {
				throw new \RuntimeException( "Missing upload: {$subdir}{$f}" );
			}
		}

		$existing = get_page_by_path( $slug, OBJECT, 'resources' );
		if ( $existing ) {
			return "'{$slug}' already exists (ID {$existing->ID}) — skipped";
		}

		$pdf_path = $base . $pdf_file;
		$pdf_id   = wp_insert_attachment(
			[
				'post_title'     => $title,
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			],
			$pdf_path
		);
		update_post_meta( $pdf_id, '_wp_attached_file', $subdir . $pdf_file );

		$thumb_path = $base . $thumb_file;
		$thumb_id   = wp_insert_attachment(
			[
				'post_title'     => $title . ' Thumbnail',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			],
			$thumb_path
		);
		update_post_meta( $thumb_id, '_wp_attached_file', $subdir . $thumb_file );
		wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $thumb_path ) );

		$post_id = wp_insert_post(
			[
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
				'post_type'   => 'resources',
				'post_author' => 1,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( "wp_insert_post failed for '{$slug}': " . $post_id->get_error_message() );
		}

		update_post_meta( $post_id, 'download', $pdf_id );
		update_post_meta( $post_id, '_download', 'field_6125c6a030fde' );
		update_post_meta( $post_id, '_thumbnail_id', $thumb_id );

		wp_set_object_terms( $post_id, $therapy_area, 'therapy_area' );
		wp_set_object_terms( $post_id, $brand, 'brand' );
		wp_set_object_terms( $post_id, $audience, 'audience' );

		return "Created '{$slug}' (post {$post_id}, pdf {$pdf_id}, thumb {$thumb_id})";
	},
];

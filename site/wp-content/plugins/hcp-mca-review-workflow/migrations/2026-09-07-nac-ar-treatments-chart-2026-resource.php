<?php
/**
 * National Asthma Council Allergic Rhinitis Treatments Chart, 2026 edition.
 *
 *   1. Creates the 2026 resource on /resources/ (FESS, Nasal & Sinus Health,
 *      all four HCP audiences so it lists under every audience filter).
 *   2. Retitles the existing 2025 resource (post 167) with its year so the two
 *      sit apart on the Resources page. Slug unchanged.
 *
 * Files must exist in wp-content/uploads/2026/09/ before running:
 *   nac-ar-treatments-chart-2026.pdf
 *   nac-ar-treatments-chart-2026-thumb.jpg
 *
 * Idempotent: an existing 2026 resource is skipped, the retitle is a no-op
 * once applied.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create the 2026 NAC Allergic Rhinitis Treatments Chart resource and retitle the 2025 one.',

	'up' => function (): string {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$title      = '2026 National Asthma Council Allergic Rhinitis Treatments Chart';
		$slug       = '2026-national-asthma-council-allergic-rhinitis-treatments-chart';
		$pdf_file   = 'nac-ar-treatments-chart-2026.pdf';
		$thumb_file = 'nac-ar-treatments-chart-2026-thumb.jpg';
		$subdir     = '2026/09/';

		$therapy_area = [ 10 ];                 // Nasal & Sinus Health
		$brand        = [ 22 ];                 // FESS
		$audience     = [ 32, 230, 214, 198 ];  // Healthcare Professional, Allergist, Pharmacist, GP

		$previous_id    = 167;
		$previous_title = '2025 National Asthma Council Allergic Rhinitis Treatments Chart';

		$upload_dir = wp_upload_dir();
		$base       = $upload_dir['basedir'] . '/' . $subdir;
		$notes      = [];

		foreach ( [ $pdf_file, $thumb_file ] as $f ) {
			if ( ! file_exists( $base . $f ) ) {
				throw new \RuntimeException( "Missing upload: {$subdir}{$f}" );
			}
		}

		$existing = get_page_by_path( $slug, OBJECT, 'resources' );

		if ( $existing ) {
			$notes[] = "'{$slug}' already exists (ID {$existing->ID}) — skipped";
		} else {
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

			$notes[] = "Created '{$slug}' (post {$post_id}, pdf {$pdf_id}, thumb {$thumb_id})";
		}

		$previous = get_post( $previous_id );
		if ( ! $previous || 'resources' !== $previous->post_type ) {
			throw new \RuntimeException( "Resource {$previous_id} not found — 2025 chart not retitled." );
		}

		if ( $previous->post_title === $previous_title ) {
			$notes[] = "Resource {$previous_id} already titled";
		} else {
			$updated = wp_update_post( [ 'ID' => $previous_id, 'post_title' => $previous_title ], true );
			if ( is_wp_error( $updated ) ) {
				throw new \RuntimeException( "wp_update_post failed for {$previous_id}: " . $updated->get_error_message() );
			}
			$notes[] = "Retitled resource {$previous_id}";
		}

		return implode( '; ', $notes );
	},
];

<?php
/**
 * CAPH0102 Zaditen — Allergic Conjunctivitis Treatments Chart.
 *
 * Creates two things:
 *   1. The resource post on /resources/ (card links direct to the PDF).
 *   2. A PDF redirect page at /allergic-conjunctivitis-chart/ for the eDM,
 *      banner and MREC CTAs, so UTM-tagged clicks register in GA4 before
 *      bouncing to the file.
 *
 * Files must exist in wp-content/uploads/2026/08/ before running:
 *   caph0102-zaditen-ac-treatments-chart.pdf
 *   caph0102-zaditen-ac-treatments-chart-thumb.jpg
 *
 * Idempotent: existing posts are skipped, the redirect URL is refreshed.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0102 Zaditen Allergic Conjunctivitis Treatments Chart resource + eDM redirect page.',

	'up' => function (): string {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$title      = 'Allergic Conjunctivitis Treatments Chart';
		$slug       = 'allergic-conjunctivitis-treatments-chart';
		$pdf_file   = 'caph0102-zaditen-ac-treatments-chart.pdf';
		$thumb_file = 'caph0102-zaditen-ac-treatments-chart-thumb.jpg';
		$subdir     = '2026/08/';

		$redirect_slug  = 'allergic-conjunctivitis-chart';
		$redirect_title = 'Allergic Conjunctivitis Chart';

		$therapy_area = [ 14 ];                    // Eye Care
		$brand        = [ 257 ];                   // Zaditen
		$audience     = [ 32, 230, 214, 198 ];     // HCP, Allergist, Pharmacist, GP

		$upload_dir = wp_upload_dir();
		$base       = $upload_dir['basedir'] . '/' . $subdir;
		$notes      = [];

		foreach ( [ $pdf_file, $thumb_file ] as $f ) {
			if ( ! file_exists( $base . $f ) ) {
				throw new \RuntimeException( "Missing upload: {$subdir}{$f}" );
			}
		}

		// --- Resource post ---
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

		// --- eDM redirect page ---
		$pdf_url          = $upload_dir['baseurl'] . '/' . $subdir . $pdf_file;
		$existing_redirect = get_page_by_path( $redirect_slug, OBJECT, 'page' );

		if ( $existing_redirect ) {
			update_post_meta( $existing_redirect->ID, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $existing_redirect->ID, '_pdf_redirect_url', $pdf_url );
			$notes[] = "Updated redirect page '{$redirect_slug}' (ID {$existing_redirect->ID})";
		} else {
			$redirect_id = wp_insert_post(
				[
					'post_title'   => $redirect_title,
					'post_name'    => $redirect_slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
				],
				true
			);

			if ( is_wp_error( $redirect_id ) ) {
				throw new \RuntimeException( "wp_insert_post failed for '{$redirect_slug}': " . $redirect_id->get_error_message() );
			}

			update_post_meta( $redirect_id, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $redirect_id, '_pdf_redirect_url', $pdf_url );

			$notes[] = "Created redirect page '{$redirect_slug}' (ID {$redirect_id})";
		}

		return implode( "\n", $notes );
	},
];

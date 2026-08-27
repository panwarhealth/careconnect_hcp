<?php
/**
 * CAPH0128 FESS — Nasal Saline for Allergic Rhinitis Pharmacist Factsheet.
 *
 * Creates two things:
 *   1. The resource post on /resources/ (card links direct to the PDF).
 *   2. A PDF redirect page at /nasal-saline-allergic-rhinitis-factsheet/ for
 *      the CAPH0116 banner and MREC CTAs, so UTM-tagged clicks register in
 *      GA4 before bouncing to the file.
 *
 * Files must exist in wp-content/uploads/2026/08/ before running:
 *   caph0128-fess-allergy-pharmacist-factsheet.pdf
 *   caph0128-fess-allergy-pharmacist-factsheet-thumb.jpg
 *
 * Idempotent: an existing resource is skipped, the redirect URL is refreshed.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0128 FESS Nasal Saline for Allergic Rhinitis pharmacist factsheet resource + campaign redirect page.',

	'up' => function (): string {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$title      = 'Nasal Saline for Allergic Rhinitis – Pharmacist Factsheet';
		$slug       = 'nasal-saline-for-allergic-rhinitis-pharmacist-factsheet';
		$pdf_file   = 'caph0128-fess-allergy-pharmacist-factsheet.pdf';
		$thumb_file = 'caph0128-fess-allergy-pharmacist-factsheet-thumb.jpg';
		$subdir     = '2026/08/';

		$redirect_slug  = 'nasal-saline-allergic-rhinitis-factsheet';
		$redirect_title = 'Nasal Saline for Allergic Rhinitis Factsheet';

		$therapy_area = [ 10 ];        // Nasal & Sinus Health
		$brand        = [ 22 ];        // FESS
		$audience     = [ 32, 214 ];   // Healthcare Professional, Pharmacist

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

		// --- Campaign redirect page ---
		$pdf_url           = $upload_dir['baseurl'] . '/' . $subdir . $pdf_file;
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

		return implode( '; ', $notes );
	},
];

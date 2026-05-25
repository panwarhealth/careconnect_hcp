<?php
/**
 * Create CAPH0109 and CAPH0110 FESS resource posts.
 * Files must exist in wp-content/uploads/2026/05/ before running:
 *   caph0109-fess-patient-leaflet.pdf + caph0109-fess-patient-leaflet-thumb.jpg
 *   caph0110-fess-paediatric-sinus-chart.pdf + caph0110-fess-paediatric-sinus-chart-thumb.jpg
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0109 Nasal Saline Patient Leaflet and CAPH0110 Paediatric Nasal Congestion Chart resources (FESS).',

	'up' => function (): string {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload_dir = wp_upload_dir();
		$base       = $upload_dir['basedir'] . '/2026/05/';
		$base_url   = $upload_dir['baseurl'] . '/2026/05/';
		$notes      = [];

		$resources = [
			[
				'slug'         => 'nasal-saline-patient-leaflet',
				'title'        => 'Nasal Saline Patient Leaflet',
				'pdf_file'     => 'caph0109-fess-patient-leaflet.pdf',
				'thumb_file'   => 'caph0109-fess-patient-leaflet-thumb.jpg',
				'therapy_area' => [ 10 ],       // Nasal & Sinus Health
				'audience'     => [ 33 ],       // Patient
				'brand'        => [ 22 ],       // FESS
			],
			[
				'slug'         => 'paediatric-nasal-congestion-chart',
				'title'        => 'Paediatric Nasal Congestion Chart',
				'pdf_file'     => 'caph0110-fess-paediatric-sinus-chart.pdf',
				'thumb_file'   => 'caph0110-fess-paediatric-sinus-chart-thumb.jpg',
				'therapy_area' => [ 10, 7 ],    // Nasal & Sinus Health + Paediatric Health
				'audience'     => [ 33 ],       // Patient
				'brand'        => [ 22 ],       // FESS
			],
		];

		foreach ( $resources as $r ) {
			$existing = get_page_by_path( $r['slug'], OBJECT, 'resources' );
			if ( $existing ) {
				$notes[] = "'{$r['slug']}' already exists (ID {$existing->ID}) — skipped";
				continue;
			}

			// --- PDF attachment ---
			$pdf_path = $base . $r['pdf_file'];
			$pdf_id   = wp_insert_attachment(
				[
					'post_title'     => $r['title'],
					'post_mime_type' => 'application/pdf',
					'post_status'    => 'inherit',
				],
				$pdf_path
			);
			update_post_meta( $pdf_id, '_wp_attached_file', '2026/05/' . $r['pdf_file'] );

			// --- Thumbnail attachment ---
			$thumb_path = $base . $r['thumb_file'];
			$thumb_id   = wp_insert_attachment(
				[
					'post_title'     => $r['title'] . ' Thumbnail',
					'post_mime_type' => 'image/jpeg',
					'post_status'    => 'inherit',
				],
				$thumb_path
			);
			update_post_meta( $thumb_id, '_wp_attached_file', '2026/05/' . $r['thumb_file'] );
			$metadata = wp_generate_attachment_metadata( $thumb_id, $thumb_path );
			wp_update_attachment_metadata( $thumb_id, $metadata );

			// --- Resource post ---
			$post_id = wp_insert_post(
				[
					'post_title'  => $r['title'],
					'post_name'   => $r['slug'],
					'post_status' => 'publish',
					'post_type'   => 'resources',
					'post_author' => 1,
				],
				true
			);

			if ( is_wp_error( $post_id ) ) {
				throw new \RuntimeException( "wp_insert_post failed for {$r['slug']}: " . $post_id->get_error_message() );
			}

			// ACF download field + thumbnail
			update_post_meta( $post_id, 'download', $pdf_id );
			update_post_meta( $post_id, '_download', 'field_6125c6a030fde' );
			update_post_meta( $post_id, '_thumbnail_id', $thumb_id );

			// Taxonomy terms
			wp_set_object_terms( $post_id, $r['therapy_area'], 'therapy_area' );
			wp_set_object_terms( $post_id, $r['audience'], 'audience' );
			wp_set_object_terms( $post_id, $r['brand'], 'brand' );

			$notes[] = "Created '{$r['slug']}' (post ID $post_id, pdf attachment $pdf_id, thumb $thumb_id)";
		}

		return implode( "\n", $notes );
	},
];

<?php
/**
 * Create the PDF redirect page for CAPH0126 Hydralyte Diabetes eDM.
 *
 * Second CTA of the eDM (first CTA reuses the existing /hydralyte-sick-days/
 * redirect page from 2026-05-25-hydralyte-redirect-page.php).
 *
 * Slug to use in the eDM: /hydralyte-patient-leaflet/
 *
 * Idempotent: re-running updates the redirect URL if the page exists.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0126 Hydralyte patient leaflet PDF redirect page for eDM tracking.',

	'up' => function (): string {
		$title = 'Hydralyte Patient Leaflet';
		$slug  = 'hydralyte-patient-leaflet';
		$pdf   = 'https://hcp.carepharma.com.au/wp-content/uploads/2025/11/CAPH0051_Hydralyte-Patient-Leaflet_A4_2pp_V3.0_HR.pdf';

		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		if ( $existing ) {
			update_post_meta( $existing->ID, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $existing->ID, '_pdf_redirect_url', $pdf );
			return "Updated existing page '{$slug}' (ID {$existing->ID}).";
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( "wp_insert_post failed for '{$slug}': " . $post_id->get_error_message() );
		}

		update_post_meta( $post_id, '_wp_page_template', 'template-pdf-redirect.php' );
		update_post_meta( $post_id, '_pdf_redirect_url', $pdf );

		return "Created '{$slug}' (ID {$post_id}).";
	},
];

<?php
/**
 * Create the Hydralyte Sick Days 2026 redirect page (CAPH0089).
 * Uses the PDF Redirect template; target URL stored in _pdf_redirect_url postmeta.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create Hydralyte Sick Days 2026 tracked redirect page (CAPH0089).',

	'up' => function (): string {
		$slug    = 'hydralyte-sick-days';
		$pdf_url = 'https://hcp.carepharma.com.au/wp-content/uploads/2026/03/CAPH0089-Hydralyte-APP-2026_Sick-Days-LB_2pp-A4_V3.2.pdf';

		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			update_post_meta( $existing->ID, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $existing->ID, '_pdf_redirect_url', $pdf_url );
			return "Page '$slug' already exists (ID {$existing->ID}) — template and redirect URL updated.";
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => 'Hydralyte Sick Days 2026',
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( 'wp_insert_post failed: ' . $post_id->get_error_message() );
		}

		update_post_meta( $post_id, '_wp_page_template', 'template-pdf-redirect.php' );
		update_post_meta( $post_id, '_pdf_redirect_url', $pdf_url );

		return "Created page '$slug' (ID $post_id) with PDF redirect template.";
	},
];

<?php
/**
 * PDF redirect page at /zaditen-detailer/.
 *
 * The Zaditen banner image in the FESS allergy eDMs (CAPH0122 MT + TMR,
 * CAPH0134 AJP) links to /zaditen-detailer/, which never existed. The
 * Detailer was retired in favour of the Allergic Conjunctivitis Treatments
 * Chart, so the page sends those clicks to the chart PDF, the same file the
 * eDMs' Download button serves via /allergic-conjunctivitis-chart/.
 *
 * Idempotent: an existing page is re-pointed rather than duplicated.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create /zaditen-detailer/ PDF redirect page pointing at the Allergic Conjunctivitis Treatments Chart.',

	'up' => function (): string {
		$slug    = 'zaditen-detailer';
		$pdf_url = wp_upload_dir()['baseurl'] . '/2026/08/caph0102-zaditen-ac-treatments-chart.pdf';

		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		if ( $existing ) {
			update_post_meta( $existing->ID, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $existing->ID, '_pdf_redirect_url', $pdf_url );
			return "Updated redirect page '{$slug}' (ID {$existing->ID})";
		}

		$page_id = wp_insert_post(
			[
				'post_title'   => 'Zaditen Detailer',
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			],
			true
		);

		if ( is_wp_error( $page_id ) ) {
			throw new \RuntimeException( "wp_insert_post failed for '{$slug}': " . $page_id->get_error_message() );
		}

		update_post_meta( $page_id, '_wp_page_template', 'template-pdf-redirect.php' );
		update_post_meta( $page_id, '_pdf_redirect_url', $pdf_url );

		return "Created redirect page '{$slug}' (ID {$page_id})";
	},
];

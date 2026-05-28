<?php
/**
 * Create PDF redirect pages for CAPH0108 FESS/URTI Solus eDM.
 *
 * Three PDFs linked from the eDM, each gets a clean slug so GA4
 * can capture the tagged visit before the redirect fires.
 *
 * Slugs to use in the eDM:
 *   /fess-paediatric-sinus-chart/
 *   /fess-patient-leaflet/
 *   /fess-gp-detailer/
 *
 * Idempotent: re-running updates the redirect URL if the page exists.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0108 FESS/URTI PDF redirect pages for eDM tracking.',

	'up' => function (): string {
		$pages = [
			[
				'title' => 'FESS Paediatric Sinus Chart',
				'slug'  => 'fess-paediatric-sinus-chart',
				'pdf'   => 'https://hcp.carepharma.com.au/wp-content/uploads/2026/05/caph0110-fess-paediatric-sinus-chart.pdf',
			],
			[
				'title' => 'FESS Patient Leaflet',
				'slug'  => 'fess-patient-leaflet',
				'pdf'   => 'https://hcp.carepharma.com.au/wp-content/uploads/2026/05/caph0109-fess-patient-leaflet.pdf',
			],
			[
				'title' => 'FESS GP Detailer',
				'slug'  => 'fess-gp-detailer',
				'pdf'   => 'https://hcp.carepharma.com.au/wp-content/uploads/2026/05/CAPH0071-FESS-GP-Detailer_4pp-A4_V4.0_HR.pdf',
			],
		];

		$results = [];

		foreach ( $pages as $page ) {
			$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );

			if ( $existing ) {
				update_post_meta( $existing->ID, '_wp_page_template', 'template-pdf-redirect.php' );
				update_post_meta( $existing->ID, '_pdf_redirect_url', $page['pdf'] );
				$results[] = "Updated existing page '{$page['slug']}' (ID {$existing->ID}).";
				continue;
			}

			$post_id = wp_insert_post(
				[
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
				],
				true
			);

			if ( is_wp_error( $post_id ) ) {
				throw new \RuntimeException( "wp_insert_post failed for '{$page['slug']}': " . $post_id->get_error_message() );
			}

			update_post_meta( $post_id, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $post_id, '_pdf_redirect_url', $page['pdf'] );
			$results[] = "Created '{$page['slug']}' (ID {$post_id}).";
		}

		return implode( ' ', $results );
	},
];

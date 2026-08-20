<?php
/**
 * CAPH0123 — eDM redirect page for the National Asthma Council
 * Allergic Rhinitis Treatments Chart.
 *
 * The resource post and 2025 PDF already exist; this adds only the
 * tracked redirect page at /allergic-rhinitis-chart/ so UTM-tagged
 * eDM clicks register in GA4 before bouncing to the file. The PDF URL
 * is resolved from the resource's download field, not hardcoded.
 *
 * Idempotent: refreshes the template and redirect URL if the page exists.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create /allergic-rhinitis-chart/ eDM redirect page for the NAC Allergic Rhinitis Treatments Chart.',

	'up' => function (): string {
		$resource_slug  = 'national-asthma-council-allergic-rhinitis-treatment-chart';
		$redirect_slug  = 'allergic-rhinitis-chart';
		$redirect_title = 'Allergic Rhinitis Chart';

		$resource = get_page_by_path( $resource_slug, OBJECT, 'resources' );
		if ( ! $resource ) {
			throw new \RuntimeException( "Resource '{$resource_slug}' not found." );
		}

		$pdf_id  = (int) get_post_meta( $resource->ID, 'download', true );
		$pdf_url = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
		if ( ! $pdf_url ) {
			throw new \RuntimeException( "Resource {$resource->ID} has no resolvable download attachment." );
		}

		$existing = get_page_by_path( $redirect_slug, OBJECT, 'page' );

		if ( $existing ) {
			update_post_meta( $existing->ID, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $existing->ID, '_pdf_redirect_url', $pdf_url );
			return "Updated redirect page '{$redirect_slug}' (ID {$existing->ID}) -> {$pdf_url}";
		}

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

		return "Created redirect page '{$redirect_slug}' (ID {$redirect_id}) -> {$pdf_url}";
	},
];

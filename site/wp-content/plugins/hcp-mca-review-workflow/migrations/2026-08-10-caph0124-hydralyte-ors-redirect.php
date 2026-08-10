<?php
/**
 * PDF redirect page for CAPH0124 Hydralyte, so UTM-tagged CTAs register in
 * GA4 before bouncing to the file.
 *
 * Slug to use in campaigns: /oral-rehydration-in-diabetes/
 *
 * Idempotent: re-running refreshes the redirect URL.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0124 Hydralyte oral-rehydration-in-diabetes PDF redirect page for campaign tracking.',

	'up' => function (): string {
		$slug  = 'oral-rehydration-in-diabetes';
		$title = 'Oral Rehydration in Diabetes';

		$upload_dir = wp_upload_dir();
		$pdf_rel    = '2026/08/caph0124-hydralyte-ors-diabetes.pdf';

		if ( ! file_exists( $upload_dir['basedir'] . '/' . $pdf_rel ) ) {
			throw new \RuntimeException( "Missing upload: {$pdf_rel}" );
		}

		$pdf_url  = $upload_dir['baseurl'] . '/' . $pdf_rel;
		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		if ( $existing ) {
			update_post_meta( $existing->ID, '_wp_page_template', 'template-pdf-redirect.php' );
			update_post_meta( $existing->ID, '_pdf_redirect_url', $pdf_url );
			return "Updated redirect page '{$slug}' (ID {$existing->ID}).";
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
		update_post_meta( $post_id, '_pdf_redirect_url', $pdf_url );

		return "Created redirect page '{$slug}' (ID {$post_id}).";
	},
];

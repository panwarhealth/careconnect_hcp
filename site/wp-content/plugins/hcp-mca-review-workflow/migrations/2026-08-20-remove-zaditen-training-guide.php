<?php
/**
 * Remove the Zaditen Training Guide from the site entirely.
 *
 * - Resource post /blog/resources/zaditen-training-guide/ deleted.
 * - Its PDF + thumbnail attachments (uploads/2025/05/) deleted with files.
 * - Orphaned 2022/12 copies of the same files (no attachment rows) unlinked.
 * - The "See more" CTA in the eye-care article, which pointed at the 2022
 *   PDF copy, retargeted to the Zaditen for Allergies page so the product
 *   tile keeps a live destination.
 *
 * Idempotent: every step no-ops once applied.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Remove Zaditen Training Guide: resource, attachments, orphaned files; retarget article CTA.',

	'up' => function (): string {
		$notes = [];

		// --- Article CTA: point "See more" at the Zaditen page, not the PDF ---
		$article = get_page_by_path( 'eye-care-for-children-with-conjunctivitis-a-guide-to-educating-parents', OBJECT, 'post' );
		$zaditen = get_page_by_path( 'allergy-analyser/zaditen-for-allergies', OBJECT, 'page' );

		if ( $article && $zaditen ) {
			$new = preg_replace(
				'~href="[^"]*012607[^"]*"~',
				'href="' . esc_url( get_permalink( $zaditen ) ) . '"',
				$article->post_content,
				-1,
				$hits
			);
			if ( $hits > 0 ) {
				kses_remove_filters();
				wp_update_post( [ 'ID' => $article->ID, 'post_content' => $new ] );
				kses_init_filters();
				$notes[] = "article CTA retargeted ({$hits} link)";
			} else {
				$notes[] = 'article CTA already clean';
			}
		} else {
			$notes[] = 'article or zaditen page not found — CTA step skipped';
		}

		// --- Resource post ---
		$resource = get_page_by_path( 'zaditen-training-guide', OBJECT, 'resources' );
		if ( $resource ) {
			wp_delete_post( $resource->ID, true );
			$notes[] = "resource {$resource->ID} deleted";
		} else {
			$notes[] = 'resource already gone';
		}

		// --- Attachments (deletes the 2025/05 files and generated sizes) ---
		global $wpdb;
		$files = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wp_attached_file'
			   AND (meta_value LIKE '2025/05/012607-Care-Zaditen-Training-Guide%'
			     OR meta_value LIKE '2025/05/Zaditen-TG-front%')"
		);
		foreach ( $files as $att_id ) {
			wp_delete_attachment( (int) $att_id, true );
			$notes[] = "attachment {$att_id} deleted";
		}
		if ( ! $files ) {
			$notes[] = 'attachments already gone';
		}

		// --- Orphaned 2022/12 copies (files on disk, no attachment rows) ---
		$base    = wp_upload_dir()['basedir'] . '/2022/12/';
		$orphans = array_merge(
			[ $base . '012607-Care-Zaditen-Training-Guide-SINGLE-LAYOUT.pdf' ],
			glob( $base . 'Zaditen-TG-front*.jpg' ) ?: []
		);
		$removed = 0;
		foreach ( $orphans as $f ) {
			if ( file_exists( $f ) && unlink( $f ) ) {
				$removed++;
			}
		}
		$notes[] = "orphaned 2022/12 files removed: {$removed}";

		return implode( '; ', $notes );
	},
];

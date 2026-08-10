<?php
/**
 * Rename the series landing page: /clinical-bites/ -> /diabetes-clinical-bites/.
 *
 * Client request (2026-08-11): the URL itself should distinguish this series
 * from the Allergic Rhinitis one to follow. The printed QR code is being
 * regenerated against the new URL, which is what unfreezes the old slug.
 *
 * The old URL keeps a permanent redirect (includes/redirects.php): it has been
 * in review links and draft campaign copy, and a stray printed copy of the old
 * QR must land on the page, not a 404.
 *
 * Also repoints the series' `_series_landing` term meta, which the episode
 * pages' "Video N of M" links read.
 *
 * Idempotent: no-ops once the page carries the new slug.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Rename the Clinical Bites landing page to /diabetes-clinical-bites/ and repoint series links.',
	'up'          => function () {
		$new_slug = 'diabetes-clinical-bites';

		$page = get_page_by_path( $new_slug, OBJECT, 'page' );
		if ( ! $page ) {
			$page = get_page_by_path( 'clinical-bites', OBJECT, 'page' );
			if ( ! $page ) {
				return 'Landing page not found under either slug — nothing to rename.';
			}
			wp_update_post( array( 'ID' => $page->ID, 'post_name' => $new_slug ) );
		}

		$note = "Landing page {$page->ID} is /{$new_slug}/.";

		$term = get_term_by( 'slug', 'clinical-bites-diabetes', 'video_topic' );
		if ( $term ) {
			update_term_meta( $term->term_id, '_series_landing', get_permalink( $page->ID ) );
			$note .= ' Series landing link repointed.';
		}

		return $note;
	},
);

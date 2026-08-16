<?php
/**
 * Roll back the landing-page rename: /diabetes-clinical-bites/ -> /clinical-bites/.
 *
 * The pamphlet QR had already gone to print against /clinical-bites/ when the
 * rename (2026-08-11-rename-clinical-bites-slug.php) was requested, so the
 * original URL is canonical again — permanently. includes/redirects.php now
 * 301s the short-lived new slug back here.
 *
 * Also repoints the series' `_series_landing` term meta, which the episode
 * pages' "Video N of M" links read.
 *
 * Idempotent: no-ops once the page carries the original slug.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Restore the Clinical Bites landing page to /clinical-bites/ and repoint series links.',
	'up'          => function () {
		$slug = 'clinical-bites';

		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $page ) {
			$page = get_page_by_path( 'diabetes-clinical-bites', OBJECT, 'page' );
			if ( ! $page ) {
				return 'Landing page not found under either slug — nothing to restore.';
			}
			wp_update_post( array( 'ID' => $page->ID, 'post_name' => $slug ) );
			// The rename left this behind; with the original slug back on the
			// post itself, a stale old-slug entry would shadow future lookups.
			delete_post_meta( $page->ID, '_wp_old_slug', $slug );
		}

		$note = "Landing page {$page->ID} is /{$slug}/.";

		$term = get_term_by( 'slug', 'clinical-bites-diabetes', 'video_topic' );
		if ( $term ) {
			update_term_meta( $term->term_id, '_series_landing', get_permalink( $page->ID ) );
			$note .= ' Series landing link repointed.';
		}

		return $note;
	},
);

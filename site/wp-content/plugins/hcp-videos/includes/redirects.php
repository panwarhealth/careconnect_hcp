<?php
/**
 * Permanent redirects for renamed public URLs.
 *
 * /clinical-bites/ was the series landing page until 2026-08-11 and had been
 * circulated in review links and campaign drafts before the rename to
 * /diabetes-clinical-bites/. WordPress's own old-slug redirect covers most
 * cases; this explicit one guarantees it, because a printed QR code cannot be
 * corrected after the fact.
 *
 * Fragments (#clinicalbitesresources) survive: browsers carry the fragment
 * across a 301 unless the target names its own.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', 'hcp_videos_legacy_redirects', 1 );
function hcp_videos_legacy_redirects(): void {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

	if ( 'clinical-bites' !== $path ) {
		return;
	}

	// Only take over when the old URL no longer resolves to real content, so
	// this is inert anywhere the rename migration has not run yet.
	if ( ! is_404() ) {
		return;
	}

	wp_safe_redirect( home_url( '/diabetes-clinical-bites/' ), 301 );
	exit;
}

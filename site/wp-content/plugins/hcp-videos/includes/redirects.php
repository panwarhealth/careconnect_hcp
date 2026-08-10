<?php
/**
 * Permanent redirects for renamed public URLs.
 *
 * The series landing page is /clinical-bites/ — the pamphlet QR went to print
 * against that URL, which freezes it for good. It was briefly renamed to
 * /diabetes-clinical-bites/ on 2026-08-11 and rolled back the same week; that
 * slug may survive in review links and a Vimeo end screen, so it redirects
 * home here.
 *
 * Fragments (#clinicalbitesresources) survive: browsers carry the fragment
 * across a 301 unless the target names its own.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', 'hcp_videos_legacy_redirects', 1 );
function hcp_videos_legacy_redirects(): void {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

	if ( 'diabetes-clinical-bites' !== $path ) {
		return;
	}

	// Only take over when that URL no longer resolves to real content, so this
	// is inert anywhere the restore migration has not run yet.
	if ( ! is_404() ) {
		return;
	}

	wp_safe_redirect( home_url( '/clinical-bites/' ), 301 );
	exit;
}

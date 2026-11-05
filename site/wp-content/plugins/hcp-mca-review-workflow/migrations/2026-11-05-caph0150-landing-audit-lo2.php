<?php
/**
 * CAPH0150: second audit learning outcome on the logged-out landing page (108753)
 * describes the 2026 case studies instead of three real patients.
 *
 * Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: landing page audit learning outcome 2 matches the 2026 audit',
	'up'          => function (): string {
		global $wpdb;

		$old = 'Identify three adult patients who present with risk of anal fissure and record the outcomes of screening, diagnosis, and management against best practice';
		$new = 'Guide the screening, diagnosis, and management of three hypothetical patients who present with possible anal fissure according to best practice';

		$page = get_post( 108753 );
		if ( ! $page ) {
			throw new RuntimeException( 'Landing page 108753 not found.' );
		}
		if ( false !== strpos( $page->post_content, $new ) ) {
			return 'Already updated.';
		}
		if ( 1 !== substr_count( $page->post_content, $old ) ) {
			throw new RuntimeException( 'Old learning outcome not found exactly once on 108753.' );
		}

		$wpdb->update( $wpdb->posts, [ 'post_content' => str_replace( $old, $new, $page->post_content ) ], [ 'ID' => 108753 ] );
		clean_post_cache( 108753 );

		return 'Learning outcome 2 updated on 108753.';
	},
];

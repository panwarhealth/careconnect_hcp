<?php
/**
 * CAPH0150: the completion email for the new audit names it as
 * "Clinical Audit" rather than the legacy "Mini Clinical Audit". Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: completion email body says "Clinical Audit component".',

	'up' => function (): string {
		$post = get_page_by_path( 'clinical-audit-2026-completion-email', OBJECT, 'ld-notification' );
		if ( ! $post ) {
			throw new \RuntimeException( 'completion email notification not found' );
		}
		$old = 'completing the Mini Clinical Audit component of this CPD activity';
		$new = 'completing the Clinical Audit component of this CPD activity';
		if ( false !== strpos( $post->post_content, $new ) ) {
			return 'already updated';
		}
		if ( 1 !== substr_count( $post->post_content, $old ) ) {
			throw new \RuntimeException( 'expected sentence not found exactly once' );
		}
		kses_remove_filters();
		wp_update_post( [ 'ID' => $post->ID, 'post_content' => str_replace( $old, $new, $post->post_content ) ] );
		kses_init_filters();
		return 'completion email body updated';
	},
];

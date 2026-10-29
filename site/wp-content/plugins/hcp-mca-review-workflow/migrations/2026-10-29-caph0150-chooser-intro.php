<?php
/**
 * CAPH0150: chooser page intro line announces the new audit version.
 * Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: chooser page intro line announces the new streamlined audit.',

	'up' => function (): string {
		$page = get_page_by_path( HCP_MCA_CHOOSER_PAGE_SLUG );
		if ( ! $page ) {
			throw new \RuntimeException( 'chooser page not found' );
		}
		$old = 'The clinical audit has been updated.';
		$new = 'A new, streamlined version of the Mini Clinical Audit is now available to all participants.';
		if ( false !== strpos( $page->post_content, $new ) ) {
			return 'already updated';
		}
		if ( false === strpos( $page->post_content, $old ) ) {
			throw new \RuntimeException( 'chooser intro line not found' );
		}
		kses_remove_filters();
		wp_update_post( [
			'ID'           => $page->ID,
			'post_content' => wp_slash( str_replace( $old, $new, $page->post_content ) ),
		] );
		return 'chooser intro updated';
	},
];

<?php
/**
 * Allergic Rhinitis Treatments Charts landing page: NEW sash text a touch
 * smaller (1.5rem -> 1.375rem). Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'AR chart landing page: NEW sash text slightly smaller.',

	'up' => function (): string {
		$page = get_page_by_path( 'allergic-rhinitis-chart', OBJECT, 'page' );
		if ( ! $page ) {
			throw new \RuntimeException( 'allergic-rhinitis-chart page not found' );
		}
		$content = $page->post_content;
		if ( false !== strpos( $content, 'background:#fffd55;color:#002a48;font-weight:600;font-size:1.375rem;' ) ) {
			return 'already 1.375rem';
		}
		$new = str_replace(
			'background:#fffd55;color:#002a48;font-weight:600;font-size:1.5rem;',
			'background:#fffd55;color:#002a48;font-weight:600;font-size:1.375rem;',
			$content,
			$hits
		);
		if ( ! $hits ) {
			throw new \RuntimeException( 'sash not found; run the ribbon-yellow migration first' );
		}
		kses_remove_filters();
		wp_update_post( [ 'ID' => $page->ID, 'post_content' => $new ] );
		kses_init_filters();
		return 'sash text 1.375rem';
	},
];

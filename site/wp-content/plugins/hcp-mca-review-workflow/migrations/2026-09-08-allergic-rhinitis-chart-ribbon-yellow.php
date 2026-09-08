<?php
/**
 * Allergic Rhinitis Treatments Charts landing page: reviewer round two.
 * The NEW ribbon on the 2026 chart becomes thicker, golden yellow with navy
 * text, to stand out against the teal thumbnail. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'AR chart landing page: thicker yellow NEW ribbon with navy text on the 2026 chart card.',

	'up' => function (): string {
		$page = get_page_by_path( 'allergic-rhinitis-chart', OBJECT, 'page' );
		if ( ! $page ) {
			throw new \RuntimeException( 'allergic-rhinitis-chart page not found' );
		}

		$style = 'position:absolute;top:2rem;right:-2.75rem;width:11rem;transform:rotate(45deg);'
			. 'background:#F5C542;color:#002a48;font-weight:800;font-size:0.9375rem;letter-spacing:0.16em;'
			. 'line-height:1;text-align:center;padding:0.8rem 0;box-shadow:0 2px 6px rgba(0,0,0,0.3);';

		$content = $page->post_content;
		if ( false !== strpos( $content, 'background:#F5C542' ) && false !== strpos( $content, 'transform:rotate(45deg)' ) ) {
			return 'ribbon already yellow';
		}

		$new = preg_replace(
			'~(<span class="hcp-new-tag hcp-new-ribbon" style=")[^"]*(">NEW</span>)~',
			'$1' . $style . '$2',
			$content,
			1,
			$hits
		);
		if ( ! $hits ) {
			throw new \RuntimeException( 'NEW ribbon not found on the page; run the amends migration first' );
		}

		// The ribbon relies on transform, which WordPress strips from inline
		// styles unless the save is unfiltered.
		kses_remove_filters();
		wp_update_post( [ 'ID' => $page->ID, 'post_content' => $new ] );
		kses_init_filters();
		return 'ribbon restyled';
	},
];

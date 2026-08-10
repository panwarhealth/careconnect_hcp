<?php
/**
 * Make the Tools & Videos jump nav scroll sideways instead of wrapping.
 *
 * "Diabetes Clinical Bites" broke mid-label on phones, and the Allergic
 * Rhinitis series will add an even longer link. The nav becomes a flex row
 * (`hcp-jump-nav`, styled in includes/helpers.php): labels never break, and
 * when the row overflows it scrolls horizontally — the same pattern as any
 * category chip bar. Desktop is unaffected because everything fits.
 *
 * `space-x-lg` comes off because it spaces with margins, which double up
 * against the flex gap.
 *
 * Idempotent: the replacement no-ops once applied.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Tools & Videos jump nav: horizontal scroll on mobile instead of mid-label wrapping.',
	'up'          => function () {
		$page = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $page ) {
			return "Page 'tools-and-videos' not found.";
		}

		$from = '<nav class="border-b space-x-lg text-paragraph border-stroke">';
		$to   = '<nav class="hcp-jump-nav border-b text-paragraph border-stroke">';

		if ( false === strpos( $page->post_content, $from ) ) {
			return false !== strpos( $page->post_content, 'hcp-jump-nav' )
				? 'Jump nav already converted.'
				: 'Nav markup not found — page differs from expected, nothing changed.';
		}

		wp_update_post( array(
			'ID'           => $page->ID,
			'post_content' => str_replace( $from, $to, $page->post_content ),
		) );

		return 'Jump nav converted to scrollable row.';
	},
);

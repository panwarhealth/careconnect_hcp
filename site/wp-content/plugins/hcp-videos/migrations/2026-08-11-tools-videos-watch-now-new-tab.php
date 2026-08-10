<?php
/**
 * Open the Tools & Videos hero "Watch now" in a new tab.
 *
 * The review round asked for every Watch button to open in a new tab so the
 * listing page is never navigated away from. The episode cards and the landing
 * page hero already comply; this converts the one straggler, the hub's
 * Clinical Bites hero link, which still carried target="_self".
 *
 * Idempotent: no-ops once converted.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Tools & Videos: hero "Watch now" opens in a new tab.',
	'up'          => function () {
		$page = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $page ) {
			return "Page 'tools-and-videos' not found.";
		}

		$from = 'href="/video/why-sick-day-planning-is-important-in-diabetes/" target="_self"';
		$to   = 'href="/video/why-sick-day-planning-is-important-in-diabetes/" target="_blank"';

		if ( false === strpos( $page->post_content, $from ) ) {
			return false !== strpos( $page->post_content, $to )
				? 'Already opens in a new tab.'
				: 'Link not found — page differs from expected, nothing changed.';
		}

		wp_update_post( array(
			'ID'           => $page->ID,
			'post_content' => str_replace( $from, $to, $page->post_content ),
		) );

		return 'Hero "Watch now" now opens in a new tab.';
	},
);

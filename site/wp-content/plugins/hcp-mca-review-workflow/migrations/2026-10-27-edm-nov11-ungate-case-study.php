<?php
/**
 * Ungate window for the 11 Nov 2026 TMR eDM: the CAPH0148 travelling with
 * diabetes case study opens to logged-out visitors for 48 hours
 * (11 Nov 09:00 to 13 Nov 09:00 AEDT).
 *
 * The window expires on its own; no clean-up migration is needed.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 11 Nov: ungate the travelling with diabetes case study for 48 hours.',
	'up'          => function () {
		$from  = 1794348000; // 2026-11-11 09:00 AEDT
		$until = 1794520800; // 2026-11-13 09:00 AEDT

		$slug = 'travelling-with-diabetes';
		$post = get_page_by_path( $slug, OBJECT, 'post' );
		if ( ! $post ) {
			throw new RuntimeException( "Case study '$slug' not found — window not set." );
		}
		update_post_meta( $post->ID, '_ungated_from', $from );
		update_post_meta( $post->ID, '_ungated_until', $until );

		return "Ungated 11-13 Nov: $slug ({$post->ID}).";
	},
);

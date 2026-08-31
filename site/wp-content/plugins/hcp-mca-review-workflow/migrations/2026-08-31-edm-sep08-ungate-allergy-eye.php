<?php
/**
 * Ungate window for the InSight solus eDM: "Can you spot the allergy eye?"
 * open to logged-out visitors 8-9 Sep 2026 AEST.
 *
 * Body sections are gated with `logged_in_users_only`, which the window
 * mechanism strips for the duration. The three `[restrict]` CTAs in the
 * article (Download Detailer, Access Analyser, Order Samples) are a separate
 * RCP shortcode gate and stay closed to logged-out visitors, matching the
 * 20 and 26 Aug article ungates.
 *
 * Windows expire on their own; no clean-up migration is needed.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 8 Sep: ungate the "Can you spot the allergy eye?" article for 2 days.',
	'up'          => function () {
		$from  = 1788789600; // 2026-09-08 00:00 AEST
		$until = 1788962400; // 2026-09-10 00:00 AEST

		$slug = 'can-you-spot-the-allergy-eye';
		$post = get_page_by_path( $slug, OBJECT, 'post' );
		if ( ! $post ) {
			throw new RuntimeException( "Article '$slug' not found — window not set." );
		}

		update_post_meta( $post->ID, '_ungated_from', $from );
		update_post_meta( $post->ID, '_ungated_until', $until );

		return "Ungated 8-9 Sep: $slug ({$post->ID}).";
	},
);

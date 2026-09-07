<?php
/**
 * Ungate windows for the Medical Republic (16 Sep) and Medicine Today (17 Sep)
 * eDMs. The two sends overlap, so each item gets one merged window (AEST):
 *
 *   /allergic-rhinitis-clinical-bites/ + its three episode videos
 *     — 16 Sep 00:00 to 22 Sep 00:00, via the
 *       `clinical-bites-allergic-rhinitis` video_topic term. The landing page
 *       opts in with `_ungate_series` (re-set here so the migration stands alone).
 *
 *   /tools-and-videos/ — 16 Sep 00:00 to 22 Sep 00:00, explicit per-page
 *     window. The hub's own dates win over `_ungate_series`, so it must be
 *     set per campaign.
 *
 *   /allergy-analyser/ — 16 Sep 00:00 to 19 Sep 00:00, per-page window.
 *     Gated by Restrict Content Pro; relies on the `rcp_member_can_access`
 *     bridge in the child theme's inc/ungate.php.
 *
 * Windows expire on their own; no clean-up migration is needed.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 16/17 Sep: ungate AR Clinical Bites series and Tools and Videos hub 16-21 Sep, Allergy Analyser 16-18 Sep.',
	'up'          => function () {
		$from         = 1789480800; // 2026-09-16 00:00 AEST
		$until_series = 1789999200; // 2026-09-22 00:00 AEST
		$until_tool   = 1789740000; // 2026-09-19 00:00 AEST

		$term = get_term_by( 'slug', 'clinical-bites-allergic-rhinitis', 'video_topic' );
		if ( ! $term ) {
			throw new RuntimeException( 'video_topic term clinical-bites-allergic-rhinitis not found — window not set.' );
		}
		update_term_meta( $term->term_id, '_ungated_from', $from );
		update_term_meta( $term->term_id, '_ungated_until', $until_series );

		$landing = get_page_by_path( 'allergic-rhinitis-clinical-bites', OBJECT, 'page' );
		if ( ! $landing ) {
			throw new RuntimeException( 'Page allergic-rhinitis-clinical-bites not found — series opt-in not set.' );
		}
		update_post_meta( $landing->ID, '_ungate_series', $term->slug );

		$hub = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $hub ) {
			throw new RuntimeException( 'Page tools-and-videos not found — window not set.' );
		}
		update_post_meta( $hub->ID, '_ungated_from', $from );
		update_post_meta( $hub->ID, '_ungated_until', $until_series );

		$analyser = get_page_by_path( 'allergy-analyser', OBJECT, 'page' );
		if ( ! $analyser ) {
			throw new RuntimeException( 'Page allergy-analyser not found — window not set.' );
		}
		update_post_meta( $analyser->ID, '_ungated_from', $from );
		update_post_meta( $analyser->ID, '_ungated_until', $until_tool );

		return "Ungated 16-21 Sep: AR Clinical Bites series (term {$term->term_id}, landing {$landing->ID}), tools-and-videos ({$hub->ID}); 16-18 Sep: allergy-analyser ({$analyser->ID}).";
	},
);

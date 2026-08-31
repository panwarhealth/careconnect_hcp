<?php
/**
 * Ungate windows for the 2 Sep 2026 eDM (AEST):
 *
 *   /allergic-rhinitis-clinical-bites/ + its three episode videos
 *     — 2 Sep 00:00 to 7 Sep 00:00 (5 days), via a window on the
 *       `clinical-bites-allergic-rhinitis` video_topic term. The landing
 *       page opts in with `_ungate_series` (not previously set).
 *
 *   /allergy-analyser/ — 2 Sep 00:00 to 4 Sep 00:00 (2 days), per-post window.
 *     This page is gated by Restrict Content Pro, not by `logged_in_users_only`,
 *     so the window only takes effect with the `rcp_member_can_access` bridge in
 *     the child theme's inc/ungate.php. Without that file deployed the meta is
 *     inert and logged-out visitors keep getting redirected to /register/.
 *
 * Windows expire on their own; no clean-up migration is needed.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 2 Sep: ungate AR Clinical Bites series for 5 days and Allergy Analyser for 2 days.',
	'up'          => function () {
		$from = 1788271200; // 2026-09-02 00:00 AEST

		$term = get_term_by( 'slug', 'clinical-bites-allergic-rhinitis', 'video_topic' );
		if ( ! $term ) {
			throw new RuntimeException( 'video_topic term clinical-bites-allergic-rhinitis not found — window not set.' );
		}
		update_term_meta( $term->term_id, '_ungated_from', $from );
		update_term_meta( $term->term_id, '_ungated_until', 1788703200 ); // 2026-09-07 00:00 AEST

		$landing = get_page_by_path( 'allergic-rhinitis-clinical-bites', OBJECT, 'page' );
		if ( ! $landing ) {
			throw new RuntimeException( 'Page allergic-rhinitis-clinical-bites not found — series opt-in not set.' );
		}
		update_post_meta( $landing->ID, '_ungate_series', $term->slug );

		$analyser = get_page_by_path( 'allergy-analyser', OBJECT, 'page' );
		if ( ! $analyser ) {
			throw new RuntimeException( 'Page allergy-analyser not found — window not set.' );
		}
		update_post_meta( $analyser->ID, '_ungated_from', $from );
		update_post_meta( $analyser->ID, '_ungated_until', 1788444000 ); // 2026-09-04 00:00 AEST

		return "Ungated 2-6 Sep: AR Clinical Bites series (term {$term->term_id}, landing {$landing->ID}); 2-3 Sep: allergy-analyser ({$analyser->ID}).";
	},
);

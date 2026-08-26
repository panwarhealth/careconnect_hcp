<?php
/**
 * Ungate window for the 26 Aug 2026 eDM: the Clinical Bites series open to
 * logged-out visitors for seven days (26 Aug - 1 Sep AEST).
 *
 * The window lives on the `clinical-bites-diabetes` video_topic term, so every
 * episode inherits it, as does any page carrying `_ungate_series` for that
 * slug. The Clinical Bites landing page and the Tools & Videos hub are both
 * already linked; the hub link is re-asserted here so the migration stands
 * alone.
 *
 * The window expires on its own; no clean-up migration is needed.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 26 Aug: ungate Clinical Bites series + Tools & Videos hub for 7 days.',
	'up'          => function () {
		$from  = 1787666400; // 2026-08-26 00:00 AEST
		$until = 1788271200; // 2026-09-02 00:00 AEST

		$slug = 'clinical-bites-diabetes';
		$term = get_term_by( 'slug', $slug, 'video_topic' );
		if ( ! $term instanceof WP_Term ) {
			throw new RuntimeException( "video_topic term '$slug' not found — window not set." );
		}
		update_term_meta( $term->term_id, '_ungated_from', $from );
		update_term_meta( $term->term_id, '_ungated_until', $until );

		$hub = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $hub ) {
			throw new RuntimeException( "Page 'tools-and-videos' not found — series window set, hub not linked." );
		}
		update_post_meta( $hub->ID, '_ungate_series', $slug );

		return "Series '$slug' (term {$term->term_id}) ungated 26 Aug-1 Sep; hub page {$hub->ID} linked.";
	},
);

<?php
/**
 * Ungate window for the Tools and Videos hub across the AR Clinical Bites send.
 *
 * The hub carries `_ungate_series = clinical-bites-diabetes`, so it currently
 * inherits the live diabetes window (26 Aug to 2 Sep AEST). Repointing that
 * meta at the allergic rhinitis series would cut the diabetes campaign short,
 * so the hub gets an explicit window instead. A post's own window takes
 * precedence over its series, and this one spans both campaigns end to end:
 * it opens where the diabetes window opened and closes where the AR window
 * closes, leaving no gap between the two sends.
 *
 * The three episode videos need nothing here. They sit in the
 * clinical-bites-allergic-rhinitis term and are already covered by the series
 * window set in 2026-08-31-edm-sep02-ungate-ar-pages.
 *
 * Note for the next campaign: once this window expires the hub's own dates
 * still win over `_ungate_series`, so it will stop inheriting series windows.
 * Future campaigns must set the hub's window explicitly, as this one does.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 2 Sep: explicit ungate window on the Tools and Videos hub, spanning the diabetes and allergic rhinitis sends.',
	'up'          => function () {
		$from  = 1787666400; // 2026-08-26 00:00 AEST, start of the live diabetes window
		$until = 1788703200; // 2026-09-07 00:00 AEST, end of the allergic rhinitis window

		$slug = 'tools-and-videos';
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $page ) {
			throw new RuntimeException( "Page '$slug' not found — window not set." );
		}

		update_post_meta( $page->ID, '_ungated_from', $from );
		update_post_meta( $page->ID, '_ungated_until', $until );

		return "Ungated 26 Aug to 6 Sep: $slug ({$page->ID}).";
	},
);

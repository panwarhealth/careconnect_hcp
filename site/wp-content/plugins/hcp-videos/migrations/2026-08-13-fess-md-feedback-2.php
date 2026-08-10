<?php
/**
 * CAPH0123 MD feedback v2 — widow control on the NAC chart resource title:
 * a non-breaking space binds "Treatments Chart" so "Chart" can't drop alone.
 *
 * The layout items from the same feedback round live in the section/landing
 * migrations and the plugin CSS.
 *
 * Idempotent: no-ops once the title carries the NBSP.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'FESS MD feedback v2: bind "Treatments Chart" in the NAC resource title.',
	'up'          => function () {
		$p = get_page_by_path( 'national-asthma-council-allergic-rhinitis-treatment-chart', OBJECT, 'resources' );
		if ( ! $p ) {
			return 'NAC chart resource not found.';
		}

		$title = "National Asthma Council Allergic Rhinitis Treatments\u{00A0}Chart";
		if ( $p->post_title === $title ) {
			return 'Already applied.';
		}

		wp_update_post( array( 'ID' => $p->ID, 'post_title' => $title ) );

		return 'NAC chart title bound.';
	},
);

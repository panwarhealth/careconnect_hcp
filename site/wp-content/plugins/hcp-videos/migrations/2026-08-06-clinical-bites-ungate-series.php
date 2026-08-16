<?php
/**
 * Link the Clinical Bites landing page to its series for campaign ungating.
 *
 * `_ungate_series` names a `video_topic` slug. The landing page is a page and
 * so carries no taxonomy terms of its own; this meta is how it inherits the
 * window set on the series term, the same window its episodes inherit.
 *
 * Sets no dates. The window itself (`_ungated_from` / `_ungated_until` term
 * meta) is set per campaign when an eDM is ready, and cleared afterwards.
 *
 * See site/wp-content/themes/wp-spinnr-child/inc/ungate.php.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Point the Clinical Bites landing page at the clinical-bites-diabetes series for ungating.',
	'up'          => function () {
		$page = get_page_by_path( 'clinical-bites', OBJECT, 'page' );
		if ( ! $page ) {
			return 'Clinical Bites landing page not found — nothing to link.';
		}

		$slug = 'clinical-bites-diabetes';
		if ( ! get_term_by( 'slug', $slug, 'video_topic' ) ) {
			return "video_topic term '$slug' not found — nothing to link.";
		}

		update_post_meta( $page->ID, '_ungate_series', $slug );

		return "Landing page {$page->ID} linked to series '$slug'.";
	},
);

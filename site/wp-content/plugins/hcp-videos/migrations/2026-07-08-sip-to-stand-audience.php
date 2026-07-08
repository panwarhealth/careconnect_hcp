<?php
/**
 * Tag the "Sip to stand" blog article with the Healthcare Professional audience
 * term, so the video Related-Resources card shows the audience eyebrow (blog
 * posts otherwise carry no audience term and render with no tag).
 *
 * Post + term resolved by slug/name (stable per env). Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Tag "Sip to stand" article as Healthcare Professional (resource-card eyebrow).',
	'up'          => function () {
		$p = get_page_by_path( 'sip-to-stand-why-hydration-is-essential-in-pots', OBJECT, 'post' );
		if ( ! $p ) {
			return 'post not found';
		}
		$term = get_term_by( 'name', 'Healthcare Professional', 'audience' );
		if ( ! $term ) {
			return 'audience term not found';
		}
		if ( has_term( (int) $term->term_id, 'audience', $p->ID ) ) {
			return 'already tagged';
		}
		wp_set_object_terms( $p->ID, array( (int) $term->term_id ), 'audience', false );
		return 'tagged post ' . $p->ID . ' as Healthcare Professional';
	},
);

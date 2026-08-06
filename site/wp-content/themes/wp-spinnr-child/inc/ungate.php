<?php
/**
 * Campaign ungating — temporarily opens gated content to logged-out visitors
 * for a date window (eDM sends, printed QR codes, review links).
 *
 * A window is a `_ungated_from` / `_ungated_until` pair of unix timestamps.
 * It can be set two ways:
 *
 *   Per post   — meta on the post itself. Used by one-off articles.
 *   Per series — meta on a `video_topic` term. Every video in that term
 *                inherits it, and a landing page opts in with `_ungate_series`
 *                naming the term slug. One pair of dates per campaign.
 *
 * A post's own window always wins over its series window.
 *
 * Two consumers:
 *   hcp_ungate_is_open()   — server-side, for templates that withhold markup
 *                            from logged-out visitors (single-video.php).
 *   hcp_output_ungate_data() in functions.php — hands the window to the JS in
 *                            spinnr_custom_body.js, which strips
 *                            `logged_in_users_only` from already-rendered markup.
 */

defined( 'ABSPATH' ) || exit;

const HCP_UNGATE_TAXONOMY = 'video_topic';

/**
 * Resolve the ungate window that applies to a post.
 *
 * @return array{from:int,until:int}|null Null when no window is set.
 */
function hcp_ungate_window( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return null;
	}

	$until = (int) get_post_meta( $post_id, '_ungated_until', true );
	if ( $until > 0 ) {
		return array(
			'from'  => (int) get_post_meta( $post_id, '_ungated_from', true ),
			'until' => $until,
		);
	}

	foreach ( hcp_ungate_series_terms( $post_id ) as $term ) {
		$until = (int) get_term_meta( $term->term_id, '_ungated_until', true );
		if ( $until > 0 ) {
			return array(
				'from'  => (int) get_term_meta( $term->term_id, '_ungated_from', true ),
				'until' => $until,
			);
		}
	}

	return null;
}

/**
 * Series terms a post inherits a window from: its own taxonomy terms, or for a
 * landing page the term named in `_ungate_series`.
 *
 * @return WP_Term[]
 */
function hcp_ungate_series_terms( $post_id ) {
	if ( ! taxonomy_exists( HCP_UNGATE_TAXONOMY ) ) {
		return array();
	}

	$terms = get_the_terms( $post_id, HCP_UNGATE_TAXONOMY );
	if ( is_array( $terms ) && $terms ) {
		return $terms;
	}

	$slug = (string) get_post_meta( $post_id, '_ungate_series', true );
	if ( '' === $slug ) {
		return array();
	}

	$term = get_term_by( 'slug', $slug, HCP_UNGATE_TAXONOMY );

	return $term instanceof WP_Term ? array( $term ) : array();
}

/**
 * Whether a post is inside its ungate window right now.
 *
 * An unset or zero `from` means the window opens immediately and runs until
 * `until`, matching the JS and the articles that predate the `from` key.
 */
function hcp_ungate_is_open( $post_id ) {
	$window = hcp_ungate_window( $post_id );
	if ( null === $window ) {
		return false;
	}

	$now = time();

	return $now >= $window['from'] && $now < $window['until'];
}

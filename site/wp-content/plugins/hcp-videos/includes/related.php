<?php
/**
 * Related-videos resolver. Order:
 *   1. Manual picks (ACF `related_videos`), in the chosen order.
 *   2. Other listed videos sharing a `video_topic` term (series/episode order).
 *   3. Every remaining listed video (most recent first).
 * Always excludes the current video and de-dupes.
 *
 * Series-scoping: a video that belongs to a `video_topic` (a series, e.g.
 * Clinical Bites) relates ONLY within that series — step 3 is skipped so the
 * sidebar doesn't top up with unrelated videos. Videos with no topic keep the
 * full-library fallback.
 *
 * Within a series the order also wraps: an episode suggests the ones that come
 * after it and then returns to the start, so the last episode points back at
 * the first instead of running out of suggestions. That ordering is computed,
 * not curated — a new series needs no per-episode picks, and re-ordering one
 * cannot leave a stale hand-made list behind.
 */

defined( 'ABSPATH' ) || exit;

/**
 * meta_query fragment matching "listed" videos (video_listed truthy or unset).
 */
function hcp_videos_listed_meta_query(): array {
	return array(
		'relation' => 'OR',
		array( 'key' => 'video_listed', 'value' => '1' ),
		array( 'key' => 'video_listed', 'compare' => 'NOT EXISTS' ),
	);
}

function hcp_videos_related_ids( int $post_id, int $limit = 50 ): array {
	$ordered = array();

	$add = function ( $ids ) use ( &$ordered, $post_id ) {
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id && $id !== $post_id && ! in_array( $id, $ordered, true ) ) {
				$ordered[] = $id;
			}
		}
	};

	// 1. Manual picks (published).
	$manual = get_field( 'related_videos', $post_id );
	if ( is_array( $manual ) ) {
		$add( array_filter( $manual, fn( $id ) => get_post_status( (int) $id ) === 'publish' ) );
	}

	// Auto-fill is opt-out: '0' turns it off; missing/'' keeps it on (back-compat).
	// This is also the escape hatch for an episode that must show a hand-picked
	// list rather than its place in the run.
	if ( get_post_meta( $post_id, 'related_autofill', true ) === '0' ) {
		return array_slice( $ordered, 0, $limit );
	}

	// 2. Series episodes, continuing from this one and wrapping to the start.
	// The computed run replaces manual picks rather than following them: a
	// curated list on one episode would break the sequence for that episode
	// alone, which is the inconsistency this ordering exists to remove.
	$following = hcp_videos_series_following_ids( $post_id );
	if ( null !== $following ) {
		return array_slice( $following, 0, $limit );
	}

	// 3. Everything else (listed), most recent first — only for videos with no topic.
	$add( get_posts( array(
		'post_type'      => 'video',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'post__not_in'   => array( $post_id ),
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => hcp_videos_listed_meta_query(),
	) ) );

	return array_slice( $ordered, 0, $limit );
}

<?php
/**
 * Related-videos resolver — returns ALL other videos in relevance order so the
 * sidebar acts as the full library (scrolling, like YouTube). Order:
 *   1. Manual picks (ACF `related_videos`), in the chosen order.
 *   2. Other listed videos sharing a `video_topic` term (most recent first).
 *   3. Every remaining listed video (most recent first).
 * Always excludes the current video and de-dupes.
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

	// 2. Same-topic listed videos.
	$terms = get_the_terms( $post_id, 'video_topic' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$add( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => array( $post_id ),
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => hcp_videos_listed_meta_query(),
			'tax_query'      => array( array(
				'taxonomy' => 'video_topic',
				'field'    => 'term_id',
				'terms'    => wp_list_pluck( $terms, 'term_id' ),
			) ),
		) ) );
	}

	// 3. Everything else (listed), most recent first.
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

<?php
/**
 * Related-videos resolver.
 *
 * Order of preference:
 *   1. Manual picks (ACF `related_videos`), in the chosen order.
 *   2. Auto: other listed videos sharing a `video_topic` term.
 *   3. Fallback: most-recent other listed videos.
 *
 * Always excludes the current video and unpublished posts, returns up to $limit.
 */

defined( 'ABSPATH' ) || exit;

function hcp_videos_related_ids( int $post_id, int $limit = 6 ): array {
	// 1. Manual.
	$manual = get_field( 'related_videos', $post_id );
	if ( is_array( $manual ) && $manual ) {
		$ids = array_values( array_filter( array_map( 'intval', $manual ), function ( $id ) use ( $post_id ) {
			return $id && $id !== $post_id && get_post_status( $id ) === 'publish';
		} ) );
		if ( $ids ) {
			return array_slice( $ids, 0, $limit );
		}
	}

	// 2. Auto by shared topic.
	$terms = get_the_terms( $post_id, 'video_topic' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$term_ids = wp_list_pluck( $terms, 'term_id' );
		$ids      = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => array( $post_id ),
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'video_topic',
					'field'    => 'term_id',
					'terms'    => $term_ids,
				),
			),
		) );
		if ( $ids ) {
			return $ids;
		}
	}

	// 3. Fallback: latest others.
	return get_posts( array(
		'post_type'      => 'video',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'post__not_in'   => array( $post_id ),
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
}

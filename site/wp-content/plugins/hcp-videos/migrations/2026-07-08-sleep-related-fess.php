<?php
/**
 * Point the sleep-health video's related sidebar at the FESS videos.
 *
 * "The Role of Pharmacists in sleep health" sits alone in its topic, so the
 * series-scoped resolver leaves its sidebar empty. Cross-promote the FESS
 * videos there instead (nasal congestion & sleep are clinically linked), in
 * listing order, with auto-fill off so the list stays the FESS set.
 *
 * Video by Vimeo ID, series by term slug (both stable per env). Idempotent and
 * non-destructive: skips if related videos are already picked.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Sleep-health video: related sidebar = FESS videos (cross-promote).',
	'up'          => function () {
		$sleep_vimeo = '865429849';
		$series_slug = 'fess';

		$video_id = 0;
		foreach ( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) as $vid ) {
			if ( hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) ) === $sleep_vimeo ) {
				$video_id = $vid;
				break;
			}
		}
		if ( ! $video_id ) {
			return "no video for vimeo {$sleep_vimeo}";
		}

		if ( ! empty( get_field( 'related_videos', $video_id ) ) ) {
			return "video {$video_id} already has manual picks (skipped)";
		}

		$fess = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => array( $video_id ),
			'fields'         => 'ids',
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
			'tax_query'      => array( array(
				'taxonomy' => 'video_topic',
				'field'    => 'slug',
				'terms'    => $series_slug,
			) ),
		) );
		if ( ! $fess ) {
			return "no videos in series {$series_slug}";
		}

		update_field( 'related_videos', $fess, $video_id );
		update_field( 'related_autofill', 0, $video_id );

		return "video {$video_id} <- " . count( $fess ) . ' FESS videos: ' . implode( ',', $fess );
	},
);

<?php
/**
 * Point the Hydralyte video's related sidebar at the Clinical Bites series.
 *
 * The Hydralyte video sits alone in its topic, so the series-scoped resolver
 * leaves its sidebar empty. Cross-promote the Clinical Bites episodes there
 * instead, in episode order (Episode 1 first, menu_order ASC), and turn
 * auto-fill off so the list stays exactly the series.
 *
 * Video resolved by Vimeo ID, series by term slug (both stable per env).
 * Idempotent and non-destructive: skips if related videos are already picked.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Hydralyte video: related sidebar = Clinical Bites episodes, ascending (Ep 1 first).',
	'up'          => function () {
		$hydralyte_vimeo = '1122083239';
		$series_slug     = 'clinical-bites-diabetes';

		$video_id = 0;
		foreach ( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) as $vid ) {
			if ( hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) ) === $hydralyte_vimeo ) {
				$video_id = $vid;
				break;
			}
		}
		if ( ! $video_id ) {
			return "no video for vimeo {$hydralyte_vimeo}";
		}

		if ( ! empty( get_field( 'related_videos', $video_id ) ) ) {
			return "video {$video_id} already has manual picks (skipped)";
		}

		$episodes = get_posts( array(
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
		if ( ! $episodes ) {
			return "no published videos in series {$series_slug}";
		}

		update_field( 'related_videos', $episodes, $video_id );
		update_field( 'related_autofill', 0, $video_id );

		return "video {$video_id} <- " . count( $episodes ) . ' episodes (asc): ' . implode( ',', $episodes );
	},
);

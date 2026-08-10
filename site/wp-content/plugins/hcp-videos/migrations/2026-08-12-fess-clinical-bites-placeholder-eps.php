<?php
/**
 * PLACEHOLDER episodes 2-3 for the Allergic Rhinitis Rounds series.
 *
 * Titles are final (from the CAPH0123 copy doc); the video, description and
 * thumbnail duplicate episode 1 until the real assets arrive. Created so the
 * hub section and landing page show the full three-tile row for client review.
 * Replace the vimeo field and content in place when episodes 2-3 are delivered
 * — do NOT create new posts, the slugs and menu_order here are canonical.
 *
 * Idempotent: keyed by episode slugs.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Allergic Rhinitis Rounds: placeholder episodes 2-3 (duplicate of episode 1).',
	'up'          => function () {
		$ep1 = get_page_by_path( 'not-another-sinus-infection', OBJECT, 'video' );
		if ( ! $ep1 ) {
			throw new RuntimeException( 'Episode 1 not found — run the topic-video-1 migration first.' );
		}

		$episodes = array(
			2 => array( 'title' => 'Expecting and Congested', 'slug' => 'expecting-and-congested' ),
			3 => array( 'title' => '&#8220;Are Steroids Safe For My Child?&#8221;', 'slug' => 'are-steroids-safe-for-my-child' ),
		);

		$log = array();
		foreach ( $episodes as $order => $ep ) {
			if ( get_page_by_path( $ep['slug'], OBJECT, 'video' ) ) {
				$log[] = "{$ep['slug']} already present";
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_type'    => 'video',
				'post_status'  => 'publish',
				'post_title'   => wp_specialchars_decode( $ep['title'] ),
				'post_name'    => $ep['slug'],
				'post_content' => $ep1->post_content,
				'menu_order'   => $order,
			), true );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				throw new RuntimeException( "Failed to create {$ep['slug']}." );
			}

			update_field( 'vimeo', get_field( 'vimeo', $ep1->ID ), $post_id );
			update_field( 'video_listed', 1, $post_id );
			update_field( 'video_resources', get_field( 'video_resources', $ep1->ID, false ), $post_id );
			update_field( 'related_videos', get_field( 'related_videos', $ep1->ID, false ), $post_id );

			if ( taxonomy_exists( 'audience' ) ) {
				wp_set_object_terms( $post_id, 'Healthcare Professional', 'audience', false );
			}
			wp_set_object_terms( $post_id, 'clinical-bites-allergic-rhinitis', 'video_topic', false );

			if ( function_exists( 'hcp_videos_cache_vimeo_meta' ) ) {
				hcp_videos_cache_vimeo_meta( $post_id );
			}

			$log[] = "{$ep['slug']} created ({$post_id})";
		}

		return implode( '; ', $log );
	},
);

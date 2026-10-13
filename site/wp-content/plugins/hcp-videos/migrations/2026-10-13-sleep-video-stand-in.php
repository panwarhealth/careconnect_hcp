<?php
/**
 * Sleep video stand-in: the original (Sleep Health Foundation webinar with
 * Prof Bandana Saini, June 2023) was lost with the Vimeo account. Until it is
 * found, the page plays Prof Saini's APP 2023 Gold Coast talk on the same topic.
 *
 * Its own file/poster keys so the webinar can later take the page back without
 * clashing with cached URLs. Captions are removed: the saved ones belong to the
 * webinar and would not line up.
 *
 * Idempotent: rewrites the same values.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Sleep video: APP 2023 Gold Coast talk as a stand-in for the lost webinar.',
	'up'          => function () {
		$cdn  = 'https://cdn-careconnect.panwarhealth.com.au/videos/the-role-of-pharmacists-in-sleep-health/';
		$post = null;
		foreach ( get_posts( array( 'post_type' => 'video', 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $p ) {
			if ( '865429849' === hcp_videos_vimeo_id( get_post_meta( $p->ID, 'vimeo', true ) ) ) {
				$post = $p;
				break;
			}
		}
		if ( ! $post ) {
			throw new RuntimeException( 'Sleep video post not found.' );
		}

		update_post_meta( $post->ID, 'video_src', $cdn . 'the-role-of-pharmacists-in-sleep-health-app2023.mp4' );
		update_post_meta( $post->ID, '_hcpvid_thumb', $cdn . 'poster-app2023.jpg' );
		update_post_meta( $post->ID, '_hcpvid_duration', '34:03' );
		delete_post_meta( $post->ID, '_hcpvid_captions' );

		return 'Sleep video (post ' . $post->ID . ') now plays the APP 2023 talk.';
	},
);

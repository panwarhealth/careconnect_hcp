<?php
/**
 * Sleep video: the original Sleep Health Foundation webinar (Prof Bandana
 * Saini, 27 June 2023) has been found, so it replaces the APP 2023 Gold Coast
 * stand-in set by 2026-10-13-sleep-video-stand-in.
 *
 * Restores the original Vimeo poster (still at poster.jpg on the CDN) and the
 * webinar's saved captions (bundled in migrations/captions/). The stand-in
 * files stay on the CDN.
 *
 * Idempotent: rewrites the same values.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Sleep video: original Sleep Health Foundation webinar restored, with its poster and captions.',
	'up'          => function () {
		$key  = 'the-role-of-pharmacists-in-sleep-health';
		$cdn  = 'https://cdn-careconnect.panwarhealth.com.au/videos/' . $key . '/';
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

		$vtt = str_replace( "\r\n", "\n", (string) @file_get_contents( __DIR__ . '/captions/' . $key . '.vtt' ) );
		if ( ! hcp_videos_is_vtt( $vtt ) ) {
			throw new RuntimeException( 'Webinar caption file missing or invalid.' );
		}

		update_post_meta( $post->ID, 'video_src', $cdn . $key . '-webinar.mp4' );
		update_post_meta( $post->ID, '_hcpvid_thumb', $cdn . 'poster.jpg' );
		update_post_meta( $post->ID, '_hcpvid_duration', '30:54' );
		update_post_meta( $post->ID, HCP_VIDEOS_CAPTIONS_META, wp_slash( $vtt ) );

		return 'Sleep video (post ' . $post->ID . ') now plays the original webinar.';
	},
);

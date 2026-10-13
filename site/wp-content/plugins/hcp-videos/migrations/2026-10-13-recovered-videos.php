<?php
/**
 * Playable files for videos recovered after the R2 move, which shipped with
 * them showing "temporarily unavailable". Matched by legacy Vimeo ID as in
 * 2026-10-13-r2-video-hosting.
 *
 * Idempotent: rewrites the same URL.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Recovered videos: FESS Healthed Product Explainer file on R2.',
	'up'          => function () {
		$cdn = 'https://cdn-careconnect.panwarhealth.com.au/videos/';

		// Vimeo ID => CDN key.
		$map = array(
			'1125398505' => 'fess-healthed-product-explainer',
		);

		$ids = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$done = array();
		foreach ( $ids as $vid ) {
			$vimeo = hcp_videos_vimeo_id( get_post_meta( $vid, 'vimeo', true ) );
			if ( isset( $map[ $vimeo ] ) ) {
				update_post_meta( $vid, 'video_src', $cdn . $map[ $vimeo ] . '/' . $map[ $vimeo ] . '.mp4' );
				$done[] = $map[ $vimeo ];
			}
		}

		if ( count( $done ) !== count( $map ) ) {
			throw new RuntimeException( 'Matched only: ' . ( $done ? implode( ', ', $done ) : 'none' ) );
		}

		return 'Now playable: ' . implode( ', ', $done ) . '.';
	},
);

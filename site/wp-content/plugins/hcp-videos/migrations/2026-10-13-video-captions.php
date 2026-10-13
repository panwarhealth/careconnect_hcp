<?php
/**
 * Captions for the videos recovered from Vimeo.
 *
 * Vimeo's auto-generated English captions were saved before the account
 * closed and uploaded to the CDN as videos/{key}/captions.en.vtt. This copies
 * each into the video post, where the player serves it as a same-origin track.
 * Videos are matched by their legacy Vimeo ID, as in 2026-10-13-r2-video-hosting.
 *
 * Idempotent: rewrites the same text; videos that already have captions are
 * left alone so a later hand-edited track is never overwritten.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Captions: Vimeo auto-generated English captions onto the 13 recovered videos.',
	'up'          => function () {
		$cdn = 'https://cdn-careconnect.panwarhealth.com.au/videos/';

		// Vimeo ID => CDN key.
		$map = array(
			'1125398505' => 'fess-healthed-product-explainer',
			'839949755'  => 'fess-how-to-use-childrens-nasal-spray',
			'839949677'  => 'fess-how-to-use-nasal-spray',
			'839949859'  => 'fess-how-to-use-nasal-wash',
			'865429849'  => 'the-role-of-pharmacists-in-sleep-health',
			'1207250947' => 'why-sick-day-planning-is-important-in-diabetes',
			'1213139778' => 'what-goes-in-a-sick-day-plan-for-people-with-diabetes',
			'1213463817' => 'medication-management-during-sick-days',
			'1213464339' => 'dehydration-during-sick-days-the-role-of-ors',
			'1213464687' => 'preparing-a-sick-day-management-kit',
			'1216901419' => 'not-another-sinus-infection',
			'1219107918' => 'expecting-and-congested',
			'1219108342' => 'are-steroids-safe-for-my-child',
		);

		$ids = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$added   = 0;
		$skipped = 0;
		$failed  = array();
		foreach ( $ids as $vid ) {
			$vimeo = hcp_videos_vimeo_id( get_post_meta( $vid, 'vimeo', true ) );
			if ( ! isset( $map[ $vimeo ] ) ) {
				continue;
			}
			if ( '' !== (string) get_post_meta( $vid, HCP_VIDEOS_CAPTIONS_META, true ) ) {
				$skipped++;
				continue;
			}

			$resp = wp_remote_get( $cdn . $map[ $vimeo ] . '/captions.en.vtt', array( 'timeout' => 15 ) );
			$vtt  = str_replace( "\r\n", "\n", (string) wp_remote_retrieve_body( $resp ) );
			if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) || ! hcp_videos_is_vtt( $vtt ) ) {
				$failed[] = $map[ $vimeo ];
				continue;
			}

			update_post_meta( $vid, HCP_VIDEOS_CAPTIONS_META, wp_slash( $vtt ) );
			$added++;
		}

		if ( $failed ) {
			throw new RuntimeException( 'Could not fetch captions for: ' . implode( ', ', $failed ) . " ($added added)." );
		}

		return sprintf( '%d videos given captions; %d already had them.', $added, $skipped );
	},
);

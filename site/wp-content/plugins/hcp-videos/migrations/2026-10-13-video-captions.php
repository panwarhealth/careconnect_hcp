<?php
/**
 * Captions for the videos recovered from Vimeo.
 *
 * Vimeo's auto-generated English captions were saved before the account
 * closed and ship with this plugin in migrations/captions/{key}.vtt (prod
 * cannot make outbound HTTP requests, so they are read from disk). This copies
 * each into the video post, where the player serves it as a same-origin track.
 * Videos are matched by their legacy Vimeo ID, as in 2026-10-13-r2-video-hosting.
 *
 * The sleep video is left out: its captions belong to the lost webinar, and
 * the page now plays a different recording.
 *
 * Idempotent: videos that already have captions are left alone, so a later
 * hand-edited track is never overwritten.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Captions: Vimeo auto-generated English captions onto the 12 recovered videos.',
	'up'          => function () {
		$dir = __DIR__ . '/captions/';

		// Vimeo ID => caption file key.
		$map = array(
			'1125398505' => 'fess-healthed-product-explainer',
			'839949755'  => 'fess-how-to-use-childrens-nasal-spray',
			'839949677'  => 'fess-how-to-use-nasal-spray',
			'839949859'  => 'fess-how-to-use-nasal-wash',
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

			$vtt = str_replace( "\r\n", "\n", (string) @file_get_contents( $dir . $map[ $vimeo ] . '.vtt' ) );
			if ( ! hcp_videos_is_vtt( $vtt ) ) {
				$failed[] = $map[ $vimeo ];
				continue;
			}

			update_post_meta( $vid, HCP_VIDEOS_CAPTIONS_META, wp_slash( $vtt ) );
			$added++;
		}

		if ( $failed ) {
			throw new RuntimeException( 'Missing or invalid caption files: ' . implode( ', ', $failed ) . " ($added added)." );
		}

		return sprintf( '%d videos given captions; %d already had them.', $added, $skipped );
	},
);

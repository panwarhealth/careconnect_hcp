<?php
/**
 * Move video playback from Vimeo to the Care Connect R2 bucket.
 *
 * Videos are matched by their legacy Vimeo ID (stable across environments)
 * and mapped to the R2 key they were uploaded under with tools/video/publish.sh.
 * Every mapped video gets its poster; only those with a recovered file get a
 * video_src, so the rest show the "temporarily unavailable" notice.
 *
 * Also:
 * - inline Vimeo iframes in pages/posts become [hcp_video slug="..."];
 * - resource cards that linked to vimeo.com link to the matching video page.
 *
 * The legacy `vimeo` field on video posts is left untouched for rollback.
 * Idempotent: re-running rewrites the same values; replaced iframes are gone.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Video hosting: Vimeo to R2 (cdn-careconnect.panwarhealth.com.au); inline embeds to [hcp_video]; resource cards to video pages.',
	'up'          => function () {
		global $wpdb;

		$cdn = 'https://cdn-careconnect.panwarhealth.com.au/videos/';

		// Vimeo ID => [ R2 key, file recovered ].
		$map = array(
			'1122083239' => array( 'how-hydralyte-rehydrates-faster-than-water-alone', true ),
			'1125398505' => array( 'fess-healthed-product-explainer', false ),
			'839949755'  => array( 'fess-how-to-use-childrens-nasal-spray', true ),
			'839949677'  => array( 'fess-how-to-use-nasal-spray', true ),
			'839949859'  => array( 'fess-how-to-use-nasal-wash', true ),
			'865429849'  => array( 'the-role-of-pharmacists-in-sleep-health', false ),
			'1207250947' => array( 'why-sick-day-planning-is-important-in-diabetes', true ),
			'1213139778' => array( 'what-goes-in-a-sick-day-plan-for-people-with-diabetes', true ),
			'1213463817' => array( 'medication-management-during-sick-days', true ),
			'1213464339' => array( 'dehydration-during-sick-days-the-role-of-ors', true ),
			'1213464687' => array( 'preparing-a-sick-day-management-kit', true ),
			'1216901419' => array( 'not-another-sinus-infection', true ),
			'1219107918' => array( 'expecting-and-congested', true ),
			'1219108342' => array( 'are-steroids-safe-for-my-child', true ),
		);

		// Vimeo ID => video post, across every status.
		$video_posts = array();
		$ids         = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		foreach ( $ids as $vid ) {
			$vimeo = hcp_videos_vimeo_id( get_post_meta( $vid, 'vimeo', true ) );
			if ( isset( $map[ $vimeo ] ) ) {
				$video_posts[ $vimeo ] = get_post( $vid );
			}
		}

		$playable = 0;
		foreach ( $video_posts as $vimeo => $post ) {
			list( $key, $recovered ) = $map[ $vimeo ];
			update_post_meta( $post->ID, '_hcpvid_thumb', $cdn . $key . '/poster.jpg' );
			if ( $recovered ) {
				update_field( 'field_hcpvid_src', $cdn . $key . '/' . $key . '.mp4', $post->ID );
				$playable++;
			}
		}

		// Inline Vimeo iframes => [hcp_video]. Unknown IDs are left alone.
		$embeds   = 0;
		$contents = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts}
			 WHERE post_content LIKE '%player.vimeo.com/video/%'
			 AND post_type NOT IN ('revision') AND post_status NOT IN ('trash', 'auto-draft')"
		);
		foreach ( $contents as $row ) {
			$count   = 0;
			$content = preg_replace_callback(
				'~<iframe\b[^>]*player\.vimeo\.com/video/(\d+)[^>]*>\s*</iframe>~i',
				function ( $m ) use ( $video_posts ) {
					return isset( $video_posts[ $m[1] ] )
						? '[hcp_video slug="' . $video_posts[ $m[1] ]->post_name . '"]'
						: $m[0];
				},
				$row->post_content,
				-1,
				$count
			);
			if ( $content !== $row->post_content ) {
				wp_update_post( wp_slash( array( 'ID' => $row->ID, 'post_content' => $content ) ) );
				$embeds += $count;
			}
		}

		// Resource cards: vimeo.com link => the matching video page.
		$cards     = 0;
		$resources = get_posts( array(
			'post_type'      => 'resources',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array(
				'key'     => 'vimeo',
				'value'   => 'vimeo.com',
				'compare' => 'LIKE',
			) ),
		) );
		foreach ( $resources as $rid ) {
			$vimeo = hcp_videos_vimeo_id( get_post_meta( $rid, 'vimeo', true ) );
			if ( isset( $video_posts[ $vimeo ] ) ) {
				update_post_meta( $rid, 'vimeo', get_permalink( $video_posts[ $vimeo ] ) );
				$cards++;
			}
		}

		return sprintf(
			'%d of %d video posts matched (%d playable from R2); %d inline embeds replaced; %d resource cards relinked.',
			count( $video_posts ),
			count( $map ),
			$playable,
			$embeds,
			$cards
		);
	},
);

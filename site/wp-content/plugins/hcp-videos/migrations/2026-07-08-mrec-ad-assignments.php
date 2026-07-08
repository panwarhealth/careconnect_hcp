<?php
/**
 * Assign the 300x250 MREC sidebar ads:
 *   - Hydralyte MREC -> "Why Sick Day Planning" (Clinical Bites) video
 *   - FESS MREC       -> "Role of Pharmacists in sleep health" video
 *
 * Videos resolved by Vimeo ID; MREC images resolved by filename (LIKE), so the
 * per-env attachment ID doesn't matter. PREREQUISITE: the MREC gifs must be
 * uploaded to the media library first (they are custom assets, not in the DB
 * seed) — if absent this migration is a safe no-op. Non-destructive: only sets
 * ad_image when it is currently empty.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Assign MREC ads (Hydralyte -> CB video, FESS -> sleep video).',
	'up'          => function () {
		$find_mrec = function ( $needle ) {
			$atts = get_posts( array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( array(
					'key'     => '_wp_attached_file',
					'value'   => $needle,
					'compare' => 'LIKE',
				) ),
			) );
			return $atts ? (int) $atts[0] : 0;
		};

		$find_video = function ( $vimeo ) {
			foreach ( get_posts( array(
				'post_type'      => 'video',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) ) as $vid ) {
				if ( hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) ) !== $vimeo ) {
					continue;
				}
				// Skip staging/local "(preview)" placeholder episodes that share the
				// real video's Vimeo ID (they don't exist on prod).
				if ( stripos( get_the_title( $vid ), '(preview)' ) !== false ) {
					continue;
				}
				return (int) $vid;
			}
			return 0;
		};

		$pairs = array(
			array( 'mrec-hydralyte', '1207250947' ), // Hydralyte MREC -> Clinical Bites video
			array( 'mrec-fess', '865429849' ),       // FESS MREC -> sleep-health video
		);

		$log = array();
		foreach ( $pairs as list( $needle, $vimeo ) ) {
			$ad    = $find_mrec( $needle );
			$video = $find_video( $vimeo );
			if ( ! $ad || ! $video ) {
				$log[] = "{$needle}/{$vimeo}: " . ( $ad ? '' : 'no image; ' ) . ( $video ? '' : 'no video' );
				continue;
			}
			if ( ! empty( get_field( 'ad_image', $video ) ) ) {
				$log[] = "video {$video} already has an ad (skipped)";
				continue;
			}
			update_field( 'ad_image', $ad, $video );
			$log[] = "video {$video} <- {$needle} ({$ad})";
		}

		return implode( '; ', $log );
	},
);

<?php
/**
 * Assign the Hydralyte MREC sidebar ad to Clinical Bites episodes 2-5, so the
 * whole series carries the same ad as episode 1.
 *
 * Same conventions as 2026-07-08-mrec-ad-assignments.php: image resolved by
 * filename (LIKE) so the per-env attachment ID doesn't matter; only sets
 * ad_image when currently empty. Throws (stays pending) until the gif is
 * uploaded to the media library.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Assign the Hydralyte MREC ad to Clinical Bites episodes 2-5.',
	'up'          => function () {
		$atts = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array( array(
				'key'     => '_wp_attached_file',
				'value'   => 'mrec-hydralyte',
				'compare' => 'LIKE',
			) ),
		) );
		if ( ! $atts ) {
			// Throw so the migration stays pending until the gif is uploaded.
			throw new RuntimeException( 'mrec-hydralyte not in media library yet — upload it, then re-run migrations.' );
		}
		$mrec = (int) $atts[0];

		$episodes = array( '1213139778', '1213463817', '1213464339', '1213464687' );

		$log = array();
		foreach ( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) as $vid ) {
			if ( ! in_array( hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) ), $episodes, true ) ) {
				continue;
			}
			if ( get_field( 'ad_image', $vid ) ) {
				$log[] = "{$vid} already has an ad (skipped)";
				continue;
			}
			update_field( 'ad_image', $mrec, $vid );
			$log[] = "{$vid} <- mrec {$mrec}";
		}

		return $log ? implode( '; ', $log ) : 'no episode videos found';
	},
);

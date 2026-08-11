<?php
/**
 * Client markup (10.08.26): episode 4's Related videos are curated — Ep 5,
 * then the Hydralyte MOA video; the third tile is removed. Manual picks with
 * auto-fill off, the escape hatch built for exactly this case.
 *
 * Keyed by Vimeo ID (ep 4 = 1213464339); MOA video resolved by slug.
 *
 * Idempotent: overwrites the same values on re-run.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Clinical Bites episode 4: curated related list — Ep 5 then the Hydralyte MOA video.',
	'up'          => function () {
		$moa = get_page_by_path( 'how-hydralyte-rehydrates-faster-than-water-alone', OBJECT, 'video' );
		if ( ! $moa ) {
			throw new RuntimeException( 'Hydralyte MOA video not found.' );
		}

		$ep5 = null;
		$ep4 = null;
		foreach ( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) as $vid ) {
			$vimeo = hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) );
			if ( '1213464339' === $vimeo ) {
				$ep4 = $vid;
			} elseif ( '1213464687' === $vimeo ) {
				$ep5 = $vid;
			}
		}
		if ( ! $ep4 || ! $ep5 ) {
			throw new RuntimeException( 'Episode 4 or 5 not found by Vimeo ID.' );
		}

		update_field( 'related_videos', array( $ep5, $moa->ID ), $ep4 );
		update_post_meta( $ep4, 'related_autofill', '0' );

		return "Episode 4 ({$ep4}) related set to Ep 5 ({$ep5}) + MOA ({$moa->ID}), auto-fill off.";
	},
);

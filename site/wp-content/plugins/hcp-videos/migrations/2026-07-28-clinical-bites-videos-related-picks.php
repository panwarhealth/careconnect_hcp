<?php
/**
 * Manual related-video picks for Clinical Bites episodes 2-5, per the CAPH0105
 * copy doc. The series auto-fill orders episodes ascending, which does not
 * match the specified rotations, so each episode gets explicit picks with
 * auto-fill off. Episode 1 keeps the default series auto-fill (2, 3, 4).
 *
 * Also refreshes the Hydralyte MOA video's picks to the full episode list:
 * its 2026-07-07 migration runs before the episodes exist on a fresh deploy,
 * so it captures episode 1 only.
 *
 * Idempotent: picks are recomputed from Vimeo IDs each run and overwritten.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Clinical Bites episodes 2-5: manual related picks per copy doc; refresh Hydralyte picks.',
	'up'          => function () {
		$vimeo_ids = array(
			'ep1'       => '1207250947',
			'ep2'       => '1213139778',
			'ep3'       => '1213463817',
			'ep4'       => '1213464339',
			'ep5'       => '1213464687',
			'hydralyte' => '1122083239',
		);

		// Lowest matching ID per Vimeo ID: staging has manual "(preview)" dupes
		// sharing episode 1's Vimeo ID; the original post always predates them.
		$posts = array();
		foreach ( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) as $vid ) {
			$key = array_search( hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) ), $vimeo_ids, true );
			if ( $key !== false && ( ! isset( $posts[ $key ] ) || $vid < $posts[ $key ] ) ) {
				$posts[ $key ] = (int) $vid;
			}
		}

		$missing = array_diff_key( $vimeo_ids, $posts );
		if ( $missing ) {
			// Throw so the migration stays pending rather than being marked applied.
			throw new RuntimeException( 'Missing videos for: ' . implode( ', ', array_keys( $missing ) ) . ' — run the episode 2-5 migration first.' );
		}

		$picks = array(
			'ep2' => array( 'ep3', 'ep4', 'ep5' ),
			'ep3' => array( 'ep4', 'ep5', 'ep1' ),
			'ep4' => array( 'ep5', 'hydralyte', 'ep1' ),
			'ep5' => array( 'ep2', 'ep3', 'ep4' ),
		);

		$log = array();
		foreach ( $picks as $ep => $related ) {
			$ids = array_map( fn( $k ) => $posts[ $k ], $related );
			update_field( 'related_videos', $ids, $posts[ $ep ] );
			update_field( 'related_autofill', 0, $posts[ $ep ] );
			$log[] = "{$ep} <- " . implode( ',', $related );
		}

		update_field( 'related_videos', array( $posts['ep1'], $posts['ep2'], $posts['ep3'], $posts['ep4'], $posts['ep5'] ), $posts['hydralyte'] );
		update_field( 'related_autofill', 0, $posts['hydralyte'] );
		$log[] = 'hydralyte <- ep1..ep5';

		return implode( '; ', $log );
	},
);

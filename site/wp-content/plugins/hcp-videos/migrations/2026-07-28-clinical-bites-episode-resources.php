<?php
/**
 * Curate the bottom-of-page Resources for Clinical Bites episodes 2-5 — same
 * three picks as episode 1, per client review feedback.
 *
 * Same conventions as 2026-07-07-video-resources-curation.php: keyed by Vimeo
 * ID -> resource slugs, non-destructive (skips videos already curated).
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Clinical Bites episodes 2-5: same Resources picks as episode 1.',
	'up'          => function () {
		$slugs = array(
			'hydarlyte-sick-days-care-plan',
			'hydralyte-patient-leaflet',
			'sip-to-stand-why-hydration-is-essential-in-pots',
		);
		$episodes = array( '1213139778', '1213463817', '1213464339', '1213464687' );

		$rids = array();
		foreach ( $slugs as $slug ) {
			$r = get_page_by_path( $slug, OBJECT, 'resources' );
			if ( ! $r ) {
				$r = get_page_by_path( $slug, OBJECT, 'post' );
			}
			if ( $r ) {
				$rids[] = $r->ID;
			}
		}
		if ( count( $rids ) !== count( $slugs ) ) {
			// Throw so the migration stays pending rather than curating a partial list.
			throw new RuntimeException( 'Only ' . count( $rids ) . ' of ' . count( $slugs ) . ' resources resolved.' );
		}

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
			if ( ! empty( get_field( 'video_resources', $vid ) ) ) {
				$log[] = "{$vid} already curated (skipped)";
				continue;
			}
			update_field( 'video_resources', $rids, $vid );
			$log[] = "{$vid} <- " . implode( ',', $rids );
		}

		return $log ? implode( '; ', $log ) : 'no episode videos found';
	},
);

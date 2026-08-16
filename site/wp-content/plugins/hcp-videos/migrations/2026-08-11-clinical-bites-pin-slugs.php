<?php
/**
 * Pin the Clinical Bites episode slugs.
 *
 * The migrations that create these videos set no `post_name`, so WordPress
 * derives one from the title. That has produced identical slugs on local and
 * staging, but derivation is not a guarantee: a title tweak, or an existing
 * post already holding the slug, and WordPress silently appends `-2`.
 *
 * These URLs are handed to Vimeo as end-screen buttons on the videos
 * themselves, which puts them outside our control once set — the same reason
 * the landing page slug is hardcoded for its printed QR codes.
 *
 * Matching is by Vimeo ID, not title: the title is the thing most likely to be
 * edited, and is exactly what we cannot depend on here.
 *
 * Idempotent: re-running finds every slug already correct.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Pin the five Clinical Bites episode slugs so the Vimeo end-screen links cannot break.',
	'up'          => function () {
		// Vimeo ID => required slug.
		$slugs = array(
			'1207250947' => 'why-sick-day-planning-is-important-in-diabetes',
			'1213139778' => 'what-goes-in-a-sick-day-plan-for-people-with-diabetes',
			'1213463817' => 'medication-management-during-sick-days',
			'1213464339' => 'dehydration-during-sick-days-the-role-of-ors',
			'1213464687' => 'preparing-a-sick-day-management-kit',
		);

		$episodes = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( array(
				'taxonomy' => 'video_topic',
				'field'    => 'slug',
				'terms'    => 'clinical-bites-diabetes',
			) ),
		) );

		if ( ! $episodes ) {
			return 'No Clinical Bites episodes found — nothing to pin.';
		}

		$fixed   = array();
		$missing = array_keys( $slugs );

		foreach ( $episodes as $id ) {
			$vimeo = hcp_videos_vimeo_id( get_field( 'vimeo', $id ) );
			if ( ! isset( $slugs[ $vimeo ] ) ) {
				continue;
			}

			$missing = array_diff( $missing, array( $vimeo ) );
			$wanted  = $slugs[ $vimeo ];

			if ( get_post_field( 'post_name', $id ) === $wanted ) {
				continue;
			}

			// Clear any post already squatting the slug before claiming it, or
			// wp_unique_post_slug hands back the `-2` form this exists to prevent.
			$squatter = get_page_by_path( $wanted, OBJECT, 'video' );
			if ( $squatter && (int) $squatter->ID !== (int) $id ) {
				wp_update_post( array(
					'ID'        => $squatter->ID,
					'post_name' => $wanted . '-superseded-' . $squatter->ID,
				) );
			}

			wp_update_post( array( 'ID' => $id, 'post_name' => $wanted ) );
			$fixed[] = $wanted;
		}

		$note = $fixed ? 'Pinned: ' . implode( ', ', $fixed ) . '.' : 'All five slugs already correct.';

		if ( $missing ) {
			$note .= ' NOT FOUND for Vimeo ID(s): ' . implode( ', ', $missing ) . '.';
		}

		return $note;
	},
);

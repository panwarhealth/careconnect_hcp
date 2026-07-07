<?php
/**
 * Seed the curated Resources shown at the bottom of each video page.
 *
 * Keyed by Vimeo ID (stable per video) -> resource slugs (stable per env), so
 * the same associations reproduce across local/staging/prod even though post
 * IDs differ. Sets the ACF `video_resources` field.
 *
 * Idempotent and non-destructive: skips any video that already has resources
 * picked (so it never clobbers a later manual edit) and skips videos/resources
 * not present in the current environment.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Curate bottom-of-page Resources per video (Vimeo ID -> resource slugs).',
	'up'          => function () {
		$map = array(
			'1122083239' => array( 'general-usage', 'rehydrating-patients-who-have-vomiting-or-diarrhoea', 'dehydration' ),                     // How Hydralyte rehydrates faster than water alone
			'1125398505' => array( 'benefits-of-saline-booklet', 'national-asthma-council-allergic-rhinitis-treatment-chart', 'fess-mist-trial' ), // FESS – Healthed Product Explainer
			'839949755'  => array( 'how-to-spray-childrens-fess', 'nasal-saline-patient-leaflet', 'fess-recommendation' ),                     // FESS – How to use Children’s Nasal Spray
			'839949677'  => array( 'how-to-spray', 'nasal-saline-patient-leaflet', 'fess-recommendation' ),                                    // FESS – How to Use Nasal Spray
			'839949859'  => array( 'how-to-wash', 'nasal-washing-guide-for-patients', 'nasal-saline-patient-leaflet' ),                        // FESS – How to Use Nasal Wash
			'865429849'  => array( 'the-role-of-pharmacists-in-sleep-health', 'hcp-fess-sleep-factsheet', 'patient-fess-sleep-factsheet' ),    // The Role of Pharmacists in sleep health
			'1207250947' => array( 'hydarlyte-sick-days-care-plan', 'rehydrating-patients-who-have-vomiting-or-diarrhoea', 'general-usage' ),  // Why Sick Day Planning is Important in Diabetes
		);

		// Build a normalised Vimeo-ID -> video-post-ID index (handles URL or bare-ID storage).
		$by_vimeo = array();
		foreach ( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) as $vid ) {
			$vk = hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) );
			if ( $vk !== '' ) {
				$by_vimeo[ $vk ] = $vid;
			}
		}

		$log = array();
		foreach ( $map as $vimeo => $slugs ) {
			if ( empty( $by_vimeo[ $vimeo ] ) ) {
				$log[] = "no video for vimeo {$vimeo}";
				continue;
			}
			$vid = $by_vimeo[ $vimeo ];

			if ( ! empty( get_field( 'video_resources', $vid ) ) ) {
				$log[] = "video {$vid} already curated (skipped)";
				continue;
			}

			$rids = array();
			foreach ( $slugs as $slug ) {
				$r = get_page_by_path( $slug, OBJECT, 'resources' );
				if ( $r ) {
					$rids[] = $r->ID;
				}
			}
			if ( $rids ) {
				update_field( 'video_resources', $rids, $vid );
				$log[] = "video {$vid} <- " . count( $rids ) . ' resources';
			} else {
				$log[] = "video {$vid}: no matching resources found";
			}
		}

		return implode( '; ', $log );
	},
);

<?php
/**
 * Seed Clinical Bites episodes 2-5 (Diabetes Sick Day Management series).
 * Copy from "CAPH0105 Hydralyte Clinical Bites Videos 2-5 Description COPY.docx".
 * Idempotent: each episode is skipped if a video with its Vimeo ID exists.
 * menu_order = episode number (episode 1 is aligned too) so series order is
 * deterministic for the related-videos resolver and hub listings.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Create Clinical Bites episodes 2-5 and set series menu_order.',
	'up'          => function () {
		$topic_slug = 'clinical-bites-diabetes';

		if ( ! term_exists( $topic_slug, 'video_topic' ) ) {
			$term = wp_insert_term( 'Clinical Bites: Diabetes Sick Day Management', 'video_topic', array( 'slug' => $topic_slug ) );
			if ( is_wp_error( $term ) ) {
				return 'Failed to create topic: ' . $term->get_error_message();
			}
		}

		$series  = '<p>The Diabetes Sick Day Management Clinical Bites series provides practical guidance for primary care clinicians to improve the uptake and implementation of sick day action plans for patients with diabetes.</p>';
		$speaker = '<p style="font-style:italic;"><span class="speaker-label">About the speaker:</span> Deborah Hawthorne is a Credentialled Diabetes Educator, rural Consultant Pharmacist, and chair of the Pharmaceutical Society of Australia (PSA)\'s Consultant Pharmacist Community of Special Interest. She has a diverse background in GP clinics, diabetes education, medication reviews, telehealth, clinical education, and research. Her advocacy has earned her multiple national awards, including PSA Consultant Pharmacist of the Year 2023.</p>';

		$episodes = array(
			2 => array(
				'vimeo'   => '1213139778',
				'title'   => 'What Goes in a Sick Day Plan for People with Diabetes',
				'episode' => '<p>In this episode, Credentialled Diabetes Educator and Consultant Pharmacist Deborah Hawthorne goes through the key components of a sick day plan, and different considerations for patients with Type 1 vs Type 2 diabetes.</p>',
			),
			3 => array(
				'vimeo'   => '1213463817',
				'title'   => 'Medication Management During Sick Days',
				'episode' => '<p>In this episode, Credentialled Diabetes Educator and Consultant Pharmacist Deborah Hawthorne explains why some medications need to be paused during illness, and the helpful SADMANS mnemonic for remembering which medicines need to be reassessed.</p>',
			),
			4 => array(
				'vimeo'   => '1213464339',
				'title'   => 'Dehydration During Sick Days: The Role of ORS',
				'episode' => '<p>In this episode, Credentialled Diabetes Educator and Consultant Pharmacist Deborah Hawthorne discusses guideline recommendations for hydration during sick days, and clarifies the suitability of oral rehydration solutions for people living with diabetes based on glucose content.</p>',
			),
			5 => array(
				'vimeo'   => '1213464687',
				'title'   => 'Preparing a Sick Day Management Kit',
				'episode' => '<p>In this episode, Credentialled Diabetes Educator and Consultant Pharmacist Deborah Hawthorne offers her practical advice on items that can be recommended to patients to build their own \'sick day kit\' &ndash; helping to ensure they are always prepared at home.</p>',
			),
		);

		// Lowest matching ID: staging has manual "(preview)" dupes sharing
		// episode 1's Vimeo ID; the original post always predates them.
		$find_by_vimeo = function ( string $vimeo ): int {
			$matches = array();
			foreach ( get_posts( array(
				'post_type'      => 'video',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) ) as $vid ) {
				if ( hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) ) === $vimeo ) {
					$matches[] = (int) $vid;
				}
			}
			return $matches ? min( $matches ) : 0;
		};

		$log = array();

		// Episode 1 already exists: align its menu_order with the series.
		$ep1 = $find_by_vimeo( '1207250947' );
		if ( $ep1 ) {
			wp_update_post( array( 'ID' => $ep1, 'menu_order' => 1 ) );
			$log[] = "ep1={$ep1} (menu_order)";
		}

		foreach ( $episodes as $num => $ep ) {
			if ( $find_by_vimeo( $ep['vimeo'] ) ) {
				$log[] = "ep{$num} already present";
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_type'    => 'video',
				'post_status'  => 'publish',
				'post_title'   => $ep['title'],
				'post_content' => $ep['episode'] . $series . $speaker,
				'menu_order'   => $num,
			), true );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return "Failed to create episode {$num}. Done so far: " . implode( ', ', $log );
			}

			$ok = function_exists( 'update_field' )
				? ( update_field( 'vimeo', $ep['vimeo'], $post_id ) !== false
					&& update_field( 'video_listed', 1, $post_id ) !== false )
				: false;

			if ( ! $ok ) {
				wp_delete_post( $post_id, true );
				return "ACF update_field failed on episode {$num}; rolled back. Done so far: " . implode( ', ', $log );
			}

			if ( taxonomy_exists( 'audience' ) ) {
				wp_set_object_terms( $post_id, 'Healthcare Professional', 'audience', false );
			}
			wp_set_object_terms( $post_id, $topic_slug, 'video_topic', false );

			if ( function_exists( 'hcp_videos_cache_vimeo_meta' ) ) {
				hcp_videos_cache_vimeo_meta( $post_id );
			}

			$log[] = "ep{$num}={$post_id}";
		}

		return 'Clinical Bites episodes: ' . implode( ', ', $log );
	},
);

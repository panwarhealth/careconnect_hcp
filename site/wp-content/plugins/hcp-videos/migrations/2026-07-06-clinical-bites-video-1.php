<?php
/**
 * Seed the first Clinical Bites (Diabetes Sick Day Management) video and its
 * grouping topic. Idempotent: skips if a video with this Vimeo ID exists.
 * Videos 2-4 of the series follow the same pattern (same topic term).
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Create the Clinical Bites: Diabetes Sick Day Management topic and video 1.',
	'up'          => function () {
		$topic_name = 'Clinical Bites: Diabetes Sick Day Management';
		$topic_slug = 'clinical-bites-diabetes';

		if ( ! term_exists( $topic_slug, 'video_topic' ) ) {
			$term = wp_insert_term( $topic_name, 'video_topic', array( 'slug' => $topic_slug ) );
			if ( is_wp_error( $term ) ) {
				return 'Failed to create topic: ' . $term->get_error_message();
			}
		}

		$vimeo = '1207250947';
		$existing = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'vimeo',
			'meta_value'     => $vimeo,
		) );
		if ( $existing ) {
			return 'Clinical Bites video 1 already present.';
		}

		$content = '<p>In this episode, Credentialled Diabetes Educator and Consultant Pharmacist Deborah Hawthorne explains why illness is riskier in people living with diabetes, what a sick day action plan is and how it helps, and how health professionals can initiate sick day planning with their patients.</p>'
			. '<p>The Diabetes Sick Day Management Clinical Bites series provides practical guidance for primary care clinicians to improve the uptake and implementation of sick day action plans for patients with diabetes.</p>'
			. '<p style="font-style:italic;"><span class="speaker-label">About the speaker:</span> Deborah Hawthorne is a Credentialled Diabetes Educator, rural Consultant Pharmacist, and chair of the Pharmaceutical Society of Australia (PSA)\'s Consultant Pharmacist Community of Special Interest. She has a diverse background in GP clinics, diabetes education, medication reviews, telehealth, clinical education, and research. Her advocacy has earned her multiple national awards, including PSA Consultant Pharmacist of the Year 2023.</p>';

		$post_id = wp_insert_post( array(
			'post_type'    => 'video',
			'post_status'  => 'publish',
			'post_title'   => 'Why Sick Day Planning is Important in Diabetes',
			'post_content' => $content,
		), true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 'Failed to create video post.';
		}

		$ok = function_exists( 'update_field' )
			? ( update_field( 'vimeo', $vimeo, $post_id ) !== false
				&& update_field( 'video_listed', 1, $post_id ) !== false )
			: false;

		if ( ! $ok ) {
			wp_delete_post( $post_id, true );
			return 'ACF update_field failed; rolled back.';
		}

		if ( taxonomy_exists( 'audience' ) ) {
			wp_set_object_terms( $post_id, 'Healthcare Professional', 'audience', false );
		}
		wp_set_object_terms( $post_id, $topic_slug, 'video_topic', false );

		if ( function_exists( 'hcp_videos_cache_vimeo_meta' ) ) {
			hcp_videos_cache_vimeo_meta( $post_id );
		}

		return 'Clinical Bites video 1 created (post ' . $post_id . ').';
	},
);

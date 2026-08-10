<?php
/**
 * Seed the FESS Clinical Bites (Allergic Rhinitis Rounds) series: topic term,
 * series meta, and episode 1 with its resource picks and related extra.
 *
 * 3-part series; episodes 2-3 follow later under the same topic term. Related
 * videos resolve as the series run plus the manual extra (the Healthed product
 * explainer), per the copy doc.
 *
 * Idempotent: keyed by the episode slug and the term slug.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Create the Clinical Bites: Allergic Rhinitis Rounds topic and video 1.',
	'up'          => function () {
		$topic_name = 'Clinical Bites: Allergic Rhinitis Rounds';
		$topic_slug = 'clinical-bites-allergic-rhinitis';
		$vimeo      = '1216901419';

		$term = term_exists( $topic_slug, 'video_topic' );
		if ( ! $term ) {
			$term = wp_insert_term( $topic_name, 'video_topic', array( 'slug' => $topic_slug ) );
			if ( is_wp_error( $term ) ) {
				return 'Failed to create topic: ' . $term->get_error_message();
			}
		}
		$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		update_term_meta( $term_id, '_series_total', 3 );
		update_term_meta( $term_id, '_series_landing', '/allergic-rhinitis-clinical-bites/' );

		if ( get_page_by_path( 'not-another-sinus-infection', OBJECT, 'video' ) ) {
			return 'FESS Clinical Bites video 1 already present.';
		}

		$content = '<p>When should recurrent nasal symptoms trigger suspicion of underlying inflammatory disease? In this episode, Allergist and Medical Rhinologist Dr Jessica Tattersall discusses how to distinguish acute sinusitis episodes from uncontrolled allergic rhinitis, and how to optimise management for these patients.</p>'
			. '<p>The Allergic Rhinitis Rounds Clinical Bites series explores three common case scenarios, offering practical expert insights to support primary care providers with allergic rhinitis patient care.</p>'
			. '<p style="font-style:italic;"><span class="speaker-label">About the speaker:</span> Dr Jessica Tattersall is an Allergist and Medical Rhinologist who began her training in otolaryngology before pursuing a career in allergic diseases. She is a Fellow of the Royal Australian College of General Practitioners, holds a Masters of Medicine in Allergic Diseases from the University of Western Sydney, and is a member of the Australasian Society of Clinical Immunology and Allergy, the Australian and New Zealand Rhinological Society, and the American Rhinological Society. She has a special interest in allergic, non-allergic and inflammatory diseases of the airway and sinuses, and is actively involved in research and education in this area.</p>';

		$post_id = wp_insert_post( array(
			'post_type'    => 'video',
			'post_status'  => 'publish',
			'post_title'   => 'Not another sinus infection',
			'post_name'    => 'not-another-sinus-infection',
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

		// Related Resources (bottom of the video page + landing page section).
		$rids = array();
		foreach ( array(
			array( 'national-asthma-council-allergic-rhinitis-treatment-chart', 'resources' ),
			array( 'nasal-saline-patient-leaflet', 'resources' ),
			array( 'top-4-expert-tips-to-optimise-allergic-rhinitis-management-with-dr-jessica-tattersall', 'post' ),
		) as $pair ) {
			$r = get_page_by_path( $pair[0], OBJECT, $pair[1] );
			if ( $r ) {
				$rids[] = $r->ID;
			}
		}
		if ( 3 !== count( $rids ) ) {
			wp_delete_post( $post_id, true );
			throw new RuntimeException( 'Only ' . count( $rids ) . ' of 3 resources resolved.' );
		}
		update_field( 'video_resources', $rids, $post_id );

		// Series-wide related extra: shown after the other episodes.
		$explainer = get_page_by_path( 'fess-healthed-product-explainer', OBJECT, 'video' );
		if ( $explainer ) {
			update_field( 'related_videos', array( $explainer->ID ), $post_id );
		}

		if ( function_exists( 'hcp_videos_cache_vimeo_meta' ) ) {
			hcp_videos_cache_vimeo_meta( $post_id );
		}

		return 'FESS Clinical Bites video 1 created (post ' . $post_id . ').';
	},
);

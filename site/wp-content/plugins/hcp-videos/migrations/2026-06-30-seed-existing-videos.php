<?php
/**
 * Seed Video CPT posts from the videos currently hardcoded on the Tools &
 * Videos page. Idempotent: skips any video whose Vimeo ID already exists.
 * Descriptions are intentionally left blank for the content team to fill.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Create Video posts for the 6 existing Tools & Videos entries.',
	'up'          => function () {
		$videos = array(
			array( 'title' => 'How Hydralyte rehydrates faster than water alone', 'vimeo' => '1122083239', 'audience' => 'Healthcare Professional', 'topic' => 'Hydralyte' ),
			array( 'title' => 'FESS – Healthed Product Explainer',                'vimeo' => '1125398505', 'audience' => 'Healthcare Professional', 'topic' => 'FESS' ),
			array( 'title' => "FESS – How to use Children's Nasal Spray",         'vimeo' => '839949755',  'audience' => 'Patient',                'topic' => 'FESS' ),
			array( 'title' => 'FESS – How to Use Nasal Spray',                    'vimeo' => '839949677',  'audience' => 'Patient',                'topic' => 'FESS' ),
			array( 'title' => 'FESS – How to Use Nasal Wash',                     'vimeo' => '839949859',  'audience' => 'Patient',                'topic' => 'FESS' ),
			array( 'title' => 'The Role of Pharmacists in sleep health',          'vimeo' => '865429849',  'audience' => 'Pharmacist',             'topic' => 'Sleep' ),
		);

		$created = 0;
		$skipped = 0;

		foreach ( $videos as $v ) {
			// Idempotency: skip if a video with this Vimeo ID already exists.
			$existing = get_posts( array(
				'post_type'      => 'video',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'vimeo',
				'meta_value'     => $v['vimeo'],
			) );
			if ( $existing ) {
				$skipped++;
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_type'    => 'video',
				'post_status'  => 'publish',
				'post_title'   => $v['title'],
				'post_content' => '', // description added later by content team
			), true );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			// Vimeo (ACF text field) + listed flag. If ACF isn't loaded or
			// the field key can't resolve, update_field() returns false and we
			// delete the orphaned post so the idempotency check can retry cleanly.
			$ok = function_exists( 'update_field' )
				? ( update_field( 'vimeo', $v['vimeo'], $post_id ) !== false
					&& update_field( 'video_listed', 1, $post_id ) !== false )
				: false;

			if ( ! $ok ) {
				wp_delete_post( $post_id, true );
				continue;
			}

			// Taxonomies (creates terms by name if missing).
			if ( taxonomy_exists( 'audience' ) ) {
				$result = wp_set_object_terms( $post_id, $v['audience'], 'audience', false );
				if ( is_wp_error( $result ) ) {
					wp_delete_post( $post_id, true );
					continue;
				}
			}
			$result = wp_set_object_terms( $post_id, $v['topic'], 'video_topic', false );
			if ( is_wp_error( $result ) ) {
				wp_delete_post( $post_id, true );
				continue;
			}

			$created++;
		}

		return sprintf( 'Videos seeded: %d created, %d already present.', $created, $skipped );
	},
);

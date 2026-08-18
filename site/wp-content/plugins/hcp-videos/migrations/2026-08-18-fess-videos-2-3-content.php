<?php
/**
 * Replace the placeholder content on Allergic Rhinitis Rounds episodes 2-3
 * with the real copy, resources and Vimeo videos (CAPH0123 copy doc,
 * "Video 2 & 3 Description COPY").
 *
 * Updates in place — the placeholder posts' slugs and menu_order are canonical.
 * The "Video N of 3:" description prefix from the copy doc is dropped, matching
 * episode 1 (the card eyebrow already numbers the episodes). Related videos
 * resolve as the series run plus the manual extra: episode 2 keeps the Healthed
 * product explainer; episode 3 swaps it for the Children's Nasal Spray how-to.
 *
 * Idempotent: safe to re-run; last write wins.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Allergic Rhinitis Rounds eps 2-3: real copy, resources, related picks and Vimeo IDs.',
	'up'          => function () {
		$series_para = '<p>The Allergic Rhinitis Rounds Clinical Bites series explores three common case scenarios, offering practical expert insights to support primary care providers with allergic rhinitis patient care.</p>';

		$tattersall_bio = '<p style="font-style:italic;"><span class="speaker-label">About the speaker:</span> Dr Jessica Tattersall is an Allergist and Medical Rhinologist who began her training in otolaryngology before pursuing a career in allergic diseases. She is a Fellow of the Royal Australian College of General Practitioners, holds a Masters of Medicine in Allergic Diseases from the University of Western Sydney, and is a member of the Australasian Society of Clinical Immunology and Allergy, the Australian and New Zealand Rhinological Society, and the American Rhinological Society. She has a special interest in allergic, non-allergic and inflammatory diseases of the airway and sinuses, and is actively involved in research and education in this area.</p>';

		$taitz_bio = '<p style="font-style:italic;"><span class="speaker-label">About the speaker:</span> Dr Jonny Taitz is a Specialist General Paediatrician in private practice, caring for children with both complex and simple medical issues. He is a Senior National Examiner for the Royal Australasian College of Paediatricians, a Conjoint Senior Lecturer at the University of NSW, and has previously served as the clinical adviser to the Paediatric Patient Safety program at the NSW Clinical Excellence Commission and on the editorial board of the British Medical Journal Quality and Safety. Other previous roles include Director of Medical Services at Royal North Shore Hospital and acting Executive Director of Medical Services for Northern Sydney Local Health District. Dr Taitz has authored two books: the Australian Kids Health Book: The Essential A-Z Guide to Emergencies, Baby Care and Common Childhood Illnesses, and the New Zealand Kids Health Book: The Essential A-Z Guide to Emergencies, Baby Care and Common Childhood Illnesses.</p>';

		$episodes = array(
			'expecting-and-congested' => array(
				'vimeo'     => '1219107918',
				'content'   => '<p>Rhinitis is common during pregnancy, affecting 1 in 5 pregnant women. In this episode, Allergist and Medical Rhinologist Dr Jessica Tattersall outlines the management approaches that can be safely used throughout pregnancy, and when medication should be considered.</p>'
					. $series_para . $tattersall_bio,
				'resources' => array(
					array( 'hcp-pregnancy-factsheet', 'resources' ),
					array( 'nasal-saline-patient-leaflet', 'resources' ),
					array( 'national-asthma-council-allergic-rhinitis-treatment-chart', 'resources' ),
				),
				'related'   => array( array( 'fess-healthed-product-explainer', 'video' ) ),
			),
			'are-steroids-safe-for-my-child' => array(
				'vimeo'     => '1219108342',
				'content'   => '<p>Compliance to therapy remains a major challenge in allergic rhinitis, particularly in children. In this episode, Specialist General Paediatrician Dr Jonny Taitz unpacks why parents may have concerns about steroid use, and available options to support a child&#8217;s management plan.</p>'
					. $series_para . $taitz_bio,
				'resources' => array(
					array( 'paediatric-nasal-congestion-chart', 'resources' ),
					array( 'nasal-saline-patient-leaflet', 'resources' ),
					array( 'rethink-your-approach-to-paediatric-urtis', 'post' ),
				),
				'related'   => array( array( 'fess-how-to-use-childrens-nasal-spray', 'video' ) ),
			),
		);

		$log = array();
		foreach ( $episodes as $slug => $ep ) {
			if ( ! preg_match( '/^\d+$/', $ep['vimeo'] ) ) {
				throw new RuntimeException( "Vimeo ID for {$slug} not set — edit this migration first." );
			}

			$post = get_page_by_path( $slug, OBJECT, 'video' );
			if ( ! $post ) {
				throw new RuntimeException( "{$slug} not found — run the placeholder-eps migration first." );
			}

			$resolve = function ( $pairs ) {
				$ids = array();
				foreach ( $pairs as $pair ) {
					$p = get_page_by_path( $pair[0], OBJECT, $pair[1] );
					if ( ! $p ) {
						throw new RuntimeException( "Could not resolve {$pair[1]} '{$pair[0]}'." );
					}
					$ids[] = $p->ID;
				}
				return $ids;
			};

			wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => wp_specialchars_decode( $ep['content'] ),
			) );

			update_field( 'vimeo', $ep['vimeo'], $post->ID );
			update_field( 'video_resources', $resolve( $ep['resources'] ), $post->ID );
			update_field( 'related_videos', $resolve( $ep['related'] ), $post->ID );

			if ( function_exists( 'hcp_videos_cache_vimeo_meta' ) ) {
				hcp_videos_cache_vimeo_meta( $post->ID );
			}

			$log[] = "{$slug} updated ({$post->ID})";
		}

		return implode( '; ', $log );
	},
);

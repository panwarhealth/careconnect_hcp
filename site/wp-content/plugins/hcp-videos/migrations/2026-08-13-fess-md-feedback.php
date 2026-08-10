<?php
/**
 * CAPH0123 MD feedback (Maria, 10.08.26) — content fixes.
 *
 * - NAC chart resource title loses its "HCP – FESS" admin prefix and gains the
 *   plural: "National Asthma Council Allergic Rhinitis Treatments Chart".
 * - Healthed explainer video titled "FESS® Healthed Product Explainer".
 * - Dr Tattersall article: "Healthcare Professional" audience label on its
 *   card; ", with Dr Jessica Tattersall" dropped from the title for space.
 * - MREC ad slot on the three Allergic Rhinitis episodes — mrec-fess.gif as
 *   the placeholder until the new allergy creative is finalised.
 *
 * The hero-layout feedback (tile removed, CTA right of copy) lives in the
 * section/landing migrations themselves, patched in place where already run.
 *
 * Idempotent: every step no-ops once applied.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'FESS Clinical Bites MD feedback: titles, audience label, MREC placeholders.',
	'up'          => function () {
		$log = array();

		$retitle = function ( string $slug, string $type, string $title ) use ( &$log ) {
			$p = get_page_by_path( $slug, OBJECT, $type );
			if ( ! $p ) {
				$log[] = "{$slug}: not found";
			} elseif ( $p->post_title === $title ) {
				$log[] = "{$slug}: title already set";
			} else {
				wp_update_post( array( 'ID' => $p->ID, 'post_title' => $title ) );
				$log[] = "{$slug}: retitled";
			}
			return $p;
		};

		$retitle( 'national-asthma-council-allergic-rhinitis-treatment-chart', 'resources', 'National Asthma Council Allergic Rhinitis Treatments Chart' );
		$retitle( 'fess-healthed-product-explainer', 'video', 'FESS® Healthed Product Explainer' );

		$article = $retitle(
			'top-4-expert-tips-to-optimise-allergic-rhinitis-management-with-dr-jessica-tattersall',
			'post',
			'Top 4 expert tips to optimise allergic rhinitis management'
		);
		if ( $article && taxonomy_exists( 'audience' ) ) {
			wp_set_object_terms( $article->ID, 'Healthcare Professional', 'audience', false );
			$log[] = 'article: audience set';
		}

		$atts = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array(
				'key'     => '_wp_attached_file',
				'value'   => 'mrec-fess',
				'compare' => 'LIKE',
			) ),
		) );
		if ( ! $atts ) {
			throw new RuntimeException( 'mrec-fess not in media library yet — upload it, then re-run migrations.' );
		}
		foreach ( array( 'not-another-sinus-infection', 'expecting-and-congested', 'are-steroids-safe-for-my-child' ) as $slug ) {
			$ep = get_page_by_path( $slug, OBJECT, 'video' );
			if ( ! $ep ) {
				$log[] = "{$slug}: not found";
			} elseif ( get_field( 'ad_image', $ep->ID ) ) {
				$log[] = "{$slug}: already has an ad";
			} else {
				update_field( 'ad_image', (int) $atts[0], $ep->ID );
				$log[] = "{$slug}: MREC placeholder set";
			}
		}

		return implode( '; ', $log );
	},
);

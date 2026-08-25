<?php
/**
 * CAPH0116 — swap the MREC placeholder on the three Allergic Rhinitis Rounds
 * episodes for the final FESS allergy creative.
 *
 * 2026-08-13-fess-md-feedback set mrec-fess.gif as a stand-in; this replaces it
 * on every episode regardless of what is currently assigned.
 *
 * Image resolved by filename (LIKE) so the per-env attachment ID doesn't
 * matter. PREREQUISITE: caph0116-fess-allergy-mrec.gif must be in the media
 * library — the migration throws and stays pending until it is.
 *
 * Idempotent: no-ops once every episode points at the new creative.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'CAPH0116: final FESS allergy MREC on the Allergic Rhinitis episodes.',
	'up'          => function () {
		$atts = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array(
				'key'     => '_wp_attached_file',
				'value'   => 'caph0116-fess-allergy-mrec',
				'compare' => 'LIKE',
			) ),
		) );
		if ( ! $atts ) {
			throw new RuntimeException( 'caph0116-fess-allergy-mrec not in media library yet — upload it, then re-run migrations.' );
		}
		$mrec = (int) $atts[0];

		if ( ! get_post_meta( $mrec, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $mrec, '_wp_attachment_image_alt', 'FESS - this allergy season, use FESS first to clean and clear the nasal passage' );
		}

		$log = array();
		foreach ( array( 'not-another-sinus-infection', 'expecting-and-congested', 'are-steroids-safe-for-my-child' ) as $slug ) {
			$ep = get_page_by_path( $slug, OBJECT, 'video' );
			if ( ! $ep ) {
				$log[] = "{$slug}: not found";
				continue;
			}
			$current = get_field( 'ad_image', $ep->ID );
			$id      = is_array( $current ) ? (int) ( $current['ID'] ?? 0 ) : (int) $current;
			if ( $id === $mrec ) {
				$log[] = "{$slug}: already set";
				continue;
			}
			update_field( 'ad_image', $mrec, $ep->ID );
			$log[] = "{$slug}: MREC {$mrec}";
		}

		return implode( '; ', $log );
	},
);

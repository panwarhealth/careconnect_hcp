<?php
/**
 * Restore the series end cards as they were on Vimeo: every episode links to
 * the next, and the last episode hands over to a product video:
 *   Sick Days Clinical Bites -> How Hydralyte rehydrates faster than water alone
 *   FESS Clinical Bites      -> FESS Healthed Product Explainer
 *
 * Sets each series' `series_next_video` term field. Series and videos are
 * found through legacy Vimeo IDs, so per-environment post/term IDs don't matter.
 *
 * Idempotent: rewrites the same values.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Series end cards: last Sick Days episode -> Hydralyte MOA video; last FESS episode -> FESS Healthed explainer.',
	'up'          => function () {
		$by_vimeo = array();
		foreach ( get_posts( array( 'post_type' => 'video', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $vid ) {
			$by_vimeo[ hcp_videos_vimeo_id( get_post_meta( $vid, 'vimeo', true ) ) ] = (int) $vid;
		}

		// Any episode of the series (Vimeo ID) => the video to hand over to (Vimeo ID).
		$hand_offs = array(
			'1207250947' => '1122083239',
			'1216901419' => '1125398505',
		);

		$done = array();
		foreach ( $hand_offs as $episode => $target ) {
			if ( empty( $by_vimeo[ $episode ] ) || empty( $by_vimeo[ $target ] ) ) {
				throw new RuntimeException( "Video for Vimeo ID $episode or $target not found." );
			}
			$term = hcp_videos_series_term( $by_vimeo[ $episode ] );
			if ( ! $term ) {
				throw new RuntimeException( "Video for Vimeo ID $episode is not in a series." );
			}
			update_term_meta( $term->term_id, 'series_next_video', $by_vimeo[ $target ] );
			update_term_meta( $term->term_id, '_series_next_video', 'field_hcpvid_series_next_video' );
			$done[] = $term->name . ' -> ' . get_the_title( $by_vimeo[ $target ] );
		}

		return implode( '; ', $done );
	},
);

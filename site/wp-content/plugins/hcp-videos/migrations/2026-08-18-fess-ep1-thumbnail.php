<?php
/**
 * Set the client-designed thumbnail as episode 1's featured image.
 *
 * The card helper prefers a featured image over the cached Vimeo poster, so
 * this replaces the auto-fetched frame on every card and hero for
 * "Not another sinus infection". Resolved by filename so the per-env
 * attachment ID doesn't matter. PREREQUISITE: caph0123-fess-ep1-thumbnail.png
 * must be uploaded to the media library first — if absent this is a safe no-op.
 *
 * Idempotent: no-ops once the featured image is set.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'FESS ep 1: client-designed thumbnail as featured image.',
	'up'          => function () {
		$post = get_page_by_path( 'not-another-sinus-infection', OBJECT, 'video' );
		if ( ! $post ) {
			throw new RuntimeException( 'Episode 1 not found.' );
		}

		if ( has_post_thumbnail( $post->ID ) ) {
			return 'Episode 1 already has a featured image.';
		}

		$atts = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array( array(
				'key'     => '_wp_attached_file',
				'value'   => 'caph0123-fess-ep1-thumbnail',
				'compare' => 'LIKE',
			) ),
		) );

		if ( ! $atts ) {
			return 'Thumbnail attachment not in media library yet — upload it and re-run.';
		}

		set_post_thumbnail( $post->ID, (int) $atts[0] );

		return 'Episode 1 featured image set (attachment ' . (int) $atts[0] . ').';
	},
);

<?php
/**
 * Record what the Clinical Bites series is, on the series term itself.
 *
 * `_series_total`   — episodes the series advertises. Drives "Video 3 of 5" on
 *                     the video pages, and stays honest during a staggered
 *                     release where fewer than five are published.
 * `_series_landing` — the landing page that "Video N of M" links back to.
 *
 * Also clears the hand-curated `related_videos` picks on the episodes, and the
 * `related_autofill=0` flag that pinned them. Related videos are now computed
 * from the running order (see includes/series.php); leftover picks would show an
 * editor a list that no longer matches the page, and leaving auto-fill switched
 * off would leave the episodes with no related videos at all.
 *
 * Idempotent: re-running rewrites the same term meta and finds nothing to clear.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Set Clinical Bites series total + landing page, and clear the superseded related-video picks.',
	'up'          => function () {
		$slug = 'clinical-bites-diabetes';
		$term = get_term_by( 'slug', $slug, 'video_topic' );
		if ( ! $term ) {
			return "video_topic term '$slug' not found — nothing to do.";
		}

		update_term_meta( $term->term_id, '_series_total', 5 );

		$page = get_page_by_path( 'clinical-bites', OBJECT, 'page' );
		if ( $page ) {
			update_term_meta( $term->term_id, '_series_landing', get_permalink( $page->ID ) );
		}

		$episodes = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( array(
				'taxonomy' => 'video_topic',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			) ),
		) );

		$cleared = 0;
		foreach ( $episodes as $id ) {
			$had_picks    = '' !== get_post_meta( $id, 'related_videos', true );
			$autofill_off = '0' === get_post_meta( $id, 'related_autofill', true );

			if ( ! $had_picks && ! $autofill_off ) {
				continue;
			}

			delete_post_meta( $id, 'related_videos' );
			delete_post_meta( $id, '_related_videos' ); // ACF field-key pointer
			delete_post_meta( $id, 'related_autofill' );
			delete_post_meta( $id, '_related_autofill' );
			$cleared++;
		}

		$landing = $page ? "landing {$page->ID}" : 'no landing page found';

		return "Series '$slug': total 5, {$landing}, cleared picks on {$cleared} episode(s).";
	},
);

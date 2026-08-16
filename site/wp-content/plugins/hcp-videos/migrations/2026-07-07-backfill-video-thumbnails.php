<?php
/**
 * Backfill cached Vimeo posters for videos that never got one.
 *
 * Videos seeded via update_field() don't trigger the acf/save_post hook that
 * caches the poster in `_hcpvid_thumb`, so their cards fall back to vumbnail.com
 * (which returns a black frame for these videos). This fetches the real Vimeo
 * poster for any video that has neither a featured image nor a cached poster.
 *
 * Idempotent and non-destructive: skips videos that already have a poster.
 * Best-effort: if the server can't reach Vimeo, those videos are left as-is
 * (re-run later or set a featured image manually).
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Backfill cached Vimeo posters + durations for videos missing them.',
	'up'          => function () {
		$videos = get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$filled = 0;
		$skipped = 0;
		$failed = 0;
		foreach ( $videos as $vid ) {
			$has_poster   = has_post_thumbnail( $vid ) || get_post_meta( $vid, '_hcpvid_thumb', true );
			$has_duration = get_post_meta( $vid, '_hcpvid_duration', true ) || trim( (string) get_field( 'duration', $vid ) );
			if ( $has_poster && $has_duration ) {
				$skipped++;
				continue;
			}
			// Fetches both poster and duration in one call.
			hcp_videos_cache_vimeo_meta( (int) $vid );
			if ( get_post_meta( $vid, '_hcpvid_thumb', true ) || get_post_meta( $vid, '_hcpvid_duration', true ) ) {
				$filled++;
			} else {
				$failed++;
			}
		}

		return "posters filled: {$filled}, already had one: {$skipped}, could not fetch: {$failed}";
	},
);

<?php
/**
 * Client-review title fix missed in the round-2 pass: episode 4 reads
 * "Dehydration During Sick Days and The Role of ORS" — no colon. Flagged four
 * times in the CAPH0105 markup (07.08.26), on every surface the title shows.
 *
 * Keyed by Vimeo ID; the stored title stays prefix-free ("Ep 4:" is added at
 * display time).
 *
 * Idempotent: no-ops once the title matches.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Clinical Bites episode 4: title loses its colon per client markup.',
	'up'          => function () {
		$title = 'Dehydration During Sick Days and The Role of ORS';

		foreach ( get_posts( array(
			'post_type'      => 'video',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) as $vid ) {
			if ( hcp_videos_vimeo_id( get_field( 'vimeo', $vid ) ) !== '1213464339' ) {
				continue;
			}
			if ( get_post_field( 'post_title', $vid ) === $title ) {
				return "Episode 4 ({$vid}) already titled correctly.";
			}
			wp_update_post( array( 'ID' => $vid, 'post_title' => $title ) );
			return "Episode 4 ({$vid}) retitled.";
		}

		return 'Episode 4 not found (vimeo 1213464339).';
	},
);

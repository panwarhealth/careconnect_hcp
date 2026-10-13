<?php
/**
 * End cards: the clickable card shown over a player when its video finishes.
 *
 * Each video picks a mode on its edit screen (field `endcard_mode`):
 *   auto   — (default) derived from the series and related-video data, so a
 *            new or re-ordered episode needs no end-card setup:
 *              mid-series episode  -> the next episode
 *              last live episode   -> the series' "after the last episode"
 *                                     video if set, else its landing page
 *              standalone video    -> its first playable related video, or
 *                                     episode 1 when that video is in a series
 *            Videos with no file are never suggested.
 *   custom — heading / title / link / button text entered on the video.
 *   off    — no end card.
 *
 * Separately, the "Autoplay" tickbox (`endcard_autoplay`) plays the card's
 * link after a short countdown when it is a video on this site (see
 * assets/player.js).
 */

defined( 'ABSPATH' ) || exit;

/**
 * The end card for a video, or null when it has none.
 *
 * @return array{heading:string,title:string,url:string,label:string,thumb:string,autoplay?:bool}|null
 */
function hcp_videos_endcard( int $post_id ): ?array {
	$mode = (string) get_post_meta( $post_id, 'endcard_mode', true );

	if ( 'off' === $mode ) {
		return null;
	}

	$card = 'custom' === $mode ? hcp_videos_endcard_custom( $post_id ) : hcp_videos_endcard_auto( $post_id );
	if ( $card ) {
		$card['autoplay'] = hcp_videos_endcard_autoplay_wanted( $post_id ) && hcp_videos_is_video_url( $card['url'] );
	}
	return $card;
}

function hcp_videos_endcard_custom( int $post_id ): ?array {
	$url = esc_url_raw( (string) get_post_meta( $post_id, 'endcard_url', true ) );
	if ( '' === $url ) {
		return null;
	}
	return array(
		'heading' => (string) get_post_meta( $post_id, 'endcard_heading', true ),
		'title'   => (string) get_post_meta( $post_id, 'endcard_title', true ),
		'url'     => $url,
		'label'   => (string) get_post_meta( $post_id, 'endcard_label', true ) ?: 'Find out more',
		'thumb'   => '',
	);
}

/**
 * The video's "Autoplay" tickbox (`endcard_autoplay`). Until someone saves it,
 * series episodes default to on and standalone videos to off.
 */
function hcp_videos_endcard_autoplay_wanted( int $post_id ): bool {
	$set = get_post_meta( $post_id, 'endcard_autoplay', true );
	return '' === $set ? null !== hcp_videos_series_term( $post_id ) : (bool) (int) $set;
}

/**
 * Whether a URL is a video page on this site: only those can autoplay, since
 * autoplay swaps the next video page into the current one.
 */
function hcp_videos_is_video_url( string $url ): bool {
	$id = url_to_postid( $url );
	return $id && 'video' === get_post_type( $id );
}

/**
 * The automatic end card, which the edit screen also previews.
 */
function hcp_videos_endcard_auto( int $post_id ): ?array {
	$term = hcp_videos_series_term( $post_id );

	if ( $term ) {
		$episodes = hcp_videos_series_episode_ids( $term->term_id );
		$position = array_search( $post_id, $episodes, true );

		$next = false !== $position ? ( $episodes[ $position + 1 ] ?? 0 ) : 0;
		if ( $next && '' !== hcp_videos_src( $next ) ) {
			return hcp_videos_endcard_for_video( $next, 'Up next' );
		}

		$after = hcp_videos_series_next_video( $term->term_id );
		if ( $after && $after !== $post_id && '' !== hcp_videos_src( $after ) ) {
			return hcp_videos_endcard_for_video( $after, 'Watch next' );
		}

		$landing = hcp_videos_series_landing_url( $term->term_id );
		if ( '' === $landing ) {
			return null;
		}
		$more = hcp_videos_series_total( $term->term_id ) > count( $episodes );
		return array(
			'heading' => $more ? 'More episodes coming soon' : 'You have reached the end of the series',
			'title'   => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			'url'     => $landing,
			'label'   => 'Back to the series',
			'thumb'   => '',
		);
	}

	// A related video that is a series episode starts the viewer at episode 1.
	foreach ( hcp_videos_related_ids( $post_id, 10 ) as $related ) {
		$series = hcp_videos_series_term( (int) $related );
		if ( $series ) {
			$related = hcp_videos_series_episode_ids( $series->term_id )[0] ?? $related;
		}
		if ( (int) $related !== $post_id && '' !== hcp_videos_src( (int) $related ) ) {
			return hcp_videos_endcard_for_video( (int) $related, 'Watch next' );
		}
	}
	return null;
}

function hcp_videos_endcard_for_video( int $video_id, string $heading ): array {
	return array(
		'heading' => $heading,
		'title'   => html_entity_decode( hcp_videos_episode_title( $video_id ), ENT_QUOTES, 'UTF-8' ),
		'url'     => (string) get_permalink( $video_id ),
		'label'   => 'Watch now',
		'thumb'   => hcp_videos_thumb_url( $video_id, 'medium' ),
	);
}

function hcp_videos_enqueue_player_script(): void {
	wp_enqueue_script(
		'hcp-videos-player',
		plugins_url( 'assets/player.js', HCP_VIDEOS_FILE ),
		array(),
		(string) filemtime( HCP_VIDEOS_DIR . 'assets/player.js' ),
		true
	);
}

<?php
/**
 * Shared helpers used by the shortcode and the single-video template.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extract the numeric Vimeo ID from a stored value that may be a full URL,
 * a player URL, or a bare ID.
 */
function hcp_videos_vimeo_id( $raw ): string {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) {
		return '';
	}
	if ( ctype_digit( $raw ) ) {
		return $raw;
	}
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $raw, $m ) ) {
		return $m[1];
	}
	// Fall back to the first run of digits if present.
	return preg_match( '~(\d{6,})~', $raw, $m ) ? $m[1] : '';
}

/**
 * Build the Vimeo player embed URL for an ID.
 */
function hcp_videos_player_url( string $id ): string {
	return $id ? 'https://player.vimeo.com/video/' . $id : '';
}

/**
 * The play-icon overlay. Centered in its (position:relative) parent via a flex
 * wrapper so the hover-scale doesn't fight the centering transform. Styling +
 * hover are in hcp_videos_play_styles().
 */
function hcp_videos_play_icon(): string {
	return '<span class="hcp-video-play" aria-hidden="true"><svg viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
		<circle cx="25" cy="25" r="25" fill="white" fill-opacity="0.92"/>
		<path d="M37.4331 24.1265C38.1172 24.5079 38.1172 25.4921 37.4331 25.8735L18.7369 36.2955C18.0703 36.6671 17.25 36.1852 17.25 35.422L17.25 14.578C17.25 13.8148 18.0703 13.3329 18.7369 13.7045L37.4331 24.1265Z" fill="#35B1C9"/>
	</svg></span>';
}

/**
 * Inline styles for the play overlay (centering + hover grow). Output once.
 */
function hcp_videos_play_styles(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	echo '<style id="hcp-video-play-css">
		.hcp-video-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:5;}
		.hcp-video-play svg{width:56px;height:56px;filter:drop-shadow(0 2px 6px rgba(0,0,0,.25));transition:transform .18s ease;}
		a:hover .hcp-video-play svg{transform:scale(1.12);}
		.hcp-video-duration{position:absolute;bottom:8px;right:8px;z-index:6;background:rgba(0,0,0,.8);color:#fff;font-size:.72rem;font-weight:600;line-height:1;padding:3px 6px;border-radius:4px;letter-spacing:.02em;}
		.hcp-related-scroll .hcp-video-play svg{width:34px;height:34px;}
		.hcp-related-scroll .hcp-video-duration{bottom:4px;right:4px;font-size:.62rem;padding:2px 4px;}
		@media(min-width:1024px){.hcp-related-scroll{max-height:640px;overflow-y:auto;padding-right:10px;}}
	</style>';
}
add_action( 'wp_head', 'hcp_videos_play_styles' );

/**
 * Format a number of seconds as m:ss (or h:mm:ss).
 */
function hcp_videos_format_duration( int $secs ): string {
	if ( $secs <= 0 ) {
		return '';
	}
	$h = intdiv( $secs, 3600 );
	$m = intdiv( $secs % 3600, 60 );
	$s = $secs % 60;
	return $h > 0
		? sprintf( '%d:%02d:%02d', $h, $m, $s )
		: sprintf( '%d:%02d', $m, $s );
}

/**
 * Display duration for a video: the manual ACF field if set, else the value
 * auto-fetched from Vimeo and cached in `_hcpvid_duration` post meta (populated
 * on save via hcp_videos_cache_duration). Never fetches on display.
 */
function hcp_videos_duration( int $post_id ): string {
	$manual = trim( (string) get_field( 'duration', $post_id ) );
	if ( $manual !== '' ) {
		return $manual;
	}
	return (string) get_post_meta( $post_id, '_hcpvid_duration', true );
}

/**
 * Best-effort fetch of a video's duration from the Vimeo oEmbed endpoint
 * (no auth) and cache it. Returns the formatted string, or '' on failure.
 * Called on save and from the one-time backfill — not on display.
 */
function hcp_videos_cache_duration( int $post_id ): string {
	$id = hcp_videos_vimeo_id( get_field( 'vimeo', $post_id ) );
	if ( ! $id ) {
		return '';
	}
	$resp = wp_remote_get( 'https://vimeo.com/api/oembed.json?url=https://vimeo.com/' . $id, array( 'timeout' => 6 ) );
	if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) {
		return '';
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	$secs = isset( $data['duration'] ) ? (int) $data['duration'] : 0;
	$formatted = hcp_videos_format_duration( $secs );
	if ( $formatted !== '' ) {
		update_post_meta( $post_id, '_hcpvid_duration', $formatted );
	}
	return $formatted;
}

// Refresh the cached Vimeo duration whenever a video is saved (no manual override).
add_action( 'acf/save_post', function ( $post_id ) {
	if ( get_post_type( $post_id ) === 'video' ) {
		hcp_videos_cache_duration( (int) $post_id );
	}
}, 20 );

/**
 * Small duration badge for a thumbnail corner (YouTube-style).
 */
function hcp_videos_duration_badge( int $post_id ): string {
	$d = hcp_videos_duration( $post_id );
	if ( $d === '' ) {
		return '';
	}
	return '<span class="hcp-video-duration">' . esc_html( $d ) . '</span>';
}

/**
 * Thumbnail URL for a video: the featured image if set, else the Vimeo
 * thumbnail via vumbnail.com (the convention already used on this site).
 */
function hcp_videos_thumb_url( int $post_id, string $size = 'medium' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		if ( $url ) {
			return $url;
		}
	}
	$id = hcp_videos_vimeo_id( get_field( 'vimeo', $post_id ) );
	return $id ? 'https://vumbnail.com/' . $id . '.jpg' : '';
}

/**
 * The audience term names for a video, comma-joined (card label).
 */
function hcp_videos_audience_label( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'audience' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	return join( ', ', wp_list_pluck( $terms, 'name' ) );
}

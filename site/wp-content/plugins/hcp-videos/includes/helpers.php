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
	</style>';
}
add_action( 'wp_head', 'hcp_videos_play_styles' );

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

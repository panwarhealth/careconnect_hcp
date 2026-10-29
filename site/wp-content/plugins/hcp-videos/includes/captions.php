<?php
/**
 * Captions: WebVTT text stored on the video post (`_hcpvid_captions`) and
 * served from this site, so the <track> is same-origin and the <video> needs
 * no CORS mode (which would make every MP4 request depend on the CDN's CORS).
 */

defined( 'ABSPATH' ) || exit;

const HCP_VIDEOS_CAPTIONS_META = '_hcpvid_captions';

function hcp_videos_captions_url( int $post_id ): string {
	$vtt = (string) get_post_meta( $post_id, HCP_VIDEOS_CAPTIONS_META, true );
	if ( '' === $vtt ) {
		return '';
	}
	// Version by content so an edited track is never served from a stale cache.
	return add_query_arg( 'v', substr( md5( $vtt ), 0, 8 ), rest_url( 'hcp-videos/v1/captions/' . $post_id ) );
}

/**
 * Whether text is usable WebVTT. Kept strict: it is served as-is to browsers.
 */
function hcp_videos_is_vtt( string $text ): bool {
	return 1 === preg_match( '/^(\xEF\xBB\xBF)?WEBVTT[ \t\r\n]/', $text );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'hcp-videos/v1', '/captions/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $request ) {
			$post = get_post( (int) $request['id'] );
			if ( ! $post || 'video' !== $post->post_type
				|| ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) ) {
				return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
			}
			$vtt = (string) get_post_meta( $post->ID, HCP_VIDEOS_CAPTIONS_META, true );
			if ( '' === $vtt ) {
				return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
			}
			header( 'Content-Type: text/vtt; charset=utf-8' );
			header( 'Cache-Control: public, max-age=86400' );
			echo $vtt; // phpcs:ignore WordPress.Security.EscapeOutput -- validated WebVTT, served as text/vtt.
			exit;
		},
	) );
} );

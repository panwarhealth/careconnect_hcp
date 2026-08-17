<?php
/**
 * GA4 play tracking for every Vimeo embed on the site.
 *
 * GA4's built-in video tracking only covers YouTube, so a sitewide script
 * scans each page for Vimeo iframes and forwards player events to GA4 using
 * the enhanced-measurement schema (video_start / video_progress /
 * video_complete). Video titles come from the Vimeo API at runtime, so new
 * embeds — video pages, article embeds, future content — are tracked with no
 * per-video wiring.
 *
 * The script is a few hundred bytes; the Vimeo SDK and gtag are only loaded
 * by it after it finds at least one Vimeo iframe. Delivery matches
 * hcp-popups: gtag configured with send_page_view disabled, events addressed
 * with send_to, so pageviews are never inflated alongside the GTM container.
 */

defined( 'ABSPATH' ) || exit;

const HCP_VIDEOS_GA4_MEASUREMENT_ID = 'G-ZM1QH0ZTGW';

function hcp_videos_ga4_measurement_id(): string {
	/**
	 * Override the GA4 measurement ID video events are sent to.
	 *
	 * @param string $id Empty string disables tracking.
	 */
	return (string) apply_filters( 'hcp_videos_ga4_measurement_id', HCP_VIDEOS_GA4_MEASUREMENT_ID );
}

add_action( 'wp_enqueue_scripts', 'hcp_videos_enqueue_analytics', 20 );
function hcp_videos_enqueue_analytics(): void {
	if ( is_admin() ) {
		return;
	}

	$id = hcp_videos_ga4_measurement_id();
	if ( '' === $id ) {
		return;
	}

	wp_enqueue_script(
		'hcp-videos-analytics',
		plugins_url( 'assets/video-analytics.js', HCP_VIDEOS_FILE ),
		array(),
		(string) filemtime( HCP_VIDEOS_DIR . 'assets/video-analytics.js' ),
		true
	);
	wp_localize_script( 'hcp-videos-analytics', 'hcpVideoAnalytics', array( 'measurementId' => $id ) );
}

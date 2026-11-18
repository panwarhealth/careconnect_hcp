<?php
/**
 * GA4 play tracking for every video player on the site.
 *
 * GA4's built-in video tracking only covers YouTube, so a sitewide script
 * listens for events from every <video data-hcp-video> and forwards them to
 * GA4 using the enhanced-measurement schema (video_start / video_progress /
 * video_complete). Titles come from the data-hcp-video attribute, so new
 * embeds — video pages, [hcp_video] in articles, future content — are tracked
 * with no per-video wiring.
 *
 * The script is a few hundred bytes. It reuses gtag when hcp-analytics has it
 * on the page; otherwise it loads gtag on first play, configured with
 * send_page_view disabled. Events are addressed with send_to, so page views
 * are never inflated. The measurement ID comes from hcp-analytics.
 */

defined( 'ABSPATH' ) || exit;

function hcp_videos_ga4_measurement_id(): string {
	/**
	 * Override the GA4 measurement ID video events are sent to.
	 *
	 * @param string $id Empty string disables tracking.
	 */
	return (string) apply_filters( 'hcp_videos_ga4_measurement_id', (string) apply_filters( 'hcp_ga4_measurement_id', '' ) );
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
	wp_localize_script(
		'hcp-videos-analytics',
		'hcpVideoAnalytics',
		array(
			'measurementId' => $id,
			'gtagOnPage'    => (bool) apply_filters( 'hcp_ga4_gtag_on_page', false ),
		)
	);
}

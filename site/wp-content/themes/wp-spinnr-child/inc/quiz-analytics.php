<?php
/**
 * GA4 events for interactive article quizzes (first: Guess the Glucose, #ggq).
 *
 * The quiz itself runs inline in post_content and records nothing; this loads
 * a separate listener only on posts that contain a quiz, so the post's inline
 * script is left untouched. Delivery matches hcp-videos: gtag configured with
 * send_page_view disabled and events addressed with send_to.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'hcp_quiz_enqueue_analytics', 20 );
function hcp_quiz_enqueue_analytics(): void {
	if ( is_admin() || ! is_singular( 'post' ) ) {
		return;
	}

	$post = get_post();
	if ( ! $post || false === strpos( $post->post_content, 'id="ggq"' ) ) {
		return;
	}

	$id = function_exists( 'hcp_videos_ga4_measurement_id' ) ? hcp_videos_ga4_measurement_id() : 'G-ZM1QH0ZTGW';
	if ( '' === $id ) {
		return;
	}

	$file = '/js/quiz-analytics.js';
	wp_enqueue_script(
		'hcp-quiz-analytics',
		get_stylesheet_directory_uri() . $file,
		array(),
		(string) filemtime( get_stylesheet_directory() . $file ),
		true
	);
	wp_localize_script(
		'hcp-quiz-analytics',
		'hcpQuizAnalytics',
		array(
			'measurementId' => $id,
			'quiz'          => $post->post_name,
		)
	);
}

<?php
/**
 * Puts GA4 and Microsoft Clarity on the page.
 *
 * GA4 is loaded once, first thing in <head>, with the page context as config
 * parameters, which GA4 sends with every event on the page, so page views and
 * every other plugin's events (pop-ups, videos, quiz) carry it. (gtag 'set'
 * does not reach GA4 events.) Those plugins reuse this gtag when it is
 * present. The page view itself comes from the config call.
 *
 * Clarity gets the same context as tags. Pages that show personal details
 * (account, sample-order and CPD sections) are masked in full; form fields are
 * masked everywhere by Clarity's default.
 */

defined( 'ABSPATH' ) || exit;

const HCP_ANALYTICS_MASKED_SECTIONS = array( 'account', 'samples', 'cpd' );

add_action( 'wp_head', 'hcp_analytics_head', 1 );
function hcp_analytics_head(): void {
	if ( is_admin() || ! hcp_analytics_is_active() ) {
		return;
	}

	$id      = hcp_analytics_measurement_id();
	$context = hcp_analytics_context();
	$config  = hcp_analytics_is_preview() ? $context + array( 'debug_mode' => true ) : $context;
	$masked  = in_array( $context['content_section'], HCP_ANALYTICS_MASKED_SECTIONS, true );

	printf( '<script async src="%s"></script>' . "\n", esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ) ) );
	echo '<script>' . "\n"
		. 'window.dataLayer = window.dataLayer || [];' . "\n"
		. 'window.gtag = window.gtag || function(){dataLayer.push(arguments);};' . "\n"
		. "gtag('js', new Date());\n"
		. "gtag('config', " . wp_json_encode( $id ) . ', ' . wp_json_encode( $config ) . ");\n"
		. ( $masked ? "document.documentElement.setAttribute('data-clarity-mask', 'true');\n" : '' )
		. hcp_analytics_clarity_js( $context )
		. "</script>\n";
}

/**
 * Microsoft's Clarity loader, followed by the page context as Clarity tags.
 */
function hcp_analytics_clarity_js( array $context ): string {
	$js = '(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};'
		. 't=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;'
		. 'y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);'
		. '})(window, document, "clarity", "script", ' . wp_json_encode( HCP_ANALYTICS_CLARITY_ID ) . ");\n";

	foreach ( $context as $key => $value ) {
		$js .= 'clarity("set", ' . wp_json_encode( $key ) . ', ' . wp_json_encode( $value ) . ");\n";
	}

	return $js;
}

add_action( 'wp_enqueue_scripts', 'hcp_analytics_enqueue_events' );
function hcp_analytics_enqueue_events(): void {
	if ( ! hcp_analytics_is_active() ) {
		return;
	}

	$file = 'assets/events.js';
	wp_enqueue_script( 'hcp-analytics-events', plugins_url( $file, HCP_ANALYTICS_FILE ), array(), (string) filemtime( HCP_ANALYTICS_DIR . $file ), true );
	wp_localize_script(
		'hcp-analytics-events',
		'hcpAnalytics',
		array(
			'measurementId' => hcp_analytics_measurement_id(),
			'article'       => is_singular( 'post' ),
			'pdfUrl'        => is_user_logged_in() && ! hcp_analytics_is_staff( get_current_user_id() ) ? rest_url( 'hcp-analytics/v1/pdf' ) : '',
			'nonce'         => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		)
	);
}

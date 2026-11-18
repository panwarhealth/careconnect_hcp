<?php
/**
 * GA4 delivery.
 *
 * The measurement ID comes from hcp-analytics (hcp_ga4_measurement_id). When
 * hcp-analytics already has gtag on the page, it is reused; otherwise gtag is
 * loaded here with send_page_view disabled, and every event is addressed with
 * send_to, so pop-ups can never add page views.
 */

defined( 'ABSPATH' ) || exit;

function hcp_popups_ga4_measurement_id(): string {
	/**
	 * Override the GA4 measurement ID pop-up events are sent to.
	 *
	 * @param string $id
	 */
	return (string) apply_filters( 'hcp_popups_ga4_measurement_id', (string) apply_filters( 'hcp_ga4_measurement_id', '' ) );
}

/**
 * Loads gtag.js. Only called when a pop-up is actually being shown.
 *
 * @return string Script handle to depend on, or '' when GA4 is off or gtag is already on the page.
 */
function hcp_popups_enqueue_ga4(): string {
	$id = hcp_popups_ga4_measurement_id();
	if ( '' === $id || apply_filters( 'hcp_ga4_gtag_on_page', false ) ) {
		return '';
	}

	wp_enqueue_script( 'hcp-popups-gtag', 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ), array(), null, true );
	wp_add_inline_script(
		'hcp-popups-gtag',
		'window.dataLayer = window.dataLayer || [];'
		. 'window.gtag = window.gtag || function(){dataLayer.push(arguments);};'
		. "gtag('js', new Date());"
		. 'gtag(' . wp_json_encode( 'config' ) . ', ' . wp_json_encode( $id ) . ', {send_page_view: false});',
		'after'
	);

	return 'hcp-popups-gtag';
}

<?php
/**
 * GA4 delivery.
 *
 * The site's GA4 arrives through a GTM container we do not control, so pop-up
 * events are sent to GA4 directly instead of via a dataLayer tag someone else
 * would have to configure. Same approach as the PDF redirect template.
 *
 * The loader is configured with send_page_view disabled and every event is
 * addressed with send_to, so this cannot inflate pageviews alongside whatever
 * the container is already doing.
 */

defined( 'ABSPATH' ) || exit;

const HCP_POPUPS_GA4_MEASUREMENT_ID = 'G-ZM1QH0ZTGW';

function hcp_popups_ga4_measurement_id(): string {
	/**
	 * Override the GA4 measurement ID pop-up events are sent to.
	 *
	 * @param string $id
	 */
	return (string) apply_filters( 'hcp_popups_ga4_measurement_id', HCP_POPUPS_GA4_MEASUREMENT_ID );
}

/**
 * Loads gtag.js. Only called when a pop-up is actually being shown.
 *
 * @return string Script handle to depend on, or '' when GA4 is switched off.
 */
function hcp_popups_enqueue_ga4(): string {
	$id = hcp_popups_ga4_measurement_id();
	if ( '' === $id ) {
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

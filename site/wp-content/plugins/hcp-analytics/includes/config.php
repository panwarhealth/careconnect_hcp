<?php
/**
 * Settings and the on / preview / live switch.
 *
 * Mode is a single option so go-live and rollback are one command:
 *   wp option update hcp_analytics_mode live|preview|off
 * "preview" turns everything on only for administrators and for browsers that
 * opened any page with ?hcp_analytics_preview=1 (=0 switches it off again), so
 * the new tracking can be checked on production while HCPs see no change.
 */

defined( 'ABSPATH' ) || exit;

const HCP_ANALYTICS_GA4_ID          = 'G-ZM1QH0ZTGW';
const HCP_ANALYTICS_CLARITY_ID      = 'zfe87ouule';
const HCP_ANALYTICS_MODE_OPTION     = 'hcp_analytics_mode';
const HCP_ANALYTICS_MODE_OFF        = 'off';
const HCP_ANALYTICS_MODE_PREVIEW    = 'preview';
const HCP_ANALYTICS_MODE_LIVE       = 'live';
const HCP_ANALYTICS_PREVIEW_COOKIE  = 'hcp_analytics_preview';

/**
 * The site's one GA4 measurement ID. Other plugins read it through the
 * hcp_ga4_measurement_id filter instead of keeping their own copy.
 */
function hcp_analytics_measurement_id(): string {
	return HCP_ANALYTICS_GA4_ID;
}
add_filter( 'hcp_ga4_measurement_id', 'hcp_analytics_measurement_id' );

/**
 * Tells other plugins gtag is already on the page (loaded in <head> by this
 * plugin), so they reuse it instead of loading and configuring their own.
 */
add_filter( 'hcp_ga4_gtag_on_page', 'hcp_analytics_is_active' );

function hcp_analytics_mode(): string {
	$mode = (string) get_option( HCP_ANALYTICS_MODE_OPTION, HCP_ANALYTICS_MODE_OFF );

	return in_array( $mode, array( HCP_ANALYTICS_MODE_PREVIEW, HCP_ANALYTICS_MODE_LIVE ), true ) ? $mode : HCP_ANALYTICS_MODE_OFF;
}

function hcp_analytics_is_previewer(): bool {
	return current_user_can( 'manage_options' ) || '1' === ( $_COOKIE[ HCP_ANALYTICS_PREVIEW_COOKIE ] ?? '' );
}

/**
 * Whether the new tracking runs for this request.
 */
function hcp_analytics_is_active(): bool {
	$mode = hcp_analytics_mode();

	return HCP_ANALYTICS_MODE_LIVE === $mode || ( HCP_ANALYTICS_MODE_PREVIEW === $mode && hcp_analytics_is_previewer() );
}

function hcp_analytics_is_preview(): bool {
	return HCP_ANALYTICS_MODE_PREVIEW === hcp_analytics_mode();
}

add_action( 'init', 'hcp_analytics_preview_toggle' );
function hcp_analytics_preview_toggle(): void {
	if ( ! isset( $_GET[ HCP_ANALYTICS_PREVIEW_COOKIE ] ) ) {
		return;
	}

	$on = '1' === $_GET[ HCP_ANALYTICS_PREVIEW_COOKIE ];
	setcookie( HCP_ANALYTICS_PREVIEW_COOKIE, $on ? '1' : '', $on ? 0 : time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ HCP_ANALYTICS_PREVIEW_COOKIE ] = $on ? '1' : '';
}

/**
 * Staff accounts are tracked as staff in GA4 and never written to the HCP
 * engagement record.
 */
function hcp_analytics_is_staff( int $user_id ): bool {
	$user = get_userdata( $user_id );

	return $user && ( user_can( $user, 'edit_posts' ) || in_array( 'hcp_mca_reviewer', (array) $user->roles, true ) );
}

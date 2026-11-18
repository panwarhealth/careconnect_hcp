<?php
/**
 * Which campaign brought this visit.
 *
 * When a page is opened from a tagged link (eDM, QR code, partner site), its
 * utm_source / utm_medium / utm_campaign are kept in a cookie for the rest of
 * the browser session, so they still apply after the HCP logs in or signs up
 * further along. A later tagged link replaces them. New accounts keep the
 * source they signed up from; engagement rows record the source of the visit.
 */

defined( 'ABSPATH' ) || exit;

const HCP_ANALYTICS_SOURCE_COOKIE = 'hcp_visit_src';
const HCP_ANALYTICS_SOURCE_KEYS   = array( 'utm_source', 'utm_medium', 'utm_campaign' );
const HCP_ANALYTICS_SIGNUP_META   = 'hcp_signup_';

/**
 * @param array<string, mixed> $raw
 * @return array<string, string> The three utm values, cleaned; all '' when none.
 */
function hcp_analytics_clean_source( array $raw ): array {
	$source = array();
	foreach ( HCP_ANALYTICS_SOURCE_KEYS as $key ) {
		$source[ $key ] = substr( sanitize_text_field( wp_unslash( (string) ( $raw[ $key ] ?? '' ) ) ), 0, 100 );
	}

	return $source;
}

function hcp_analytics_has_source( array $source ): bool {
	return '' !== $source['utm_source'] || '' !== $source['utm_campaign'];
}

/**
 * The visit's source: this request's utm tags, else the ones remembered for
 * the session.
 *
 * @return array<string, string>
 */
function hcp_analytics_visit_source(): array {
	$from_url = hcp_analytics_clean_source( $_GET );
	if ( hcp_analytics_has_source( $from_url ) ) {
		return $from_url;
	}
	parse_str( (string) ( $_COOKIE[ HCP_ANALYTICS_SOURCE_COOKIE ] ?? '' ), $remembered );

	return hcp_analytics_clean_source( $remembered );
}

add_action( 'init', 'hcp_analytics_remember_source' );
function hcp_analytics_remember_source(): void {
	if ( ! hcp_analytics_is_active() ) {
		return;
	}
	$source = hcp_analytics_clean_source( $_GET );
	if ( ! hcp_analytics_has_source( $source ) ) {
		return;
	}

	$value = http_build_query( $source );
	setcookie( HCP_ANALYTICS_SOURCE_COOKIE, $value, 0, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ HCP_ANALYTICS_SOURCE_COOKIE ] = $value;
}

add_action( 'user_register', 'hcp_analytics_save_signup_source' );
function hcp_analytics_save_signup_source( int $user_id ): void {
	if ( ! hcp_analytics_is_active() || ( is_admin() && ! wp_doing_ajax() ) ) {
		return;
	}
	foreach ( hcp_analytics_visit_source() as $key => $value ) {
		update_user_meta( $user_id, HCP_ANALYTICS_SIGNUP_META . $key, $value );
	}
}

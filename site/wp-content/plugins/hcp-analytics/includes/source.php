<?php
/**
 * Which campaign brought this visit.
 *
 * When a page is opened from a tagged link (eDM, QR code, partner site), its
 * utm_source / utm_medium / utm_campaign are kept in a cookie while the visit
 * lasts (30 minutes after the last page, the same rule as a GA4 session), so
 * they still apply after the HCP logs in or signs up further along. A later
 * tagged link replaces them. New accounts keep the
 * source they signed up from; engagement rows record the source of the visit.
 */

defined( 'ABSPATH' ) || exit;

const HCP_ANALYTICS_SOURCE_COOKIE = 'hcp_visit_src';
const HCP_ANALYTICS_SOURCE_KEYS   = array( 'utm_source', 'utm_medium', 'utm_campaign' );
const HCP_ANALYTICS_SIGNUP_META   = 'hcp_signup_';
const HCP_ANALYTICS_SOURCE_TTL    = 30 * MINUTE_IN_SECONDS;

/**
 * @param array<string, mixed> $raw
 * @return array<string, string> The three utm values, cleaned; all '' when none.
 */
function hcp_analytics_clean_source( array $raw ): array {
	$source = array();
	foreach ( HCP_ANALYTICS_SOURCE_KEYS as $key ) {
		$value          = $raw[ $key ] ?? '';
		$source[ $key ] = is_scalar( $value ) ? substr( sanitize_text_field( wp_unslash( (string) $value ) ), 0, 100 ) : '';
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
	$source = hcp_analytics_visit_source();
	if ( ! hcp_analytics_has_source( $source ) ) {
		return;
	}

	// Set or extend: each page the visit reaches restarts the 30 minutes.
	$value = http_build_query( $source );
	setcookie( HCP_ANALYTICS_SOURCE_COOKIE, $value, time() + HCP_ANALYTICS_SOURCE_TTL, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ HCP_ANALYTICS_SOURCE_COOKIE ] = $value;
}

/**
 * Whether the account being created comes from a visitor signing up, not from
 * wp-admin, a script or a scheduled job.
 */
function hcp_analytics_is_visitor_signup(): bool {
	return hcp_analytics_is_active() && ! ( is_admin() && ! wp_doing_ajax() ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! wp_doing_cron();
}

add_action( 'user_register', 'hcp_analytics_save_signup_source' );
function hcp_analytics_save_signup_source( int $user_id ): void {
	if ( ! hcp_analytics_is_visitor_signup() ) {
		return;
	}
	foreach ( hcp_analytics_visit_source() as $key => $value ) {
		update_user_meta( $user_id, HCP_ANALYTICS_SIGNUP_META . $key, $value );
	}
}

<?php
/**
 * What a visitor did before they signed up.
 *
 * While a visitor is not logged in, a first-party cookie keeps their first
 * visit, the page they landed on, the first campaign that brought them and
 * the last few pages they viewed. When they register, that journey is saved to
 * the new account, so reports can say which pages and campaigns turn visitors
 * into sign-ups. The cookie is cleared once it has been saved.
 */

defined( 'ABSPATH' ) || exit;

const HCP_ANALYTICS_JOURNEY_COOKIE = 'hcp_journey';
const HCP_ANALYTICS_JOURNEY_TTL    = 30 * DAY_IN_SECONDS;
const HCP_ANALYTICS_JOURNEY_PAGES  = 10;
const HCP_ANALYTICS_JOURNEY_PATH   = 150;

function hcp_analytics_clean_path( $path ): string {
	return is_string( $path ) ? substr( sanitize_text_field( $path ), 0, HCP_ANALYTICS_JOURNEY_PATH ) : '';
}

/**
 * The journey so far, read from the cookie and cleaned (the cookie is
 * visitor-controlled).
 *
 * @return array{first_visit: string, landing_page: string, first_source: array<string, string>, pages: string[]}
 */
function hcp_analytics_journey(): array {
	$raw = json_decode( wp_unslash( (string) ( $_COOKIE[ HCP_ANALYTICS_JOURNEY_COOKIE ] ?? '' ) ), true );
	$raw = is_array( $raw ) ? $raw : array();

	$pages = array_filter( array_map( 'hcp_analytics_clean_path', is_array( $raw['pages'] ?? null ) ? $raw['pages'] : array() ) );

	return array(
		'first_visit'  => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ( $raw['first_visit'] ?? '' ) ) ? $raw['first_visit'] : '',
		'landing_page' => hcp_analytics_clean_path( $raw['landing_page'] ?? '' ),
		'first_source' => hcp_analytics_clean_source( is_array( $raw['first_source'] ?? null ) ? $raw['first_source'] : array() ),
		'pages'        => array_slice( array_values( $pages ), -HCP_ANALYTICS_JOURNEY_PAGES ),
	);
}

function hcp_analytics_set_journey_cookie( string $value, int $expires ): void {
	setcookie( HCP_ANALYTICS_JOURNEY_COOKIE, $value, $expires, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ HCP_ANALYTICS_JOURNEY_COOKIE ] = $value;
}

// Late, so redirects (which exit) run first and only pages actually shown count.
add_action( 'template_redirect', 'hcp_analytics_journey_track', 99 );
function hcp_analytics_journey_track(): void {
	if ( is_user_logged_in() || is_404() || is_feed() || ! hcp_analytics_is_active() ) {
		return;
	}

	$journey = hcp_analytics_journey();
	$path    = hcp_analytics_clean_path( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ) );

	if ( '' === $journey['first_visit'] ) {
		$journey['first_visit']  = current_time( 'mysql' );
		$journey['landing_page'] = $path;
	}
	$source = hcp_analytics_visit_source();
	if ( ! hcp_analytics_has_source( $journey['first_source'] ) && hcp_analytics_has_source( $source ) ) {
		$journey['first_source'] = $source;
	}
	if ( end( $journey['pages'] ) !== $path ) {
		$journey['pages'][] = $path;
		$journey['pages']   = array_slice( $journey['pages'], -HCP_ANALYTICS_JOURNEY_PAGES );
	}

	hcp_analytics_set_journey_cookie( (string) wp_json_encode( $journey ), time() + HCP_ANALYTICS_JOURNEY_TTL );
}

// Runs where the sign-up source is saved (source.php), with the same guard.
add_action( 'user_register', 'hcp_analytics_save_signup_journey' );
function hcp_analytics_save_signup_journey( int $user_id ): void {
	if ( ! hcp_analytics_is_visitor_signup() ) {
		return;
	}
	$journey = hcp_analytics_journey();
	if ( '' === $journey['first_visit'] ) {
		return;
	}

	update_user_meta( $user_id, HCP_ANALYTICS_SIGNUP_META . 'first_visit', $journey['first_visit'] );
	update_user_meta( $user_id, HCP_ANALYTICS_SIGNUP_META . 'landing_page', $journey['landing_page'] );
	foreach ( $journey['first_source'] as $key => $value ) {
		update_user_meta( $user_id, HCP_ANALYTICS_SIGNUP_META . 'first_' . $key, $value );
	}
	update_user_meta( $user_id, HCP_ANALYTICS_SIGNUP_META . 'pages', wp_json_encode( $journey['pages'] ) );

	hcp_analytics_set_journey_cookie( '', time() - HOUR_IN_SECONDS );
}

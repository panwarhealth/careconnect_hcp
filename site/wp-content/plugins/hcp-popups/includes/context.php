<?php
/**
 * Where pop-ups may appear.
 *
 * Content pages only: the home page, blog index and articles, video pages,
 * resources, hubs, brand and product pages, campaign landing pages. Not the
 * operational pages where someone is mid-task rather than reading, and not the
 * MCA itself, since there is no point promoting the page they are on.
 */

defined( 'ABSPATH' ) || exit;

/** Path prefixes that are functional rather than content. */
const HCP_POPUPS_EXCLUDED_PATHS = array(
	'/register',
	'/log-in',
	'/login',
	'/my-account',
	'/account',
	'/edit-your-profile',
	'/profile',
	'/dashboard',
	'/order-samples',
	'/order-patient-demo',
	'/product-demo',
	'/contact',
	'/search',
	'/cart',
	'/checkout',
	'/privacy-policy',
	'/terms',
);

/** The MCA activity: both halves of the redirect pair, plus its courseware. */
const HCP_POPUPS_MCA_POST_IDS = array( 111281, 108753, 95553, 111793 );

const HCP_POPUPS_LEARNDASH_TYPES = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' );

function hcp_popups_current_path(): string {
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );

	return is_string( $path ) ? $path : '/';
}

function hcp_popups_context_allows(): bool {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_404() || is_robots() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( is_search() || ! empty( $_GET['s'] ) ) {
		return false;
	}

	// Courseware is operational: lessons, quizzes and the audit form itself.
	if ( is_singular( HCP_POPUPS_LEARNDASH_TYPES ) ) {
		return false;
	}

	if ( is_singular() && in_array( (int) get_queried_object_id(), HCP_POPUPS_MCA_POST_IDS, true ) ) {
		return false;
	}

	// untrailingslashit('/') is '', which is the home page and always allowed.
	$path = strtolower( untrailingslashit( hcp_popups_current_path() ) );
	if ( '' !== $path ) {
		foreach ( HCP_POPUPS_EXCLUDED_PATHS as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return false;
			}
		}
	}

	/**
	 * Allow a page to opt out without a code change.
	 *
	 * @param bool $allowed
	 * @param string $path
	 */
	return (bool) apply_filters( 'hcp_popups_context_allows', true, $path );
}

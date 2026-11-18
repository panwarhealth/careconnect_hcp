<?php
/**
 * What GA4 and Clarity are told about the current page and visitor.
 *
 * Nothing here identifies a person: the visitor is only "hcp", "staff" or
 * "no" (not logged in), and their profession is the three-letter AHPRA
 * profession code (MED, NMW, PHA ...), never the number itself.
 */

defined( 'ABSPATH' ) || exit;

const HCP_ANALYTICS_NONE = 'none';

/**
 * Site sections for pages, by page path. A path matches its own entry and
 * anything below it (allergy-analyser covers allergy-analyser/fess-for-allergies).
 * Posts, resources, videos, courses and brands get their section from their type.
 */
function hcp_analytics_page_sections(): array {
	$sections = array(
		'home'      => array( 'home' ),
		'account'   => array( 'log-in', 'register', 'welcome', 'welcome-pharmacist', 'edit-your-profile', 'forgot-password', 'consent-preferences', 'no-access', 'login-customizer' ),
		'cpd'       => array( 'clinical-audit', 'anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage', 'anal-fissures-breaking-the-cycle-and-the-stigma-landing', 'pre-learning-survey', 'certificate' ),
		'samples'   => array( 'order-samples', 'order-patient-demo-kits' ),
		'tools'     => array( 'tools-and-videos', 'allergy-analyser', 'allergy-analyser-tool', 'rectogesic-3d-model', 'fess-demonstration', 'little-coughs' ),
		'videos'    => array( 'clinical-bites', 'allergic-rhinitis-clinical-bites' ),
		'resources' => array( 'resources', 'resource-hub', 'allergic-rhinitis-chart' ),
		'articles'  => array( 'blog' ),
		'brands'    => array( 'allergy', 'nasal-and-sinus-health' ),
		'info'      => array( 'privacy-policy', 'terms-of-use', 'contact', 'support', 'our-company' ),
	);

	/**
	 * @param array<string, string[]> $sections Section => page paths.
	 */
	return (array) apply_filters( 'hcp_analytics_page_sections', $sections );
}

/**
 * Page type and section for the current request.
 *
 * @return array{page_type: string, content_section: string}
 */
function hcp_analytics_page_kind(): array {
	$by_type = array(
		'post'          => array( 'article', 'articles' ),
		'resources'     => array( 'resource', 'resources' ),
		'video'         => array( 'video', 'videos' ),
		'sfwd-courses'  => array( 'course', 'cpd' ),
		'sfwd-lessons'  => array( 'course', 'cpd' ),
		'sfwd-topic'    => array( 'course', 'cpd' ),
		'sfwd-quiz'     => array( 'course', 'cpd' ),
		'brands'        => array( 'brand', 'brands' ),
	);

	if ( is_front_page() ) {
		return array( 'page_type' => 'home', 'content_section' => 'home' );
	}
	if ( is_search() ) {
		return array( 'page_type' => 'search', 'content_section' => 'search' );
	}
	if ( is_404() ) {
		return array( 'page_type' => 'not_found', 'content_section' => 'other' );
	}

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( isset( $by_type[ $post->post_type ] ) ) {
			return array( 'page_type' => $by_type[ $post->post_type ][0], 'content_section' => $by_type[ $post->post_type ][1] );
		}
		if ( 'page' === $post->post_type ) {
			if ( 'template-pdf-redirect.php' === get_page_template_slug( $post ) ) {
				return array( 'page_type' => 'pdf_redirect', 'content_section' => 'resources' );
			}

			return array( 'page_type' => 'page', 'content_section' => hcp_analytics_section_for_path( get_page_uri( $post ) ) );
		}
	}

	if ( is_post_type_archive() || is_tax() || is_category() || is_tag() || is_home() ) {
		$type = get_query_var( 'post_type' ) ?: 'post';
		$type = is_array( $type ) ? (string) reset( $type ) : (string) $type;

		return array( 'page_type' => 'listing', 'content_section' => $by_type[ $type ][1] ?? 'other' );
	}

	return array( 'page_type' => 'other', 'content_section' => 'other' );
}

function hcp_analytics_section_for_path( string $path ): string {
	foreach ( hcp_analytics_page_sections() as $section => $paths ) {
		foreach ( $paths as $p ) {
			if ( $path === $p || 0 === strpos( $path, $p . '/' ) ) {
				return $section;
			}
		}
	}

	return 'other';
}

/**
 * The first term slug of a taxonomy on the current singular page.
 */
function hcp_analytics_term( string $taxonomy ): string {
	if ( ! is_singular() ) {
		return HCP_ANALYTICS_NONE;
	}
	$terms = get_the_terms( get_queried_object_id(), $taxonomy );

	return is_array( $terms ) && $terms ? $terms[0]->slug : HCP_ANALYTICS_NONE;
}

/**
 * Three-letter AHPRA profession code from the stored AHPRA number (not the
 * username: older accounts log in with an email address).
 */
function hcp_analytics_profession( int $user_id ): string {
	$ahpra = strtoupper( trim( (string) get_user_meta( $user_id, 'ahpra_id', true ) ) );

	return preg_match( '/^([A-Z]{3})\d/', $ahpra, $m ) ? $m[1] : 'unknown';
}

/**
 * @return array<string, string> Event parameters sent with every GA4 event on the page.
 */
function hcp_analytics_context(): array {
	$user_id = get_current_user_id();
	$kind    = hcp_analytics_page_kind();

	if ( ! $user_id ) {
		$logged_in  = 'no';
		$profession = HCP_ANALYTICS_NONE;
	} elseif ( hcp_analytics_is_staff( $user_id ) ) {
		$logged_in  = 'staff';
		$profession = HCP_ANALYTICS_NONE;
	} else {
		$logged_in  = 'hcp';
		$profession = hcp_analytics_profession( $user_id );
	}

	return array(
		'logged_in'       => $logged_in,
		'profession'      => $profession,
		'page_type'       => $kind['page_type'],
		'content_section' => $kind['content_section'],
		'therapy_area'    => hcp_analytics_term( 'therapy_area' ),
		'brand'           => hcp_analytics_term( 'brand' ),
	);
}

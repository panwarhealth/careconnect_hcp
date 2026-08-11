<?php
/**
 * Post-registration deep-link.
 *
 * A gated card's Register link stashes its target URL in a plain hidden input
 * (hcp_reg_target — deliberately NOT a Formidable field, so it never touches
 * entry storage or the Salesforce mappings). Registration auto-logs the user
 * in, so when the input is present and internal we send them to the video or
 * resource they originally clicked instead of the site-wide /welcome page.
 * Every other registration keeps the /welcome flow untouched.
 */

defined( 'ABSPATH' ) || exit;

const HCP_VIDEOS_REGISTRATION_FORM_ID = 2;

add_filter( 'frm_redirect_url', 'hcp_videos_register_deep_link', 10, 3 );
function hcp_videos_register_deep_link( $url, $form, $args ) {
	if ( ! is_object( $form ) || (int) $form->id !== HCP_VIDEOS_REGISTRATION_FORM_ID ) {
		return $url;
	}

	$target = isset( $_POST['hcp_reg_target'] ) ? trim( wp_unslash( (string) $_POST['hcp_reg_target'] ) ) : '';
	if ( '' === $target ) {
		return $url;
	}

	// Same-host URLs only; anything else keeps the configured redirect.
	$validated = wp_validate_redirect( $target, '' );

	return '' !== $validated ? $validated : $url;
}

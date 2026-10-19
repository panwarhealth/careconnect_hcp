<?php
/**
 * /consent-preferences/ — where the Terms update email sends people.
 *
 * A bare page (no site header, nav or footer) carrying [hcp_consent_landing]:
 * logged out it shows the login form and comes back here; logged in with no
 * answer it asks the question; with an answer on file it says so.
 */

defined( 'ABSPATH' ) || exit;

const HCP_CONSENT_LANDING_SLUG = 'consent-preferences';

add_filter( 'template_include', 'hcp_consent_landing_template' );
function hcp_consent_landing_template( string $template ): string {
	if ( is_page( HCP_CONSENT_LANDING_SLUG ) ) {
		return HCP_CONSENT_DIR . 'templates/blank.php';
	}

	return $template;
}

add_shortcode( 'hcp_consent_landing', 'hcp_consent_landing_shortcode' );
function hcp_consent_landing_shortcode(): string {
	hcp_consent_enqueue_choice();

	$user_id = get_current_user_id();
	$portal  = '<p class="hcp-consent-landing__portal"><a class="hcp-consent-choice__btn hcp-consent-choice__btn--yes" href="' . esc_url( home_url( '/' ) ) . '">Go to Care Connect</a></p>';

	ob_start();
	echo '<div class="hcp-consent-landing">';
	echo '<h1 class="hcp-consent-landing__title">Communication preferences</h1>';

	if ( ! $user_id ) {
		echo '<p>Please log in to set your communication preferences.</p>';
		echo do_shortcode( '[login_form redirect="' . esc_url( get_permalink() ) . '"]' );
	} else {
		$status = hcp_consent_marketing_status( $user_id );
		if ( $status ) {
			echo '<p class="hcp-consent-landing__done">Thank you, your preference is saved. ' . esc_html( hcp_consent_status_text( $status ) ) . '</p>';
			echo '<p>You can change it at any time on your <a href="' . esc_url( home_url( '/edit-your-profile/' ) ) . '">profile page</a>.</p>';
			echo $portal; // phpcs:ignore WordPress.Security.EscapeOutput -- built above.
		} else {
			echo hcp_consent_choice_html( 'consent-page' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the builder.
			echo '<div class="hcp-consent-landing__saved" data-hcp-consent-saved hidden>';
			echo '<p class="hcp-consent-landing__done">Thank you, your preference is saved.</p>';
			echo $portal; // phpcs:ignore WordPress.Security.EscapeOutput -- built above.
			echo '</div>';
		}
	}

	echo '</div>';

	return (string) ob_get_clean();
}

<?php
/**
 * [hcp_consent_preference] — the marketing choice on Edit Your Profile.
 *
 * Shows the answer on file and lets the person change it. HCPs never see
 * wp-admin, so this is the place a preference can be changed after the pop-up.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hcp_consent_preference', 'hcp_consent_preference_shortcode' );
function hcp_consent_preference_shortcode(): string {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return '';
	}

	$status = hcp_consent_marketing_status( $user_id );

	ob_start();
	?>
	<section class="hcp-consent-pref" aria-labelledby="hcp-consent-pref-title">
		<h2 id="hcp-consent-pref-title">Communication preferences</h2>
		<p class="hcp-consent-pref__status" data-hcp-consent-status><?php echo esc_html( $status ? hcp_consent_status_text( $status ) : 'You have not answered yet.' ); ?></p>
		<?php echo hcp_consent_choice_html( 'account-settings' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the builder. ?>
	</section>
	<?php
	return (string) ob_get_clean();
}

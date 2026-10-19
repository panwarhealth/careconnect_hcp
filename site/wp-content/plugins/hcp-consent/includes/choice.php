<?php
/**
 * The marketing consent choice: the lawyers' statement, then No and Yes.
 *
 * One component for every place the question is asked (pop-up, Edit Your
 * Profile, /consent-preferences/), so the wording someone agreed to is the
 * same wherever they answered and the log can store exactly what was shown.
 */

defined( 'ABSPATH' ) || exit;

// Supplied by Thomsons, verbatim. Terms of Use clause 6.1 refers to this box.
const HCP_CONSENT_STATEMENT = 'Yes, I consent to receiving electronic communications from Care Pharmaceuticals about their products, services, promotions, competitions, surveys, polls, continuing education courses, and other information that may be of interest to me based upon data, including analytic data, that I consent to be collected and used';

/**
 * @param string $method One of HCP_CONSENT_METHODS; recorded with the answer.
 */
function hcp_consent_choice_html( string $method ): string {
	hcp_consent_enqueue_choice();

	ob_start();
	?>
	<div class="hcp-consent-choice" data-hcp-consent-choice data-method="<?php echo esc_attr( $method ); ?>">
		<p class="hcp-consent-choice__statement"><?php echo esc_html( HCP_CONSENT_STATEMENT ); ?></p>
		<div class="hcp-consent-choice__actions">
			<button type="button" class="hcp-consent-choice__btn hcp-consent-choice__btn--no" data-answer="no">No</button>
			<button type="button" class="hcp-consent-choice__btn hcp-consent-choice__btn--yes" data-answer="yes">Yes</button>
		</div>
		<p class="hcp-consent-choice__error" role="alert" hidden>Sorry, your answer could not be saved. Please try again.</p>
		<p class="hcp-consent-choice__links">
			<a href="<?php echo esc_url( home_url( '/terms-of-use/' ) ); ?>" target="_blank" rel="noopener">Terms of Use</a>
			<span aria-hidden="true">&middot;</span>
			<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" target="_blank" rel="noopener">Privacy Policy</a>
		</p>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * "You said Yes on 20/10/26." for someone with an answer on file.
 */
function hcp_consent_status_text( array $status ): string {
	$answer = HCP_CONSENT_OPTED_IN === $status['response'] ? 'Yes' : 'No';
	$when   = mysql2date( 'j/n/y', $status['created_at'] );

	return sprintf( 'You answered %s on %s.', $answer, $when );
}

function hcp_consent_enqueue_choice(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	wp_enqueue_style( 'hcp-consent', HCP_CONSENT_URL . 'assets/css/consent.css', array(), hcp_consent_asset_version( 'assets/css/consent.css' ) );
	wp_enqueue_script( 'hcp-consent', HCP_CONSENT_URL . 'assets/js/consent.js', array(), hcp_consent_asset_version( 'assets/js/consent.js' ), true );
	wp_localize_script(
		'hcp-consent',
		'hcpConsent',
		array(
			'endpoint' => rest_url( 'hcp-consent/v1/marketing' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		)
	);
}

<?php
/**
 * Frontend shortcodes for the MCA review workflow.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hcp_mca_audit_button', 'hcp_mca_render_audit_button' );

/**
 * Render the Mini Clinical Audit CTA button, label and link resolved from
 * hcp_mca_get_state() for the current user.
 */
function hcp_mca_render_audit_button(): string {
	$audit_url = home_url( '/courses/mini-clinical-audit/' );
	$gate_message = '<p class="italic font-semibold">You must complete the Online Learning Module before commencing the Mini Clinical Audit</p>';

	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return sprintf(
			'<a class="btn cta mt-0 opacity-50 mb-0">%s</a>%s',
			esc_html__( 'Start Mini Clinical Audit', 'hcp-mca-review' ),
			$gate_message
		);
	}

	$state = hcp_mca_get_state( $user_id );

	if ( empty( $state['learning_complete'] ) ) {
		return sprintf(
			'<a class="btn cta mt-0 opacity-50 mb-0">%s</a>%s',
			esc_html__( 'Start Mini Clinical Audit', 'hcp-mca-review' ),
			$gate_message
		);
	}

	switch ( $state['state'] ) {
		case HCP_MCA_STATE_PARTIAL:
			$label = __( 'Resume Mini Clinical Audit', 'hcp-mca-review' );
			break;
		case HCP_MCA_STATE_AWAITING_REVIEW:
		case HCP_MCA_STATE_COMPLETE:
			$label = __( 'Review Mini Clinical Audit', 'hcp-mca-review' );
			break;
		case HCP_MCA_STATE_NOT_STARTED:
		default:
			$label = __( 'Start Mini Clinical Audit', 'hcp-mca-review' );
			break;
	}

	return sprintf(
		'<a class="btn cta mt-0" href="%s">%s</a>',
		esc_url( $audit_url ),
		esc_html( $label )
	);
}

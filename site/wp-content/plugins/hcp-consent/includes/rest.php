<?php
/**
 * POST /wp-json/hcp-consent/v1/marketing  { answer: yes|no, method }
 *
 * The one save path for the marketing choice, whichever page it was asked on.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'hcp_consent_register_routes' );
function hcp_consent_register_routes(): void {
	register_rest_route(
		'hcp-consent/v1',
		'/marketing',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'is_user_logged_in',
			'callback'            => 'hcp_consent_rest_save',
			'args'                => array(
				'answer' => array(
					'required' => true,
					'type'     => 'string',
					'enum'     => array( 'yes', 'no' ),
				),
				'method' => array(
					'required' => true,
					'type'     => 'string',
					'enum'     => array( 'in-portal-modal', 'account-settings', 'consent-page' ),
				),
			),
		)
	);
}

function hcp_consent_rest_save( WP_REST_Request $request ) {
	$user_id  = get_current_user_id();
	$response = 'yes' === $request->get_param( 'answer' ) ? HCP_CONSENT_OPTED_IN : HCP_CONSENT_OPTED_OUT;

	if ( ! hcp_consent_record( $user_id, $response, (string) $request->get_param( 'method' ), HCP_CONSENT_STATEMENT ) ) {
		return new WP_Error( 'hcp_consent_not_saved', 'Not saved.', array( 'status' => 500 ) );
	}

	$status = hcp_consent_marketing_status( $user_id );

	return rest_ensure_response(
		array(
			'response' => $response,
			'status'   => $status ? hcp_consent_status_text( $status ) : '',
		)
	);
}

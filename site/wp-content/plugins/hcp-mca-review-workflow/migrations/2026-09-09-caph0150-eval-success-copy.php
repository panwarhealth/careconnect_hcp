<?php
/**
 * CAPH0150: the 2026 evaluation form's own success message names the
 * Clinical Audit, not the Mini Clinical Audit. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 evaluation success message says "Clinical Audit".',

	'up' => function (): string {
		global $wpdb;
		$variant = hcp_mca_variants( true )[ HCP_MCA_VARIANT_V2 ] ?? null;
		if ( ! $variant ) {
			throw new \RuntimeException( '2026 variant not provisioned; run the provision migration first.' );
		}
		$form_id = (int) $variant['eval_form'];
		$options = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT options FROM {$wpdb->prefix}frm_forms WHERE id = %d", $form_id ) ) );
		if ( ! is_array( $options ) ) {
			throw new \RuntimeException( 'evaluation form options unreadable' );
		}
		$old = 'Thank you for completing the Mini Clinical Audit and the Activity Evaluation survey.';
		$new = 'Thank you for completing the Clinical Audit and the Activity Evaluation survey.';
		$msg = (string) ( $options['success_msg'] ?? '' );
		if ( false !== strpos( $msg, $new ) ) {
			return 'already updated';
		}
		if ( 1 !== substr_count( $msg, $old ) ) {
			throw new \RuntimeException( 'expected sentence not found exactly once' );
		}
		$options['success_msg'] = str_replace( $old, $new, $msg );
		$wpdb->update( $wpdb->prefix . 'frm_forms', [ 'options' => maybe_serialize( $options ) ], [ 'id' => $form_id ] );
		FrmForm::clear_form_cache();
		return 'evaluation success message updated';
	},
];

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
		$old   = 'Thank you for completing the Mini Clinical Audit and the Activity Evaluation survey.';
		$new   = 'Thank you for completing the Clinical Audit and the Activity Evaluation survey.';
		$notes = [];

		// Form-level success message.
		$msg = (string) ( $options['success_msg'] ?? '' );
		if ( false !== strpos( $msg, $new ) ) {
			$notes[] = 'form message already updated';
		} elseif ( 1 === substr_count( $msg, $old ) ) {
			$options['success_msg'] = str_replace( $old, $new, $msg );
			$wpdb->update( $wpdb->prefix . 'frm_forms', [ 'options' => maybe_serialize( $options ) ], [ 'id' => $form_id ] );
			$notes[] = 'form message updated';
		} else {
			throw new \RuntimeException( 'form message: expected sentence not found exactly once' );
		}

		// The on-submit confirmation action carries its own copy of the message.
		$actions = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'frm_form_actions' AND post_excerpt = 'on_submit' AND menu_order = %d",
			$form_id
		) );
		foreach ( $actions as $action ) {
			if ( false !== strpos( $action->post_content, $new ) ) {
				$notes[] = "action {$action->ID} already updated";
				continue;
			}
			if ( false === strpos( $action->post_content, $old ) ) {
				continue;
			}
			$wpdb->update( $wpdb->posts, [ 'post_content' => str_replace( $old, $new, $action->post_content ) ], [ 'ID' => (int) $action->ID ] );
			clean_post_cache( (int) $action->ID );
			$notes[] = "action {$action->ID} updated";
		}
		FrmForm::clear_form_cache();
		return implode( '; ', $notes );
	},
];

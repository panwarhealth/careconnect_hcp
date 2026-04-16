<?php
/**
 * Deny edits on HCP_MCA_AUDIT_FORM_ID entries once approved.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'frm_user_can_edit', 'hcp_mca_lock_audit_after_approval_can_edit', 10, 2 );
add_filter( 'frm_validate_entry', 'hcp_mca_lock_audit_after_approval_validate', 10, 2 );

function hcp_mca_lock_audit_after_approval_can_edit( $allowed, $context ) {
	if ( ! $allowed ) {
		return $allowed;
	}

	$form  = $context['form'] ?? null;
	$entry = $context['entry'] ?? null;
	if ( ! is_object( $form ) || (int) $form->id !== HCP_MCA_AUDIT_FORM_ID ) {
		return $allowed;
	}
	if ( ! is_object( $entry ) ) {
		return $allowed;
	}

	$entry_user_id = (int) ( $entry->user_id ?? 0 );
	if ( $entry_user_id <= 0 ) {
		return $allowed;
	}

	$approved_entry_id = (int) get_user_meta( $entry_user_id, HCP_MCA_APPROVED_ENTRY_META, true );
	if ( $approved_entry_id === (int) $entry->id ) {
		return false;
	}

	return $allowed;
}

function hcp_mca_lock_audit_after_approval_validate( $errors, $values ) {
	if ( ! is_array( $errors ) ) {
		$errors = [];
	}

	$form_id = (int) ( $values['form_id'] ?? 0 );
	if ( $form_id !== HCP_MCA_AUDIT_FORM_ID ) {
		return $errors;
	}

	$entry_id = (int) ( $values['id'] ?? 0 );
	if ( $entry_id <= 0 ) {
		return $errors;
	}

	global $wpdb;
	$entry_user_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM {$wpdb->prefix}frm_items WHERE id = %d",
		$entry_id
	) );
	if ( $entry_user_id <= 0 ) {
		return $errors;
	}

	$approved_entry_id = (int) get_user_meta( $entry_user_id, HCP_MCA_APPROVED_ENTRY_META, true );
	if ( $approved_entry_id === $entry_id ) {
		$errors['hcp_mca_locked'] = __( 'This audit submission has been approved and can no longer be edited. Contact the education provider if changes are required.', 'hcp-mca-review' );
	}

	return $errors;
}

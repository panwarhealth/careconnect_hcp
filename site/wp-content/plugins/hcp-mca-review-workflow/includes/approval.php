<?php
/**
 * Audit submission approve / revoke handlers, per variant.
 *
 * User-meta keys are scoped by hcp_mca_user_meta_key(); the legacy variant
 * keeps the historical hcp_mca_approved_* keys.
 */

defined( 'ABSPATH' ) || exit;

function hcp_mca_has_approval( int $user_id, $variant = HCP_MCA_VARIANT_LEGACY ): bool {
	$variant = hcp_mca_variant( $variant );
	if ( $user_id <= 0 || null === $variant ) {
		return false;
	}

	$approved_entry = (int) get_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_audit_entry_id' ), true );
	if ( $approved_entry <= 0 ) {
		return false;
	}

	global $wpdb;
	$latest_entry = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}frm_items
		 WHERE user_id = %d AND form_id = %d
		 ORDER BY updated_at DESC LIMIT 1",
		$user_id, (int) $variant['audit_form']
	) );

	return $latest_entry === $approved_entry;
}

/**
 * Approval meta must be set before learndash_process_mark_complete fires,
 * so the completion guard reads approved_at and skips the revert.
 */
function hcp_mca_approve( int $user_id, int $admin_id, $variant = HCP_MCA_VARIANT_LEGACY ): bool {
	$variant = hcp_mca_variant( $variant );
	if ( $user_id <= 0 || $admin_id <= 0 || null === $variant ) {
		return false;
	}

	$state = hcp_mca_get_state( $user_id, $variant );

	if ( ! $state['has_audit_entry'] || ! $state['has_eval_entry'] ) {
		return false;
	}
	if ( ! $state['audit_entry_id'] ) {
		return false;
	}

	update_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_audit_entry_id' ), (int) $state['audit_entry_id'] );
	update_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_by' ), $admin_id );
	update_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_at' ), current_time( 'mysql' ) );

	if ( ! $state['course_complete'] && function_exists( 'learndash_process_mark_complete' ) ) {
		// $force=true bypasses step-gating; self-heals users with no quiz activity row.
		learndash_process_mark_complete( $user_id, (int) $variant['quiz'], false, (int) $variant['course'], true );
	}

	return true;
}

function hcp_mca_revoke( int $user_id, $variant = HCP_MCA_VARIANT_LEGACY ): bool {
	$variant = hcp_mca_variant( $variant );
	if ( $user_id <= 0 || null === $variant ) {
		return false;
	}

	$entry_key = hcp_mca_user_meta_key( $variant, 'approved_audit_entry_id' );
	if ( ! get_user_meta( $user_id, $entry_key, true ) ) {
		return false;
	}

	delete_user_meta( $user_id, $entry_key );
	delete_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_by' ) );
	delete_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_at' ) );

	hcp_mca_uncomplete_course( $user_id, (int) $variant['course'] );

	return true;
}

/**
 * Reverse a LearnDash course completion for one user.
 */
function hcp_mca_uncomplete_course( int $user_id, int $course_id ): void {
	delete_user_meta( $user_id, 'course_completed_' . $course_id );
	delete_transient( 'learndash_course_completed_' . $course_id . '_' . $user_id );

	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'learndash_user_activity',
		[
			'activity_status'    => 0,
			'activity_completed' => 0,
		],
		[
			'user_id'       => $user_id,
			'post_id'       => $course_id,
			'activity_type' => 'course',
		]
	);
}

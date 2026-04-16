<?php
/**
 * MCA submission approve / revoke handlers for course HCP_MCA_COURSE_ID.
 */

defined( 'ABSPATH' ) || exit;

const HCP_MCA_APPROVED_ENTRY_META = 'hcp_mca_approved_audit_entry_id';
const HCP_MCA_APPROVED_BY_META    = 'hcp_mca_approved_by';
const HCP_MCA_APPROVED_AT_META    = 'hcp_mca_approved_at';

function hcp_mca_is_approved( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}
	$state = hcp_mca_get_state( $user_id );
	return ! empty( $state['approved_at'] );
}

/**
 * Approval meta must be set before learndash_process_mark_complete fires,
 * so the completion guard reads approved_at and skips the revert.
 */
function hcp_mca_approve( int $user_id, int $admin_id ): bool {
	if ( $user_id <= 0 || $admin_id <= 0 ) {
		return false;
	}

	$state = hcp_mca_get_state( $user_id );

	if ( ! $state['has_audit_entry'] || ! $state['has_eval_entry'] ) {
		return false;
	}
	if ( ! $state['audit_entry_id'] ) {
		return false;
	}

	update_user_meta( $user_id, HCP_MCA_APPROVED_ENTRY_META, (int) $state['audit_entry_id'] );
	update_user_meta( $user_id, HCP_MCA_APPROVED_BY_META, $admin_id );
	update_user_meta( $user_id, HCP_MCA_APPROVED_AT_META, current_time( 'mysql' ) );

	if ( ! $state['course_complete'] && function_exists( 'learndash_process_mark_complete' ) ) {
		// $force=true bypasses step-gating; self-heals users with no quiz activity row.
		learndash_process_mark_complete( $user_id, HCP_MCA_QUIZ_ID, false, HCP_MCA_COURSE_ID, true );
	}

	return true;
}

function hcp_mca_revoke( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}

	$had_approval = (bool) get_user_meta( $user_id, HCP_MCA_APPROVED_ENTRY_META, true );
	if ( ! $had_approval ) {
		return false;
	}

	delete_user_meta( $user_id, HCP_MCA_APPROVED_ENTRY_META );
	delete_user_meta( $user_id, HCP_MCA_APPROVED_BY_META );
	delete_user_meta( $user_id, HCP_MCA_APPROVED_AT_META );

	delete_user_meta( $user_id, 'course_completed_' . HCP_MCA_COURSE_ID );
	delete_transient( 'learndash_course_completed_' . HCP_MCA_COURSE_ID . '_' . $user_id );

	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'learndash_user_activity',
		[
			'activity_status'    => 0,
			'activity_completed' => 0,
		],
		[
			'user_id'       => $user_id,
			'post_id'       => HCP_MCA_COURSE_ID,
			'activity_type' => 'course',
		]
	);

	return true;
}

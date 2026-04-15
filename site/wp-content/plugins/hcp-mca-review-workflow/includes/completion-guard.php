<?php
/**
 * Course completion guard.
 *
 * Reverts auto-completion of HCP_MCA_COURSE_ID and silences downstream
 * listeners (cert email, congrats notification) until the user's audit entry
 * has hcp_approved_at metadata set by Maria's approve handler.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'learndash_before_course_completed', 'hcp_mca_completion_guard_arm', 1, 1 );

function hcp_mca_completion_guard_arm( $data ): void {
	if ( ! hcp_mca_completion_guard_target( $data ) ) {
		return;
	}

	$user_id = (int) $data['user']->ID;
	$state   = hcp_mca_get_state( $user_id );
	if ( ! empty( $state['approved_at'] ) ) {
		return;
	}

	add_action( 'learndash_course_completed', 'hcp_mca_completion_guard_revert', 1, 1 );
}

function hcp_mca_completion_guard_revert( $data ): void {
	if ( ! hcp_mca_completion_guard_target( $data ) ) {
		return;
	}

	$user_id   = (int) $data['user']->ID;
	$course_id = (int) $data['course']->ID;

	$state = hcp_mca_get_state( $user_id );
	if ( ! empty( $state['approved_at'] ) ) {
		return;
	}

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

	// Stops cert email + LearnDash Notifications "Congratulations" listener from firing on this completion.
	remove_all_actions( 'learndash_course_completed' );
}

function hcp_mca_completion_guard_target( $data ): bool {
	if ( ! is_array( $data ) ) {
		return false;
	}
	$course = $data['course'] ?? null;
	$user   = $data['user'] ?? null;
	if ( ! is_object( $course ) || ! is_object( $user ) ) {
		return false;
	}
	if ( (int) $course->ID !== HCP_MCA_COURSE_ID ) {
		return false;
	}
	if ( (int) $user->ID <= 0 ) {
		return false;
	}
	return true;
}

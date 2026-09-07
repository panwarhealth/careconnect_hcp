<?php
/**
 * Course completion guard.
 *
 * Reverts auto-completion of any audit variant course and silences downstream
 * listeners (cert email, congrats notification) until the user's audit entry
 * has been approved by the CPD reviewer.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'learndash_before_course_completed', 'hcp_mca_completion_guard_arm', 1, 1 );

function hcp_mca_completion_guard_arm( $data ): void {
	$variant = hcp_mca_completion_guard_target( $data );
	if ( null === $variant ) {
		return;
	}

	$state = hcp_mca_get_state( (int) $data['user']->ID, $variant );
	if ( ! empty( $state['approved_at'] ) ) {
		return;
	}

	add_action( 'learndash_course_completed', 'hcp_mca_completion_guard_revert', 1, 1 );
}

function hcp_mca_completion_guard_revert( $data ): void {
	$variant = hcp_mca_completion_guard_target( $data );
	if ( null === $variant ) {
		return;
	}

	$user_id = (int) $data['user']->ID;

	$state = hcp_mca_get_state( $user_id, $variant );
	if ( ! empty( $state['approved_at'] ) ) {
		return;
	}

	hcp_mca_uncomplete_course( $user_id, (int) $variant['course'] );

	// Stops cert email + LearnDash Notifications "Congratulations" listener from firing on this completion.
	remove_all_actions( 'learndash_course_completed' );
}

/**
 * @return array|null The variant whose course is completing, or null when this is not ours.
 */
function hcp_mca_completion_guard_target( $data ): ?array {
	if ( ! is_array( $data ) ) {
		return null;
	}
	$course = $data['course'] ?? null;
	$user   = $data['user'] ?? null;
	if ( ! is_object( $course ) || ! is_object( $user ) || (int) $user->ID <= 0 ) {
		return null;
	}
	return hcp_mca_variant_for_course( (int) $course->ID );
}

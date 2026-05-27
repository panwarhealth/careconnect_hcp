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

// Old name kept as alias so any out-of-band content referencing it still renders.
add_shortcode( 'hcp_mca_learning_enrol_button',  'hcp_mca_render_learning_module_button' );
add_shortcode( 'hcp_mca_learning_module_button', 'hcp_mca_render_learning_module_button' );

/**
 * Always-render button for the prereq Online Learning Module (course 95553).
 *
 * Replaces the previous patchwork of [course_notstarted] / [course_inprogress] /
 * [course_complete] LearnDash shortcodes whose rendering was conditional on internal
 * LD state and could silently disappear when meta/activity got out of sync (e.g. after
 * an unenrol). One shortcode, one source of truth, one button — always present.
 *
 * Label adapts to actual course progress:
 *   - Not enrolled OR enrolled but not started   → "Start Online Learning Module"
 *   - In progress                                → "Resume Learning Module"
 *   - Completed                                  → "Review Learning Module"
 *
 * Click on the rendered button is intercepted by enrol-modal.js when the user lacks
 * a racgp_number; otherwise the click proceeds normally to the course page.
 */
function hcp_mca_render_learning_module_button(): string {
	$course_id  = HCP_MCA_LEARNING_COURSE_ID;
	$course_url = home_url( '/courses/anal-fissures-breaking-the-cycle-and-the-stigma/' );
	$label      = __( 'Start Online Learning Module', 'hcp-mca-review' );

	$user_id = get_current_user_id();
	if ( $user_id > 0 ) {
		// Read directly from the LearnDash activity table — it's the most honest
		// signal of "has this user actually opened the course yet". LD's own
		// learndash_course_status() returns "Not Started" until a lesson-level
		// progress row is written, which misses users who've started but only
		// touched intro content / surveys.
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT activity_status, activity_started
			 FROM {$wpdb->prefix}learndash_user_activity
			 WHERE user_id = %d AND post_id = %d AND activity_type = 'course'
			 ORDER BY activity_updated DESC LIMIT 1",
			$user_id, $course_id
		) );

		if ( $row ) {
			if ( (int) $row->activity_status === 1 ) {
				$label = __( 'Review Learning Module', 'hcp-mca-review' );
			} elseif ( (int) $row->activity_started > 0 ) {
				$label = __( 'Resume Learning Module', 'hcp-mca-review' );
			}
		}
	}

	return sprintf(
		'<a class="btn cta mt-0" href="%s">%s</a>',
		esc_url( $course_url ),
		esc_html( $label )
	);
}

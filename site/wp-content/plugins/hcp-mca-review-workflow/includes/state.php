<?php
/**
 * MCA review workflow — state detector.
 */

defined( 'ABSPATH' ) || exit;

/**
 * State constants for the `state` field of hcp_mca_get_state().
 *   A   Neither form submitted.
 *   A2  Exactly one of audit + eval submitted.
 *   B   Both forms submitted, course not yet marked complete.
 *   D   Course marked complete (cert issued).
 */
const HCP_MCA_STATE_NOT_STARTED     = 'A';
const HCP_MCA_STATE_PARTIAL         = 'A2';
const HCP_MCA_STATE_AWAITING_REVIEW = 'B';
const HCP_MCA_STATE_COMPLETE        = 'D';

/**
 * Return a snapshot of a user's MCA review workflow state.
 *
 * @param int $user_id WP user ID.
 * @return array {
 *   @type string      $state               One of the HCP_MCA_STATE_* constants.
 *   @type bool        $has_audit_entry     True if user has any form 161 submission.
 *   @type int|null    $audit_entry_id      Most recent form 161 entry ID, or null.
 *   @type string|null $audit_submitted_at  MySQL datetime of most recent audit submission.
 *   @type bool        $has_audit_draft     True if user has a saved, unsubmitted form 161 draft with real answers.
 *   @type bool        $has_eval_entry      True if user has any form 209 submission.
 *   @type int|null    $eval_entry_id       Most recent form 209 entry ID, or null.
 *   @type string|null $eval_submitted_at   MySQL datetime of most recent eval submission.
 *   @type bool        $lesson_complete     True if LearnDash marks lesson 112353 done for this user.
 *   @type bool        $course_complete     True if LearnDash marks course 111793 done for this user.
 *   @type bool        $learning_started    True if LearnDash has any activity row for course 95553 (the prerequisite).
 *   @type bool        $learning_complete   True if LearnDash marks course 95553 done for this user.
 *   @type int|null    $approved_by         Admin user ID who approved (null if not approved via our flow).
 *   @type string|null $approved_at         MySQL datetime of approval (null if not approved via our flow).
 * }
 */
function hcp_mca_get_state( int $user_id ): array {
	global $wpdb;

	$snapshot = [
		'state'              => HCP_MCA_STATE_NOT_STARTED,
		'has_audit_entry'    => false,
		'audit_entry_id'     => null,
		'audit_submitted_at' => null,
		'has_audit_draft'    => false,
		'has_eval_entry'     => false,
		'eval_entry_id'      => null,
		'eval_submitted_at'  => null,
		'lesson_complete'    => false,
		'course_complete'    => false,
		'learning_started'   => false,
		'learning_complete'  => false,
		'approved_by'        => null,
		'approved_at'        => null,
	];

	if ( $user_id <= 0 ) {
		return $snapshot;
	}

	// updated_at DESC — Formidable bumps this field on edits as well as first submit.
	$audit_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, updated_at FROM {$wpdb->prefix}frm_items
		 WHERE user_id = %d AND form_id = %d AND is_draft = 0
		 ORDER BY updated_at DESC LIMIT 1",
		$user_id, HCP_MCA_AUDIT_FORM_ID
	) );
	if ( $audit_row ) {
		$snapshot['has_audit_entry']    = true;
		$snapshot['audit_entry_id']     = (int) $audit_row->id;
		$snapshot['audit_submitted_at'] = $audit_row->updated_at;
	}

	$snapshot['has_audit_draft'] = hcp_mca_furthest_draft_step( $user_id ) > 0;

	$eval_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, updated_at FROM {$wpdb->prefix}frm_items
		 WHERE user_id = %d AND form_id = %d AND is_draft = 0
		 ORDER BY updated_at DESC LIMIT 1",
		$user_id, HCP_MCA_EVAL_FORM_ID
	) );
	if ( $eval_row ) {
		$snapshot['has_eval_entry']    = true;
		$snapshot['eval_entry_id']     = (int) $eval_row->id;
		$snapshot['eval_submitted_at'] = $eval_row->updated_at;
	}

	// activity_status column is authoritative; course_completed_<id> usermeta
	// can be written by paths other than normal LearnDash completion.
	$lesson_status = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT activity_status FROM {$wpdb->prefix}learndash_user_activity
		 WHERE user_id = %d AND post_id = %d AND activity_type = 'lesson'
		 ORDER BY activity_updated DESC LIMIT 1",
		$user_id, HCP_MCA_LESSON_ID
	) );
	$snapshot['lesson_complete'] = ( $lesson_status === 1 );

	$course_status = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT activity_status FROM {$wpdb->prefix}learndash_user_activity
		 WHERE user_id = %d AND post_id = %d AND activity_type = 'course'
		 ORDER BY activity_updated DESC LIMIT 1",
		$user_id, HCP_MCA_COURSE_ID
	) );
	$snapshot['course_complete'] = ( $course_status === 1 );

	$learning_any = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}learndash_user_activity
		 WHERE user_id = %d AND post_id = %d",
		$user_id, HCP_MCA_LEARNING_COURSE_ID
	) );
	$snapshot['learning_started'] = ( $learning_any > 0 );

	$learning_status = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT activity_status FROM {$wpdb->prefix}learndash_user_activity
		 WHERE user_id = %d AND post_id = %d AND activity_type = 'course'
		 ORDER BY activity_updated DESC LIMIT 1",
		$user_id, HCP_MCA_LEARNING_COURSE_ID
	) );
	$snapshot['learning_complete'] = ( $learning_status === 1 );

	// Approval is keyed by the audit entry id so resubmits invalidate prior approvals.
	if ( $snapshot['audit_entry_id'] ) {
		$approved_entry = (int) get_user_meta( $user_id, 'hcp_mca_approved_audit_entry_id', true );
		if ( $approved_entry === $snapshot['audit_entry_id'] ) {
			$snapshot['approved_by'] = (int) get_user_meta( $user_id, 'hcp_mca_approved_by', true ) ?: null;
			$snapshot['approved_at'] = get_user_meta( $user_id, 'hcp_mca_approved_at', true ) ?: null;
		}
	}

	if ( $snapshot['course_complete'] ) {
		$snapshot['state'] = HCP_MCA_STATE_COMPLETE;
	} elseif ( $snapshot['has_audit_entry'] && $snapshot['has_eval_entry'] ) {
		$snapshot['state'] = HCP_MCA_STATE_AWAITING_REVIEW;
	} elseif ( $snapshot['has_audit_entry'] xor $snapshot['has_eval_entry'] ) {
		$snapshot['state'] = HCP_MCA_STATE_PARTIAL;
	}

	return $snapshot;
}

/**
 * Audit course progress in parts: the five audit steps plus the evaluation.
 *
 * Formidable only lets a user leave a step once it validates, so answers on a
 * step mean every step before it is done. A submitted audit counts all five.
 *
 * @return array { completed, total, percentage } in LearnDash's progress shape.
 */
function hcp_mca_audit_progress( int $user_id ): array {
	$state = hcp_mca_get_state( $user_id );
	$steps = count( HCP_MCA_AUDIT_STEP_BREAKS ) + 1;
	$total = $steps + 1;

	if ( $state['course_complete'] ) {
		$done = $total;
	} else {
		$done  = $state['has_audit_entry'] ? $steps : max( 0, hcp_mca_furthest_draft_step( $user_id ) - 1 );
		$done += $state['has_eval_entry'] ? 1 : 0;
	}

	return [
		'completed'  => $done,
		'total'      => $total,
		'percentage' => (int) round( $done / $total * 100 ),
	];
}

/**
 * Furthest audit step (1-based) holding a real answer in the user's latest draft, 0 if none.
 * Calculated fields and the user ID field are saved automatically, so they are not answers.
 * Cached per request: hcp_mca_get_state() is called several times per page.
 */
function hcp_mca_furthest_draft_step( int $user_id ): int {
	global $wpdb;
	static $cache = [];

	if ( isset( $cache[ $user_id ] ) ) {
		return $cache[ $user_id ];
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT f.field_order, f.type, f.field_options FROM {$wpdb->prefix}frm_item_metas m
		 JOIN {$wpdb->prefix}frm_fields f ON f.id = m.field_id
		 WHERE m.item_id = (
			SELECT id FROM {$wpdb->prefix}frm_items
			WHERE user_id = %d AND form_id = %d AND is_draft = 1 AND parent_item_id = 0
			ORDER BY updated_at DESC LIMIT 1
		 )
		 AND f.form_id = %d AND f.type <> 'user_id' AND TRIM(m.meta_value) <> ''",
		$user_id, HCP_MCA_AUDIT_FORM_ID, HCP_MCA_AUDIT_FORM_ID
	) );

	$max_order = null;
	foreach ( $rows as $row ) {
		$opts = maybe_unserialize( $row->field_options );
		if ( ! empty( $opts['calc'] ) ) {
			continue;
		}
		$max_order = max( (int) $max_order, (int) $row->field_order );
	}
	$step = 0;
	if ( null !== $max_order ) {
		$step = 1;
		foreach ( HCP_MCA_AUDIT_STEP_BREAKS as $break ) {
			if ( $max_order > $break ) {
				$step++;
			}
		}
	}

	$cache[ $user_id ] = $step;
	return $step;
}

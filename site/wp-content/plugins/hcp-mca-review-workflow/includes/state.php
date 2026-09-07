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
 * Return a snapshot of a user's review workflow state for one audit variant.
 *
 * @param int          $user_id WP user ID.
 * @param array|string $variant Variant array or key (see variants.php). Defaults to legacy.
 * @return array {
 *   @type string      $variant             Variant key.
 *   @type string      $state               One of the HCP_MCA_STATE_* constants.
 *   @type bool        $has_audit_entry     True if user has any audit form submission.
 *   @type int|null    $audit_entry_id      Most recent audit entry ID, or null.
 *   @type string|null $audit_submitted_at  MySQL datetime of most recent audit submission.
 *   @type bool        $has_eval_entry      True if user has any eval form submission.
 *   @type int|null    $eval_entry_id       Most recent eval entry ID, or null.
 *   @type string|null $eval_submitted_at   MySQL datetime of most recent eval submission.
 *   @type bool        $lesson_complete     True if LearnDash marks the audit lesson done for this user.
 *   @type bool        $course_complete     True if LearnDash marks the audit course done for this user.
 *   @type bool        $learning_started    True if LearnDash has any activity row for course 95553 (the prerequisite).
 *   @type bool        $learning_complete   True if LearnDash marks course 95553 done for this user.
 *   @type int|null    $approved_by         Admin user ID who approved (null if not approved via our flow).
 *   @type string|null $approved_at         MySQL datetime of approval (null if not approved via our flow).
 * }
 */
function hcp_mca_get_state( int $user_id, $variant = HCP_MCA_VARIANT_LEGACY ): array {
	global $wpdb;

	$variant = hcp_mca_variant( $variant );

	$snapshot = [
		'variant'            => $variant['key'] ?? '',
		'state'              => HCP_MCA_STATE_NOT_STARTED,
		'has_audit_entry'    => false,
		'audit_entry_id'     => null,
		'audit_submitted_at' => null,
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

	if ( $user_id <= 0 || null === $variant ) {
		return $snapshot;
	}

	// updated_at DESC — Formidable bumps this field on edits as well as first submit.
	$audit_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, updated_at FROM {$wpdb->prefix}frm_items
		 WHERE user_id = %d AND form_id = %d AND is_draft = 0
		 ORDER BY updated_at DESC LIMIT 1",
		$user_id, (int) $variant['audit_form']
	) );
	if ( $audit_row ) {
		$snapshot['has_audit_entry']    = true;
		$snapshot['audit_entry_id']     = (int) $audit_row->id;
		$snapshot['audit_submitted_at'] = $audit_row->updated_at;
	}

	$eval_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, updated_at FROM {$wpdb->prefix}frm_items
		 WHERE user_id = %d AND form_id = %d AND is_draft = 0
		 ORDER BY updated_at DESC LIMIT 1",
		$user_id, (int) $variant['eval_form']
	) );
	if ( $eval_row ) {
		$snapshot['has_eval_entry']    = true;
		$snapshot['eval_entry_id']     = (int) $eval_row->id;
		$snapshot['eval_submitted_at'] = $eval_row->updated_at;
	}

	// activity_status column is authoritative; course_completed_<id> usermeta
	// can be written by paths other than normal LearnDash completion.
	$snapshot['lesson_complete']   = hcp_mca_activity_complete( $user_id, (int) $variant['lesson'], 'lesson' );
	$snapshot['course_complete']   = hcp_mca_activity_complete( $user_id, (int) $variant['course'], 'course' );
	$snapshot['learning_complete'] = hcp_mca_activity_complete( $user_id, HCP_MCA_LEARNING_COURSE_ID, 'course' );

	$learning_any = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}learndash_user_activity
		 WHERE user_id = %d AND post_id = %d",
		$user_id, HCP_MCA_LEARNING_COURSE_ID
	) );
	$snapshot['learning_started'] = ( $learning_any > 0 );

	// Approval is keyed by the audit entry id so resubmits invalidate prior approvals.
	if ( $snapshot['audit_entry_id'] ) {
		$approved_entry = (int) get_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_audit_entry_id' ), true );
		if ( $approved_entry === $snapshot['audit_entry_id'] ) {
			$snapshot['approved_by'] = (int) get_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_by' ), true ) ?: null;
			$snapshot['approved_at'] = get_user_meta( $user_id, hcp_mca_user_meta_key( $variant, 'approved_at' ), true ) ?: null;
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

function hcp_mca_activity_complete( int $user_id, int $post_id, string $type ): bool {
	global $wpdb;

	if ( $post_id <= 0 ) {
		return false;
	}

	$status = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT activity_status FROM {$wpdb->prefix}learndash_user_activity
		 WHERE user_id = %d AND post_id = %d AND activity_type = %s
		 ORDER BY activity_updated DESC LIMIT 1",
		$user_id, $post_id, $type
	) );

	return 1 === $status;
}

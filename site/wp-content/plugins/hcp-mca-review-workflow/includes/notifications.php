<?php
/**
 * Notifications — emails fired by the MCA review workflow.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'frm_after_create_entry', 'hcp_mca_maybe_notify_review_ready', 30, 2 );
add_action( 'frm_after_update_entry', 'hcp_mca_maybe_notify_review_ready', 30, 2 );

/**
 * Emit a "ready for review" email when a submission causes the user
 * to enter state B (both forms in).
 *
 * On first submission: fires via frm_after_create_entry.
 * On resubmit: fires via frm_after_update_entry — dedup is keyed on
 * audit_entry_id + updated_at so resubmits re-trigger notification.
 */
function hcp_mca_maybe_notify_review_ready( $entry_id, $form_id ): void {
	$variant = hcp_mca_variant_for_form( (int) $form_id );
	if ( null === $variant ) {
		return;
	}

	global $wpdb;
	$user_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM {$wpdb->prefix}frm_items WHERE id = %d",
		$entry_id
	) );
	if ( $user_id <= 0 ) {
		return;
	}

	$state = hcp_mca_get_state( $user_id, $variant );
	if ( $state['state'] !== HCP_MCA_STATE_AWAITING_REVIEW ) {
		return;
	}

	$meta_key          = hcp_mca_user_meta_key( $variant, 'review_email_sent_for_entry_id' );
	$audit_entry_id    = (int) $state['audit_entry_id'];
	$dedup_key         = $audit_entry_id . ':' . ( $state['audit_submitted_at'] ?? '' );
	$last_notified_for = get_user_meta( $user_id, $meta_key, true );
	if ( $last_notified_for === $dedup_key ) {
		return;
	}

	$is_resubmit = ( current_action() === 'frm_after_update_entry' );

	if ( hcp_mca_send_review_ready_email( $user_id, $state, $is_resubmit, $variant ) ) {
		update_user_meta( $user_id, $meta_key, $dedup_key );
	}
}

function hcp_mca_send_review_ready_email( int $user_id, array $state, bool $is_resubmit = false, $variant = HCP_MCA_VARIANT_LEGACY ): bool {
	$variant = hcp_mca_variant( $variant );
	$title   = $variant ? get_the_title( (int) $variant['course'] ) : 'Mini Clinical Audit';

	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		return false;
	}

	$entry_url = admin_url(
		'admin.php?page=formidable-entries&frm_action=show&id=' . (int) $state['audit_entry_id']
	);
	$user_url = admin_url( 'user-edit.php?user_id=' . $user_id );

	$subject = sprintf(
		'[Care Connect] Audit %s — %s (%s)',
		$is_resubmit ? 'resubmitted for review' : 'ready for review',
		$user->display_name,
		$user->user_login
	);

	$action_text = $is_resubmit
		? sprintf( 'has resubmitted their %s. Their updated submission is ready for review.', $title )
		: sprintf( 'has submitted both the %s and Activity Evaluation. Their submission is ready for review.', $title );

	$lines = [
		'Hello,',
		'',
		sprintf( '%s (login: %s) %s', $user->display_name, $user->user_login, $action_text ),
		'',
		'Audit entry: ' . $entry_url,
		'User profile: ' . $user_url,
		'',
		'Audit submitted: ' . ( $state['audit_submitted_at'] ?? '' ),
		'Eval submitted:  ' . ( $state['eval_submitted_at'] ?? '' ),
		'',
		'— HCP CarePharma',
	];
	$body = implode( "\r\n", $lines );

	return wp_mail(
		HCP_MCA_ADMIN_EMAIL,
		$subject,
		$body,
		[ 'Content-Type: text/plain; charset=UTF-8' ]
	);
}

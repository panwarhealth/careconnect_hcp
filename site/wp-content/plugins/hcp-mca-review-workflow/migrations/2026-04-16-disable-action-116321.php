<?php
/**
 * Deactivates Formidable action 116321 ("completed audit" email on form 161
 * submit). Superseded by hcp_mca_maybe_notify_review_ready which fires on
 * state B (both forms in).
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Disable Formidable form-action post 116321 (duplicate admin email on audit submit)',
	'up'          => function (): void {
		global $wpdb;

		$action_id = 116321;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_status, post_type FROM {$wpdb->prefix}posts WHERE ID = %d",
			$action_id
		) );

		if ( ! $row ) {
			return;
		}

		if ( $row->post_type !== 'frm_form_actions' ) {
			return;
		}

		if ( $row->post_status === 'draft' ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'posts',
			[ 'post_status' => 'draft' ],
			[ 'ID' => $action_id ]
		);
	},
];

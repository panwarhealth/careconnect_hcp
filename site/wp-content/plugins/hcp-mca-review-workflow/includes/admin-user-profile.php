<?php
/**
 * Submission Approved checkbox on the wp-admin user profile edit screen.
 * Rendered after the LearnDash Course Info block; JS relocates it under the
 * Activity evaluation row within the MCA course block.
 */

defined( 'ABSPATH' ) || exit;

const HCP_MCA_APPROVAL_NONCE = 'hcp_mca_approval_nonce';

add_action( 'show_user_profile', 'hcp_mca_render_approval_checkbox', 20 );
add_action( 'edit_user_profile', 'hcp_mca_render_approval_checkbox', 20 );
add_action( 'personal_options_update', 'hcp_mca_save_approval_checkbox', 20 );
add_action( 'edit_user_profile_update', 'hcp_mca_save_approval_checkbox', 20 );

function hcp_mca_render_approval_checkbox( WP_User $user ): void {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	$state       = hcp_mca_get_state( $user->ID );
	$is_approved = ! empty( $state['approved_at'] );
	$both_forms  = $state['has_audit_entry'] && $state['has_eval_entry'];

	$approved_by_name = '';
	if ( $is_approved && ! empty( $state['approved_by'] ) ) {
		$approver = get_user_by( 'id', (int) $state['approved_by'] );
		if ( $approver ) {
			$approved_by_name = $approver->display_name;
		}
	}

	$meta_text = '';
	if ( ! $both_forms && ! $is_approved ) {
		$meta_text = ' <span style="color:#888; font-size:12px;">(available once both forms are submitted)</span>';
	} elseif ( $is_approved && $approved_by_name ) {
		$meta_text = sprintf(
			' <span style="color:#888; font-size:12px;">(approved by %s%s)</span>',
			esc_html( $approved_by_name ),
			! empty( $state['approved_at'] ) ? ' on ' . esc_html( $state['approved_at'] ) : ''
		);
	}
	?>
	<div id="hcp-mca-approval-section" class="quiz_list_item quiz_list_item_global" style="display:none;">
		<div class="list_arrow"></div>
		<div class="list_lessons">
			<div class="lesson" style="white-space:nowrap;">
				<?php wp_nonce_field( HCP_MCA_APPROVAL_NONCE, HCP_MCA_APPROVAL_NONCE ); ?>
				<input type="checkbox"
					id="hcp-mca-submission-approved"
					name="hcp_mca_submission_approved"
					value="1"
					<?php checked( $is_approved ); ?>
					<?php disabled( ! $both_forms && ! $is_approved ); ?>
				/>
				<label for="hcp-mca-submission-approved"><strong>Submission Approved</strong></label><?php echo $meta_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
	<script>
	(function(){
		var QUIZ_ID = <?php echo (int) HCP_MCA_QUIZ_ID; ?>;
		function relocate() {
			var section = document.getElementById('hcp-mca-approval-section');
			if (!section) return;
			var quizRow = document.getElementById('quiz_list-' + QUIZ_ID);
			if (!quizRow || !quizRow.parentNode) return;
			quizRow.parentNode.insertBefore(section, quizRow.nextSibling);
			section.style.display = '';
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', relocate);
		} else {
			relocate();
		}
	})();
	</script>
	<?php
}

function hcp_mca_save_approval_checkbox( int $user_id ): void {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}
	if ( empty( $_POST[ HCP_MCA_APPROVAL_NONCE ] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ HCP_MCA_APPROVAL_NONCE ] ) ), HCP_MCA_APPROVAL_NONCE ) ) {
		return;
	}

	$checkbox_ticked = ! empty( $_POST['hcp_mca_submission_approved'] );
	$currently_approved = hcp_mca_is_approved( $user_id );

	if ( $checkbox_ticked && ! $currently_approved ) {
		hcp_mca_approve( $user_id, get_current_user_id() );
	} elseif ( ! $checkbox_ticked && $currently_approved ) {
		hcp_mca_revoke( $user_id );
	}
}

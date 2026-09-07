<?php
/**
 * Submission Approved checkbox on the wp-admin user profile edit screen, one
 * per audit variant. Rendered after the LearnDash Course Info block; JS
 * relocates each under the Activity evaluation row of its course block.
 */

defined( 'ABSPATH' ) || exit;

const HCP_MCA_APPROVAL_NONCE = 'hcp_mca_approval_nonce';

add_action( 'show_user_profile', 'hcp_mca_render_approval_checkboxes', 20 );
add_action( 'edit_user_profile', 'hcp_mca_render_approval_checkboxes', 20 );
add_action( 'personal_options_update', 'hcp_mca_save_approval_checkboxes', 20 );
add_action( 'edit_user_profile_update', 'hcp_mca_save_approval_checkboxes', 20 );

function hcp_mca_render_approval_checkboxes( WP_User $user ): void {
	if ( ! current_user_can( 'edit_users' ) || ! hcp_mca_current_user_can_view() ) {
		return;
	}

	wp_nonce_field( HCP_MCA_APPROVAL_NONCE, HCP_MCA_APPROVAL_NONCE );

	$quiz_ids = [];
	foreach ( hcp_mca_variants() as $variant ) {
		hcp_mca_render_approval_checkbox( $user, $variant );
		$quiz_ids[ $variant['key'] ] = (int) $variant['quiz'];
	}
	?>
	<script>
	(function(){
		var quizIds = <?php echo wp_json_encode( $quiz_ids ); ?>;
		function relocate() {
			Object.keys(quizIds).forEach(function(key) {
				var section = document.getElementById('hcp-mca-approval-section-' + key);
				if (!section) return;
				var quizRow = document.getElementById('quiz_list-' + quizIds[key]);
				if (!quizRow || !quizRow.parentNode) return;
				quizRow.parentNode.insertBefore(section, quizRow.nextSibling);
				section.style.display = '';
			});
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

function hcp_mca_render_approval_checkbox( WP_User $user, array $variant ): void {
	$key         = $variant['key'];
	$state       = hcp_mca_get_state( $user->ID, $variant );
	$is_approved = hcp_mca_has_approval( $user->ID, $variant );
	$both_forms  = $state['has_audit_entry'] && $state['has_eval_entry'];
	$available   = $both_forms || $is_approved;

	$approved_by_name = '';
	if ( $is_approved ) {
		$approved_by_id = (int) get_user_meta( $user->ID, hcp_mca_user_meta_key( $variant, 'approved_by' ), true );
		$approver       = $approved_by_id ? get_user_by( 'id', $approved_by_id ) : null;
		if ( $approver ) {
			$approved_by_name = $approver->display_name;
		}
	}

	$meta_text = '';
	if ( ! $available ) {
		$meta_text = ' <span style="color:#888; font-size:12px;">(available once both forms are submitted)</span>';
	} elseif ( $is_approved && $approved_by_name ) {
		$approved_at = get_user_meta( $user->ID, hcp_mca_user_meta_key( $variant, 'approved_at' ), true );
		$meta_text   = sprintf(
			' <span style="color:#888; font-size:12px;">(approved by %s%s)</span>',
			esc_html( $approved_by_name ),
			$approved_at ? ' on ' . esc_html( $approved_at ) : ''
		);
	}
	?>
	<div id="hcp-mca-approval-section-<?php echo esc_attr( $key ); ?>" class="quiz_list_item quiz_list_item_global" style="display:none;">
		<div class="list_arrow"></div>
		<div class="list_lessons">
			<div class="lesson" style="white-space:nowrap;">
				<input type="checkbox"
					id="hcp-mca-submission-approved-<?php echo esc_attr( $key ); ?>"
					name="hcp_mca_submission_approved[<?php echo esc_attr( $key ); ?>]"
					value="1"
					<?php checked( $is_approved ); ?>
					<?php disabled( ! $available ); ?>
				/>
				<label for="hcp-mca-submission-approved-<?php echo esc_attr( $key ); ?>"><strong>Submission Approved</strong></label><?php echo $meta_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
	<?php
}

function hcp_mca_save_approval_checkboxes( int $user_id ): void {
	if ( ! current_user_can( 'edit_users' ) || ! hcp_mca_current_user_can_view() ) {
		return;
	}
	if ( empty( $_POST[ HCP_MCA_APPROVAL_NONCE ] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ HCP_MCA_APPROVAL_NONCE ] ) ), HCP_MCA_APPROVAL_NONCE ) ) {
		return;
	}

	$ticked = (array) ( $_POST['hcp_mca_submission_approved'] ?? [] );

	foreach ( hcp_mca_variants() as $key => $variant ) {
		$checkbox_ticked    = ! empty( $ticked[ $key ] );
		$currently_approved = hcp_mca_has_approval( $user_id, $variant );

		if ( $checkbox_ticked && ! $currently_approved ) {
			hcp_mca_approve( $user_id, get_current_user_id(), $variant );
		} elseif ( ! $checkbox_ticked && $currently_approved ) {
			hcp_mca_revoke( $user_id, $variant );
		}
	}
}

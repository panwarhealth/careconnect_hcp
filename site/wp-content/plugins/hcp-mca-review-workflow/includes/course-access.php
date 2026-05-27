<?php
/**
 * Click-based opt-in for the prerequisite Online Learning Module (course 95553).
 *
 * Replaces the old auto-enrol hook in the child theme that fired on first racgp_number
 * write. That hook was brittle (only fired once per user, only on the canonical meta
 * key, and used a remove-then-grant toggle). The new flow:
 *
 *   - User clicks "Start/Resume/Review Learning Module" on the activity homepage, OR
 *     LearnDash's "Take this Course" button on the course page itself
 *   - JS checks whether the user has a racgp_number set
 *   - If yes: click proceeds normally; LearnDash enrols on visit
 *   - If no:  click intercepted; modal asks for RACGP; on submit we save the meta and
 *     enrol via ld_update_course_access(), then redirect to the course page
 *
 * The enrol_course event still fires (Notification 111729 "Thank you for registering"
 * is preserved), since we go through the same ld_update_course_access() function the
 * old hook used.
 */

defined( 'ABSPATH' ) || exit;

/**
 * True if the user has a non-empty canonical racgp_number meta.
 *
 * Also checks _racgp_number for transitional safety until the consolidation migration
 * has run everywhere. After the migration this fallback is a no-op.
 */
function hcp_mca_user_has_racgp( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}
	$canonical = trim( (string) get_user_meta( $user_id, 'racgp_number', true ) );
	if ( $canonical !== '' ) {
		return true;
	}
	$underscore = trim( (string) get_user_meta( $user_id, '_racgp_number', true ) );
	return $underscore !== '';
}

/**
 * The activity homepage page slug (where the three "Start/Resume/Review Learning
 * Module" buttons render). Course-access JS is enqueued on this page and on the
 * Online Learning Module course itself.
 */
const HCP_MCA_ACTIVITY_HOMEPAGE_SLUG = 'anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage';

/**
 * Enqueue the modal JS + CSS only where the prereq-course start links can appear.
 */
add_action( 'wp_enqueue_scripts', 'hcp_mca_enqueue_course_access_assets' );
function hcp_mca_enqueue_course_access_assets(): void {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$on_activity_homepage = is_page( HCP_MCA_ACTIVITY_HOMEPAGE_SLUG );
	$on_learning_course   = is_singular( 'sfwd-courses' ) && (int) get_the_ID() === HCP_MCA_LEARNING_COURSE_ID;

	if ( ! $on_activity_homepage && ! $on_learning_course ) {
		return;
	}

	$base_url = plugins_url( '', HCP_MCA_PLUGIN_DIR . 'hcp-mca-review-workflow.php' );

	wp_enqueue_style(
		'hcp-mca-enrol-modal',
		$base_url . '/assets/css/enrol-modal.css',
		[],
		'1.0.0'
	);
	wp_enqueue_script(
		'hcp-mca-enrol-modal',
		$base_url . '/assets/js/enrol-modal.js',
		[],
		'1.0.0',
		true
	);

	$user_id   = get_current_user_id();
	$has_racgp = hcp_mca_user_has_racgp( $user_id );

	// Auto-open modal when a redirected-from-gate user lands here without RACGP.
	// The server-side gate in functions.php (courses_gate) appends ?capture=racgp
	// when it bounces a user out of a gated course page.
	$auto_open = ( ! $has_racgp )
		&& isset( $_GET['capture'] )
		&& $_GET['capture'] === 'racgp';

	wp_localize_script( 'hcp-mca-enrol-modal', 'hcpMcaEnrol', [
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'hcp_mca_enrol' ),
		'hasRacgp'  => $has_racgp,
		'courseUrl' => get_permalink( HCP_MCA_LEARNING_COURSE_ID ),
		'courseId'  => HCP_MCA_LEARNING_COURSE_ID,
		'autoOpen'  => $auto_open,
	] );
}

/**
 * Enforce identical RACGP field constraints on the Formidable registration form (Form 2).
 *
 * Field 4401 (key gajy7) is a Formidable "number" type — maxlength doesn't apply to
 * number inputs and the type alone allows values of any length. This inlines a small
 * script that mirrors the modal: strip non-digits on input, cap at 7 chars, block
 * submission with the same error message when the value is outside 6–7 digits.
 */
add_action( 'wp_footer', 'hcp_mca_register_form_racgp_constraints' );
function hcp_mca_register_form_racgp_constraints(): void {
	if ( ! is_page( 'register' ) ) {
		return;
	}
	?>
	<script>
	(function () {
		function initRacgpField() {
			var field = document.getElementById('field_gajy7');
			if (!field) return;

			// Convert to text so maxlength and pattern work properly.
			field.setAttribute('type', 'text');
			field.setAttribute('inputmode', 'numeric');
			field.setAttribute('maxlength', '7');
			field.setAttribute('pattern', '\\d{6,7}');

			field.addEventListener('input', function () {
				this.value = this.value.replace(/\D/g, '').slice(0, 7);
			});

			// Block form submit with the same message as the modal.
			var form = field.closest('form');
			if (form) {
				form.addEventListener('submit', function (e) {
					var val = field.value.replace(/\D/g, '');
					if (val.length < 6 || val.length > 7) {
						e.preventDefault();
						e.stopImmediatePropagation();
						var err = form.querySelector('.frm_error_style, .frm_form_field.frm_top_container .frm_error');
						var msg = 'RACGP numbers are usually 6 to 7 digits. Please check and try again.';
						var existing = field.parentNode.querySelector('.hcp-racgp-field-error');
						if (!existing) {
							var el = document.createElement('p');
							el.className = 'frm_error hcp-racgp-field-error';
							el.textContent = msg;
							field.parentNode.appendChild(el);
						}
						field.focus();
					} else {
						var existing = field.parentNode.querySelector('.hcp-racgp-field-error');
						if (existing) existing.remove();
					}
				}, true);
			}
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initRacgpField);
		} else {
			initRacgpField();
		}
	})();
	</script>
	<?php
}

/**
 * AJAX: save RACGP number (if provided) and enrol the user in the prereq course.
 *
 * Single round-trip:
 *   - Validates nonce + login
 *   - Writes racgp_number meta (only if the field came through and the user does
 *     not already have one — avoids accidental overwrites)
 *   - Calls ld_update_course_access( user, 95553, $remove = false ) which fires
 *     the enroll_course event → triggers Notification 111729 welcome email
 *   - Returns the course URL so the JS can redirect
 */
add_action( 'wp_ajax_hcp_mca_enrol_with_racgp', 'hcp_mca_ajax_enrol_with_racgp' );
function hcp_mca_ajax_enrol_with_racgp(): void {
	check_ajax_referer( 'hcp_mca_enrol', 'nonce' );

	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'hcp-mca-review' ) ], 401 );
	}

	$submitted = isset( $_POST['racgp_number'] )
		? trim( sanitize_text_field( wp_unslash( $_POST['racgp_number'] ) ) )
		: '';

	// If the user already has a canonical RACGP on file, ignore any submitted value
	// (this branch is for users who somehow hit the modal despite already being set;
	// we don't overwrite their data). Just enrol them.
	if ( hcp_mca_user_has_racgp( $user_id ) ) {
		// fall through to enrol
	} else {
		// Server-side mirror of client validation. Modal should never let invalid
		// values through, but never trust the client.
		if ( $submitted === '' ) {
			wp_send_json_error( [ 'message' => __( 'Please enter your RACGP number.', 'hcp-mca-review' ) ], 400 );
		}
		if ( ! preg_match( '/^\d{6,7}$/', $submitted ) ) {
			wp_send_json_error( [ 'message' => __( 'RACGP numbers are usually 6 to 7 digits. Please check and try again.', 'hcp-mca-review' ) ], 400 );
		}
		update_user_meta( $user_id, 'racgp_number', $submitted );
	}

	if ( ! function_exists( 'ld_update_course_access' ) ) {
		wp_send_json_error( [ 'message' => __( 'LearnDash unavailable.', 'hcp-mca-review' ) ], 500 );
	}

	// $remove = false → grant access. Fires enroll_course event → Notification 111729.
	ld_update_course_access( $user_id, HCP_MCA_LEARNING_COURSE_ID, false );

	wp_send_json_success( [
		'redirect' => get_permalink( HCP_MCA_LEARNING_COURSE_ID ),
	] );
}

<?php
/**
 * Frontend tweaks for MCA course pages.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'hcp_mca_hide_start_survey_button' );

/**
 * Hide the LearnDash "Start Survey" next-step button on the audit lesson page.
 * The button is irrelevant while the user is filling in the multi-page audit form.
 */
function hcp_mca_hide_start_survey_button(): void {
	if ( ! is_singular( 'sfwd-lessons' ) || get_the_ID() !== HCP_MCA_LESSON_ID ) {
		return;
	}
	echo '<style>.ld-content-actions { display: none !important; }</style>';
}

add_filter( 'frm_submit_button_html', 'hcp_mca_resubmit_button_label', 10, 2 );

/**
 * Change the submit button to "Resubmit" on form 161 when the user already has an entry.
 */
function hcp_mca_resubmit_button_label( $html, $args ): string {
	if ( (int) $args['form']->id !== HCP_MCA_AUDIT_FORM_ID ) {
		return $html;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return $html;
	}

	if ( hcp_mca_has_approval( $user_id ) ) {
		$html = str_replace( '>Save and continue later<', '>Save<', $html );
		$html = preg_replace( '/<button[^>]*frm_final_submit[^>]*>.*?<\/button>/s', '', $html );
		return $html;
	}

	$state = hcp_mca_get_state( $user_id );

	if ( $state['has_audit_entry'] || $state['lesson_complete'] ) {
		$html = str_replace( '>Submit<', '>Resubmit<', $html );
		$html = str_replace( '>Update<', '>Resubmit<', $html );
	}

	return $html;
}

add_filter( 'frm_setup_new_fields_vars', 'hcp_mca_audit_banner_copy', 10, 2 );
add_filter( 'frm_setup_edit_fields_vars', 'hcp_mca_audit_banner_copy', 10, 2 );

function hcp_mca_audit_banner_copy( $field_array, $field ) {
	if ( (int) $field_array['id'] !== 12465 ) {
		return $field_array;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return $field_array;
	}

	$state = hcp_mca_get_state( $user_id );

	if ( hcp_mca_has_approval( $user_id ) ) {
		$field_array['description'] = hcp_mca_approved_banner_html( 'center' );
	} elseif ( $state['has_audit_entry'] || $state['lesson_complete'] ) {
		$field_array['description'] = '<blockquote class="p-base bg-recto-green text-white rounded-lg">'
			. '<p class="text-center text-lg font-semibold mb-0">'
			. 'Your audit has been submitted for review. Provided there are no issues with your responses, '
			. 'you will receive an email within four weeks with your Statement of Completion.'
			. '</p>'
			. '<p class="text-center text-lg mt-base mb-0">'
			. 'If you would like to make changes to your responses, do this directly in the form then click the \'Resubmit\' button.'
			. '</p>'
			. '</blockquote>';
	} else {
		$field_array['description'] = '<blockquote class="p-base bg-recto-green text-white rounded-lg">'
			. '<p class="text-center text-lg font-semibold mb-0">'
			. 'Once you have completed all sections of your clinical audit, please do a final check of your responses and click \'Submit\'.'
			. '</p>'
			. '<p class="text-center text-lg mt-base mb-0">'
			. 'You will then be directed to the audit Evaluation Survey to complete your submission.'
			. '</p>'
			. '</blockquote>';
	}

	return $field_array;
}

function hcp_mca_approved_banner_html( string $align = 'left' ): string {
	$p_style = ( 'center' === $align )
		? 'width:auto;margin:0;padding:0 3rem 0 0;text-align:center;'
		: 'width:auto;margin:0;padding:0;text-align:left;';

	return '<div class="bg-recto-green text-white rounded-lg" style="padding:1rem;text-align:center;">'
		. '<p class="text-sm font-semibold" style="' . $p_style . '">'
		. 'This audit submission has been approved. You may edit responses but you do not need to resubmit them for review.'
		. '</p>'
		. '</div>';
}

add_action( 'wp_footer', 'hcp_mca_approved_buttons_js' );

function hcp_mca_approved_buttons_js(): void {
	if ( ! is_singular( 'sfwd-lessons' ) || get_the_ID() !== HCP_MCA_LESSON_ID ) {
		return;
	}
	if ( ! hcp_mca_has_approval( get_current_user_id() ) ) {
		return;
	}
	?>
	<script>
	(function($) {
		function hcpMcaFixApprovedButtons() {
			$('.frm_final_submit').remove();
			$('.frm_save_draft').text('Save');
		}
		$(document).on('frmPageChanged', hcpMcaFixApprovedButtons);
		$(document).ready(hcpMcaFixApprovedButtons);
	})(jQuery);
	</script>
	<?php
}

add_filter( 'the_content', 'hcp_mca_prepend_approved_banner_to_lesson', 5 );

function hcp_mca_prepend_approved_banner_to_lesson( $content ): string {
	static $done = false;
	if ( $done ) {
		return $content;
	}
	if ( ! is_singular( 'sfwd-lessons' ) || get_the_ID() !== HCP_MCA_LESSON_ID ) {
		return $content;
	}
	if ( ! hcp_mca_has_approval( get_current_user_id() ) ) {
		return $content;
	}
	$done = true;
	return '<div class="mt-8 mb-0">' . hcp_mca_approved_banner_html() . '</div>' . $content;
}

<?php
/**
 * Frontend tweaks for the audit lesson and form, for every variant.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The audit variant whose lesson is being viewed, or null.
 */
function hcp_mca_current_lesson_variant(): ?array {
	if ( ! is_singular( 'sfwd-lessons' ) ) {
		return null;
	}
	return hcp_mca_variant_for_lesson( (int) get_the_ID() );
}

add_action( 'wp_head', 'hcp_mca_hide_start_survey_button' );

/**
 * Hide the LearnDash "Start Survey" next-step button on the audit lesson page.
 * The button is irrelevant while the user is filling in the multi-page audit form.
 */
function hcp_mca_hide_start_survey_button(): void {
	if ( null === hcp_mca_current_lesson_variant() ) {
		return;
	}
	echo '<style>.ld-content-actions { display: none !important; }</style>';
}

add_filter( 'frm_submit_button_html', 'hcp_mca_resubmit_button_label', 10, 2 );

/**
 * Change the submit button to "Resubmit" on the audit form when the user already has an entry.
 */
function hcp_mca_resubmit_button_label( $html, $args ): string {
	$variant = hcp_mca_variant_for_audit_form( (int) $args['form']->id );
	if ( null === $variant ) {
		return $html;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return $html;
	}

	if ( hcp_mca_has_approval( $user_id, $variant ) ) {
		$html = str_replace( '>Save and continue later<', '>Save<', $html );
		$html = preg_replace( '/<button[^>]*frm_final_submit[^>]*>.*?<\/button>/s', '', $html );
		return $html;
	}

	$state = hcp_mca_get_state( $user_id, $variant );

	if ( $state['has_audit_entry'] || $state['lesson_complete'] ) {
		$html = str_replace( '>Submit<', '>Resubmit<', $html );
		$html = str_replace( '>Update<', '>Resubmit<', $html );
	}

	return $html;
}

add_filter( 'frm_setup_new_fields_vars', 'hcp_mca_audit_banner_copy', 10, 2 );
add_filter( 'frm_setup_edit_fields_vars', 'hcp_mca_audit_banner_copy', 10, 2 );

function hcp_mca_audit_banner_copy( $field_array, $field ) {
	$variant = hcp_mca_variant_by( 'banner_field', (int) $field_array['id'] );
	if ( null === $variant ) {
		return $field_array;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return $field_array;
	}

	$state = hcp_mca_get_state( $user_id, $variant );

	if ( hcp_mca_has_approval( $user_id, $variant ) ) {
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
	$variant = hcp_mca_current_lesson_variant();
	if ( null === $variant || ! hcp_mca_has_approval( get_current_user_id(), $variant ) ) {
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
	$variant = hcp_mca_current_lesson_variant();
	if ( null === $variant || ! hcp_mca_has_approval( get_current_user_id(), $variant ) ) {
		return $content;
	}
	$done = true;
	return '<div class="mt-8 mb-0">' . hcp_mca_approved_banner_html() . '</div>' . $content;
}

add_action( 'wp_enqueue_scripts', 'hcp_mca_enqueue_audit_v2_assets' );

/**
 * Interactive behaviour for the 2026 audit form: criteria pull-through,
 * count limits, generated statements, case study checks.
 */
function hcp_mca_enqueue_audit_v2_assets(): void {
	$variant = hcp_mca_current_lesson_variant();
	if ( null === $variant || HCP_MCA_VARIANT_V2 !== $variant['key'] ) {
		return;
	}

	$base_url = plugins_url( '', HCP_MCA_PLUGIN_DIR . 'hcp-mca-review-workflow.php' );
	$version  = '1.0.0';

	wp_enqueue_style( 'hcp-mca-audit-v2', $base_url . '/assets/css/audit-v2.css', [], $version );
	wp_enqueue_script( 'hcp-mca-audit-v2', $base_url . '/assets/js/audit-v2.js', [ 'jquery' ], $version, true );
	wp_localize_script( 'hcp-mca-audit-v2', 'hcpAuditV2', hcp_mca_audit_v2_js_config( $variant ) );
}

/**
 * Field key => id map for the audit form and its child forms, plus the
 * groups the JS needs: counts limited by the diagnosed total (numerators of
 * the percentage calculations) and the Step 1B improvement-area checkboxes.
 */
function hcp_mca_audit_v2_js_config( array $variant ): array {
	global $wpdb;

	$form_id   = (int) $variant['audit_form'];
	$diagnosed = 0;
	$fields    = [];
	$rows      = $wpdb->get_results( $wpdb->prepare(
		"SELECT f.id, f.field_key, f.type, f.field_options FROM {$wpdb->prefix}frm_fields f
		 LEFT JOIN {$wpdb->prefix}frm_forms fr ON fr.id = f.form_id
		 WHERE f.form_id = %d OR fr.parent_form_id = %d",
		$form_id, $form_id
	) );
	foreach ( $rows as $row ) {
		$fields[ $row->field_key ] = (int) $row->id;
		if ( HCP_MCA_V2_FIELD_KEY_PREFIX . '9962s' === $row->field_key ) {
			$diagnosed = (int) $row->id;
		}
	}

	$by_id      = array_flip( $fields );
	$numerators = [];
	foreach ( $rows as $row ) {
		if ( 'number' !== $row->type ) {
			continue;
		}
		$opts = maybe_unserialize( $row->field_options );
		$calc = (string) ( $opts['calc'] ?? '' );
		if ( $diagnosed && preg_match( '~\[(\d+)\]\s*\*\s*100\)\s*/\s*\[' . $diagnosed . '\]~', $calc, $m ) && isset( $by_id[ (int) $m[1] ] ) ) {
			$numerators[] = $by_id[ (int) $m[1] ];
		}
	}

	$areas = array_map(
		fn( $k ) => HCP_MCA_V2_FIELD_KEY_PREFIX . $k,
		[ 'or0v0', 'nl84o', 'qx8vp', 'y8oa5', 'jyxn', 'pjtj2' ]
	);

	return [
		'fields'           => $fields,
		'diagnosedCounts'  => array_values( array_unique( $numerators ) ),
		'improvementAreas' => $areas,
	];
}

<?php
/**
 * CAPH0150: the 2026 evaluation header logo keeps the 250px width the
 * legacy header used. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 evaluation header logo at 250px wide.',

	'up' => function (): string {
		$field = FrmField::getOne( HCP_MCA_V2_FIELD_KEY_PREFIX . '4ix3v2' );
		if ( ! $field ) {
			throw new \RuntimeException( 'evaluation header field not found' );
		}
		$old = '[hcp_mca_cpd_logo variant="v2" type="audit" class="mb-lg block"]';
		$new = '[hcp_mca_cpd_logo variant="v2" type="audit" class="mb-lg block" style="width:250px"]';
		if ( false !== strpos( $field->description, $new ) ) {
			return 'already sized';
		}
		if ( 1 !== substr_count( $field->description, $old ) ) {
			throw new \RuntimeException( 'logo shortcode not found in the evaluation header' );
		}
		FrmField::update( (int) $field->id, [ 'description' => str_replace( $old, $new, $field->description ) ] );
		FrmField::delete_form_transient( (int) $field->form_id );
		FrmForm::clear_form_cache();
		return 'evaluation header logo sized';
	},
];

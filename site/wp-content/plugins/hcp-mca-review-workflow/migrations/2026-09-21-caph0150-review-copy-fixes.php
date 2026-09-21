<?php
/**
 * CAPH0150: copy fixes from the tester review of the 2026 audit walkthrough.
 *
 * Each entry is [ field key suffix, column, exact old text, new text ] and
 * is skipped once the new text is present. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 audit copy fixes from tester review (1B cohort bold, BMI superscript, glyceryl trinitrate).',

	'up' => function (): string {
		$fixes = [
			[ 'p9mkv', 'description',
				'<em>Find your total patient cohort by using',
				'<em>Find your <strong>total patient cohort</strong> by using' ],
			[ 'cs1-presentation', 'description',
				'(BMI 28.5 kg/m2)',
				'(BMI 28.5 kg/m²)' ],
			[ 'cs2-presentation', 'description',
				'(BMI 24.9 kg/m2)',
				'(BMI 24.9 kg/m²)' ],
			[ 'cs1-management', 'description',
				'starting the glyceryl nitrate,',
				'starting the glyceryl trinitrate,' ],
		];
		$notes = [];
		$forms = [];
		foreach ( $fixes as [ $suffix, $column, $old, $new ] ) {
			$key   = HCP_MCA_V2_FIELD_KEY_PREFIX . $suffix;
			$field = FrmField::getOne( $key );
			if ( ! $field ) {
				throw new \RuntimeException( "field {$key} not found" );
			}
			$current = (string) $field->{$column};
			if ( false !== strpos( $current, $new ) ) {
				$notes[] = "{$key} already updated";
				continue;
			}
			if ( 1 !== substr_count( $current, $old ) ) {
				throw new \RuntimeException( "{$key}: expected text not found exactly once" );
			}
			FrmField::update( (int) $field->id, [ $column => str_replace( $old, $new, $current ) ] );
			$forms[ (int) $field->form_id ] = true;
			$notes[] = "{$key} updated";
		}
		foreach ( array_keys( $forms ) as $form_id ) {
			FrmField::delete_form_transient( $form_id );
		}
		FrmForm::clear_form_cache();
		return implode( '; ', $notes );
	},
];

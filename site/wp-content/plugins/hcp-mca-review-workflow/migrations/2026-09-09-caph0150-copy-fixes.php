<?php
/**
 * CAPH0150: copy fixes from the hand-test of the 2026 audit form.
 *
 * Each entry is [ field key suffix, column, exact old text, new text ] and
 * is skipped once the new text is present. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 audit copy fixes from review (Step 1B diagnosed-count intro and label).',

	'up' => function (): string {
		$fixes = [
			[ '8eger', 'description',
				'<em>From this total patient cohort, now identify those who were diagnosed with anal fissure.</em>',
				'<em>From this total patient cohort, now identify those who were <strong>diagnosed with anal fissure</strong>.</em>' ],
			[ '9962s', 'name',
				'Record the number',
				'Record the number of patients diagnosed with anal fissure' ],
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

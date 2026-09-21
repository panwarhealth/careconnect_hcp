<?php
/**
 * CAPH0150: case study section headings read "Patient N of 3" so the user
 * knows how many patients follow (later patients are hidden until the
 * previous one is finished). Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: case study section headings read "Patient N of 3".',

	'up' => function (): string {
		$notes = [];
		$forms = [];
		for ( $n = 1; $n <= 3; $n++ ) {
			$key   = HCP_MCA_V2_FIELD_KEY_PREFIX . "cs{$n}-section";
			$field = FrmField::getOne( $key );
			if ( ! $field ) {
				throw new \RuntimeException( "field {$key} not found" );
			}
			$new = "Patient {$n} of 3";
			if ( $field->name === $new ) {
				$notes[] = "{$key} already updated";
				continue;
			}
			FrmField::update( (int) $field->id, [ 'name' => $new ] );
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

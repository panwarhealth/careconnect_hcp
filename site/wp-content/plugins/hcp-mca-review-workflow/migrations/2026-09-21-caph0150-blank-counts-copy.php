<?php
/**
 * CAPH0150: Step 1B count intros say a blank box is recorded as zero.
 *
 * Blank count boxes are filled with 0 when the user moves forward
 * (assets/js/audit-v2.js), so the copy no longer asks for zeros. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: Step 1B count intros say a blank box is recorded as zero.',

	'up' => function (): string {
		$old = 'If no patients are relevant, enter the number zero (0).';
		$new = 'If no patients are relevant, leave the box blank and it will be recorded as zero.';
		$notes = [];
		$forms = [];
		foreach ( [ 'zl5bk', 'z5gyk', 'vpcpm', 'a68dp' ] as $suffix ) {
			$key   = HCP_MCA_V2_FIELD_KEY_PREFIX . $suffix;
			$field = FrmField::getOne( $key );
			if ( ! $field ) {
				throw new \RuntimeException( "field {$key} not found" );
			}
			$current = (string) $field->description;
			if ( false !== strpos( $current, $new ) ) {
				$notes[] = "{$key} already updated";
				continue;
			}
			if ( 1 !== substr_count( $current, $old ) ) {
				throw new \RuntimeException( "{$key}: expected text not found exactly once" );
			}
			FrmField::update( (int) $field->id, [ 'description' => str_replace( $old, $new, $current ) ] );
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

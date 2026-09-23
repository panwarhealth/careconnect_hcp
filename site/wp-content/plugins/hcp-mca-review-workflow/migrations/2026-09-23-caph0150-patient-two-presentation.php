<?php
/**
 * CAPH0150: Patient 2 presentation wording from the second tester review.
 *
 * Skipped once the new text is present. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 audit Patient 2 presentation reads "presents with regular anal pain".',

	'up' => function (): string {
		$key   = HCP_MCA_V2_FIELD_KEY_PREFIX . 'cs2-presentation';
		$old   = 'presents with a regular anal pain';
		$new   = 'presents with regular anal pain';
		$field = FrmField::getOne( $key );
		if ( ! $field ) {
			throw new \RuntimeException( "field {$key} not found" );
		}
		$current = (string) $field->description;
		if ( false !== strpos( $current, $new ) ) {
			return "{$key} already updated";
		}
		if ( 1 !== substr_count( $current, $old ) ) {
			throw new \RuntimeException( "{$key}: expected text not found exactly once" );
		}
		FrmField::update( (int) $field->id, [ 'description' => str_replace( $old, $new, $current ) ] );
		FrmField::delete_form_transient( (int) $field->form_id );
		FrmForm::clear_form_cache();
		return "{$key} updated";
	},
];

<?php
/**
 * CAPH0150: patient counts in the 2026 audit are whole numbers. Sets
 * step=1 on every typed number field of the audit form (calculated
 * percentage fields keep step=any). Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 audit count fields accept whole numbers only (step=1).',

	'up' => function (): string {
		global $wpdb;
		$variant = hcp_mca_variants( true )[ HCP_MCA_VARIANT_V2 ] ?? null;
		if ( ! $variant ) {
			throw new \RuntimeException( '2026 variant not provisioned; run the provision migration first.' );
		}
		$form_id = (int) $variant['audit_form'];
		$rows    = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, field_options FROM {$wpdb->prefix}frm_fields WHERE form_id = %d AND type = 'number'",
			$form_id
		) );
		$updated = 0;
		foreach ( $rows as $row ) {
			$opts = maybe_unserialize( $row->field_options );
			if ( ! is_array( $opts ) || '' !== (string) ( $opts['calc'] ?? '' ) ) {
				continue;
			}
			if ( '1' === (string) ( $opts['step'] ?? '' ) ) {
				continue;
			}
			$opts['step'] = '1';
			FrmField::update( (int) $row->id, [ 'field_options' => $opts ] );
			$updated++;
		}
		FrmField::delete_form_transient( $form_id );
		FrmForm::clear_form_cache();
		return "{$updated} count fields set to whole numbers";
	},
];

<?php
/**
 * CAPH0150: carry each case-study Check block's question keys as classes.
 *
 * The content migration wrote them as a data-fields attribute, which
 * Formidable strips when it saves a field description, so the Check buttons
 * could never see an answer. Fresh installs now get the classes from the
 * content migration; this repairs forms provisioned before that change.
 *
 * Idempotent: a block that already carries hcp-check-- classes is left alone.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: Check blocks on the 2026 case studies carry their question keys as classes (data-fields was stripped on save).',

	'up' => function (): string {
		global $wpdb;

		$audit_form = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}frm_forms WHERE form_key = %s",
			HCP_MCA_V2_AUDIT_FORM_KEY
		) );
		if ( ! $audit_form ) {
			throw new \RuntimeException( '2026 audit form not found; run the provision migration first.' );
		}

		// Questions whose Check also validates a free-text "why" box.
		$with_why = [ 'diagnosis', 'side-effects' ];

		$fields = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, field_key, description FROM {$wpdb->prefix}frm_fields
			 WHERE form_id = %d AND type = 'html' AND field_key LIKE %s",
			$audit_form, $wpdb->esc_like( HCP_MCA_V2_FIELD_KEY_PREFIX . 'cs' ) . '%-check'
		), ARRAY_A );

		$fixed = 0;
		$kept  = 0;
		foreach ( $fields as $field ) {
			if ( false !== strpos( $field['description'], 'hcp-check--' ) ) {
				$kept++;
				continue;
			}
			$question = substr( $field['field_key'], 0, -strlen( '-check' ) );
			$keys     = [ $question ];
			foreach ( $with_why as $q ) {
				if ( substr( $question, -strlen( $q ) ) === $q ) {
					$keys[] = $question . '-why';
				}
			}
			$classes = 'hcp-check';
			foreach ( $keys as $key ) {
				$classes .= ' hcp-check--' . $key;
			}
			$new = preg_replace(
				'/<div class="hcp-check"(\s+data-fields="[^"]*")?>/',
				'<div class="' . esc_attr( $classes ) . '">',
				$field['description'],
				1
			);
			if ( $new === $field['description'] ) {
				continue;
			}
			FrmField::update( (int) $field['id'], [ 'description' => $new ] );
			$fixed++;
		}

		return "{$fixed} Check block(s) re-marked, {$kept} already carried classes";
	},
];

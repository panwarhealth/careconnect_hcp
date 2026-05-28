<?php
/**
 * Hide the RACGP number field (ID 4401) on Form 2 (main site "Registration").
 *
 * The RACGP number is no longer collected at registration — it is captured
 * via the modal when the user first clicks "Start Learning Module". Hiding
 * rather than deleting preserves any historical field data in existing entries.
 *
 * Idempotent: re-running detects the field is already hidden and exits.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Hide RACGP number field on Form 2 so new registrations no longer ask for it at sign-up.',

	'up' => function (): string {
		global $wpdb;

		$field_id = 4401;

		$field = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, form_id, field_options FROM {$wpdb->prefix}frm_fields WHERE id = %d",
			$field_id
		) );

		if ( ! $field ) {
			throw new \RuntimeException( "RACGP field {$field_id} not found in frm_fields." );
		}

		if ( (int) $field->form_id !== 2 ) {
			throw new \RuntimeException( "Field {$field_id} belongs to form {$field->form_id}, not Form 2. Aborting." );
		}

		$options = maybe_unserialize( $field->field_options );
		if ( ! is_array( $options ) ) {
			throw new \RuntimeException( "field_options for field {$field_id} could not be unserialized." );
		}

		if ( isset( $options['hide_field'] ) && $options['hide_field'] == 1 ) {
			return "RACGP field {$field_id} on Form 2 is already hidden. No change.";
		}

		$options['hide_field']       = 1;
		$options['hide_field_cond']  = [];
		$options['required']         = 0;

		$result = $wpdb->update(
			$wpdb->prefix . 'frm_fields',
			[ 'field_options' => serialize( $options ) ],
			[ 'id' => $field_id ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( $result === false ) {
			throw new \RuntimeException( "wpdb->update failed for field {$field_id}." );
		}

		return "Hid RACGP field {$field_id} on Form 2 and set required=0.";
	},
];

<?php
/**
 * CAPH0150: re-key inline scripts copied into the 2026 audit form.
 *
 * Two fields on the legacy audit carry a <script> in their custom HTML that
 * addresses other fields by element id (field_<key>, frm_field_<id>_container).
 * FrmForm::duplicate copies the HTML verbatim, so on the 2026 form those ids
 * still point at the legacy fields, which are not on the page:
 *
 *  - Step 1B (A) section heading: sets the hidden "gatekeeper" text field to
 *    VALID once three improvement areas and their reasons are filled. Without
 *    it the required gatekeeper stays blank and Step 1B can never be passed.
 *  - Step 1B (C) "Other – please specify" risk factor: makes the number box
 *    required when the text is filled and vice versa.
 *
 * Idempotent: only un-prefixed legacy references are rewritten.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: point inline scripts on the 2026 audit form at the 2026 field ids (Step 1B gatekeeper, Other risk factor pairing).',

	'up' => function (): string {
		global $wpdb;

		$audit_form = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}frm_forms WHERE form_key = %s",
			HCP_MCA_V2_AUDIT_FORM_KEY
		) );
		if ( ! $audit_form ) {
			throw new \RuntimeException( '2026 audit form not found; run the provision migration first.' );
		}

		$prefix = HCP_MCA_V2_FIELD_KEY_PREFIX;

		// Legacy key => legacy id, and legacy key => 2026 id, for every legacy field with a 2026 copy.
		$legacy = $wpdb->get_results(
			"SELECT id, field_key FROM {$wpdb->prefix}frm_fields WHERE form_id IN (" . HCP_MCA_AUDIT_FORM_ID . ', ' . HCP_MCA_REPEATER_FORM_ID . ')',
			ARRAY_A
		);
		$copies = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.id, f.field_key FROM {$wpdb->prefix}frm_fields f
			 JOIN {$wpdb->prefix}frm_forms fr ON fr.id = f.form_id
			 WHERE ( f.form_id = %d OR fr.parent_form_id = %d ) AND f.field_key LIKE %s",
			$audit_form, $audit_form, $wpdb->esc_like( $prefix ) . '%'
		), ARRAY_A );

		$copy_id_by_key = [];
		foreach ( $copies as $row ) {
			$copy_id_by_key[ substr( $row['field_key'], strlen( $prefix ) ) ] = (int) $row['id'];
		}
		$id_map = [];
		$keys   = [];
		foreach ( $legacy as $row ) {
			if ( isset( $copy_id_by_key[ $row['field_key'] ] ) ) {
				$id_map[ (int) $row['id'] ] = $copy_id_by_key[ $row['field_key'] ];
				$keys[]                     = $row['field_key'];
			}
		}
		// Longest keys first so a short key never matches inside a longer one.
		usort( $keys, fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );

		$rewrite = function ( string $html ) use ( $keys, $id_map, $prefix ): string {
			foreach ( $keys as $key ) {
				$q    = preg_quote( $key, '/' );
				$html = preg_replace( "/(?<![\\w-])(field_|err_field_|frm_desc_field_|frm_error_field_)({$q})(?![\\w-])/", '$1' . $prefix . '$2', $html );
				$html = preg_replace( "/(?<![\\w-])(field_)({$q})(-\\d+)(?![\\w-])/", '$1' . $prefix . '$2$3', $html );
			}
			foreach ( $id_map as $old => $new ) {
				$html = preg_replace( "/(?<![\\w-])frm_field_{$old}_container(?![\\w-])/", "frm_field_{$new}_container", $html );
			}
			return $html;
		};

		// Source of truth is the legacy field's HTML: rebuild the 2026 copy from
		// it every time, so a copy that lost its <script> to KSES is repaired.
		$sources = $wpdb->get_results(
			"SELECT id, field_key, field_options FROM {$wpdb->prefix}frm_fields
			 WHERE form_id IN (" . HCP_MCA_AUDIT_FORM_ID . ', ' . HCP_MCA_REPEATER_FORM_ID . ") AND field_options LIKE '%<script%'",
			ARRAY_A
		);

		// FrmField::update strips <script> unless the current user may post
		// unfiltered HTML; the migration runner may be a CLI or a token-guarded
		// script with no user, so write as an administrator and restore after.
		$previous_user = get_current_user_id();
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID' ] );
			if ( ! $admins ) {
				throw new \RuntimeException( 'no administrator account available to write field HTML' );
			}
			wp_set_current_user( (int) $admins[0] );
		}
		kses_remove_filters();

		$notes = [];
		foreach ( $sources as $source ) {
			$options = maybe_unserialize( $source['field_options'] );
			if ( empty( $options['custom_html'] ) || false === stripos( $options['custom_html'], '<script' ) ) {
				continue;
			}
			$copy_id = $copy_id_by_key[ $source['field_key'] ] ?? 0;
			if ( ! $copy_id ) {
				$notes[] = "{$source['field_key']}: no 2026 copy";
				continue;
			}
			$copy         = FrmField::getOne( $copy_id );
			$copy_options = (array) $copy->field_options;
			$wanted       = $rewrite( $options['custom_html'] );
			if ( ( $copy_options['custom_html'] ?? '' ) === $wanted ) {
				$notes[] = "{$copy->field_key}: already re-keyed";
				continue;
			}
			$copy_options['custom_html'] = $wanted;
			FrmField::update( $copy_id, [ 'field_options' => $copy_options ] );
			$notes[] = "{$copy->field_key}: script re-keyed";
		}

		kses_init_filters();
		wp_set_current_user( $previous_user );

		return $notes ? implode( '; ', $notes ) : 'no inline scripts found on the 2026 form';
	},
];

<?php
/**
 * Form 161 field 5169 "Proportion of patient cohort with anal fissure" —
 * remove minnum=1 floor. The field is a read-only auto-calc of
 * ([5121] * 100) / [5105]. HCPs with large denominators legitimately get
 * sub-1% results (e.g. 29 / 30274 = 0.1%) and were silently blocked from
 * advancing past Step 1B. Reported by Elissa Armitage 2026-04-23.
 *
 * Also clears Formidable's per-form field transients so any stale render
 * caches are invalidated on next page load.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Form 161 field 5169: minnum 1 -> 0, flush field transients',
	'up'          => function (): string {
		global $wpdb;

		$field_id = 5169;
		$messages = [];

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT field_options FROM {$wpdb->prefix}frm_fields WHERE id = %d",
			$field_id
		) );

		if ( ! $row ) {
			return "Field {$field_id} not found; aborted.";
		}

		$opts = maybe_unserialize( $row->field_options );
		if ( ! is_array( $opts ) ) {
			return "Field {$field_id} options not an array; aborted.";
		}

		$current = $opts['minnum'] ?? null;
		if ( (string) $current === '0' ) {
			$messages[] = "Field {$field_id} minnum already '0' (no-op)";
		} else {
			$opts['minnum'] = '0';
			$updated        = $wpdb->update(
				$wpdb->prefix . 'frm_fields',
				[ 'field_options' => serialize( $opts ) ],
				[ 'id' => $field_id ]
			);
			if ( false === $updated ) {
				throw new \RuntimeException( "DB update failed for field {$field_id}." );
			}
			$messages[] = "Field {$field_id} minnum: '" . (string) $current . "' -> '0'";
		}

		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->prefix}options
			 WHERE option_name LIKE '_transient_frm_form_fields_%'
			    OR option_name LIKE '_transient_timeout_frm_form_fields_%'"
		);
		$messages[] = "Formidable field transients purged ({$deleted} rows)";

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$messages[] = 'Object cache flushed';
		}

		return implode( '; ', $messages ) . '.';
	},
];

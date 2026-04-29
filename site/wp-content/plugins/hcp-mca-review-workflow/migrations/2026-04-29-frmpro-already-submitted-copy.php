<?php
/**
 * Formidable Pro global "already submitted" message — change "module homepage"
 * to "audit homepage" so the link text reflects what users are returning to.
 * Reported by PM 2026-04-29.
 *
 * The string lives in option `frmpro_options` (FrmProSettings object), key
 * `already_submitted`, and is rendered when a single-entry-per-user form
 * (e.g. form 209 Activity evaluation) is revisited after submission.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'frmpro_options.already_submitted: "module homepage" -> "audit homepage"',
	'up'          => function (): string {
		$settings = get_option( 'frmpro_options' );

		if ( ! is_object( $settings ) || ! isset( $settings->already_submitted ) ) {
			return 'frmpro_options not loaded as object or missing already_submitted; aborted.';
		}

		$current = (string) $settings->already_submitted;

		if ( strpos( $current, 'module homepage' ) === false ) {
			return 'already_submitted does not contain "module homepage" (no-op).';
		}

		$settings->already_submitted = str_replace( 'module homepage', 'audit homepage', $current );
		update_option( 'frmpro_options', $settings );
		delete_transient( 'frmpro_options' );

		return 'already_submitted updated; frmpro_options transient cleared.';
	},
];

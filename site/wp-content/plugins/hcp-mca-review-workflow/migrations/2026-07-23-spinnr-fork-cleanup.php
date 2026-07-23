<?php
/**
 * Spinnr fork: delete options that only served the removed SPINNR service
 * integration (licence key, licence-check throttle, editor lock). The raw-HTML
 * type list formerly in lock_editor is now hardcoded as WP_SPINNR_RAW_TYPES
 * in the theme's inc/config.php.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Spinnr fork: delete wp_api_key, spinnr_last_check and lock_editor options.',
	'up'          => function (): string {
		$deleted = [];
		foreach ( [ 'wp_api_key', 'spinnr_last_check', 'lock_editor' ] as $option ) {
			$value = get_option( $option, null );
			if ( null !== $value ) {
				delete_option( $option );
				$deleted[] = "{$option} (was: " . ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . ')';
			}
		}
		return $deleted ? 'Deleted: ' . implode( '; ', $deleted ) : 'Nothing to delete (already clean).';
	},
];

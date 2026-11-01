<?php
/**
 * Order Samples (form 55): the order email and entry views show each chosen
 * brand's name under its logo, so the order can be read with images blocked.
 * The form's own cards stay logo-only via hcp_order_samples_cards_logo_only()
 * in includes/frontend.php, which must be deployed before this runs.
 *
 * Idempotent. To roll back, set hide_image_text back to 1 on field 1756.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Order Samples (form 55): brand names under the logos in the order email (CAPH0141)',
	'up'          => function (): string {
		global $wpdb;

		if ( ! function_exists( 'hcp_order_samples_cards_logo_only' ) ) {
			throw new RuntimeException( 'Deploy includes/frontend.php first, or the form cards would show text.' );
		}

		$fields  = $wpdb->prefix . 'frm_fields';
		$options = maybe_unserialize( $wpdb->get_var( "SELECT field_options FROM {$fields} WHERE id = 1756" ) );

		if ( ! is_array( $options ) ) {
			throw new RuntimeException( 'Field 1756 not found.' );
		}

		if ( empty( $options['hide_image_text'] ) ) {
			return 'Brand names already shown.';
		}

		$options['hide_image_text'] = 0;

		$updated = $wpdb->update( $fields, [ 'field_options' => maybe_serialize( $options ) ], [ 'id' => 1756 ] );

		if ( false === $updated ) {
			throw new RuntimeException( 'Failed to save field 1756: ' . $wpdb->last_error );
		}

		wp_cache_delete( 1756, 'frm_field' );

		if ( class_exists( 'FrmField' ) ) {
			FrmField::delete_form_transient( 55 );
		}

		return 'Brand names now shown under the logos.';
	},
];

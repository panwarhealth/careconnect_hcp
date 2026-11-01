<?php
/**
 * Order Samples (form 55): Theratears brand card on the Select Brands step, so
 * the brand shown in the logo image and logo strip can be ordered.
 *
 * The image ships as uploads/2026/10/caph0141/theratears-samples.png and must
 * be uploaded before this runs. Orders show the card's value in the existing
 * sample order email.
 *
 * Idempotent. To roll back, remove the Theratears option from field 1756.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Order Samples (form 55): add the Theratears brand card (CAPH0141)',
	'up'          => function (): string {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$relative = '2026/10/caph0141/theratears-samples.png';
		$path     = wp_upload_dir()['basedir'] . '/' . $relative;
		$fields   = $wpdb->prefix . 'frm_fields';

		if ( ! file_exists( $path ) ) {
			throw new RuntimeException( "Missing image {$relative}; upload it first." );
		}

		$options = maybe_unserialize( $wpdb->get_var( "SELECT options FROM {$fields} WHERE id = 1756" ) );

		if ( ! is_array( $options ) ) {
			throw new RuntimeException( 'Field 1756 has no options.' );
		}

		foreach ( $options as $option ) {
			if ( is_array( $option ) && 'Theratears' === ( $option['value'] ?? '' ) ) {
				return 'Theratears card already present.';
			}
		}

		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			$relative
		) );

		if ( ! $id ) {
			$id = wp_insert_attachment(
				[
					'post_title'     => 'Theratears samples',
					'post_mime_type' => 'image/png',
					'post_status'    => 'inherit',
				],
				$path
			);

			if ( ! $id || is_wp_error( $id ) ) {
				throw new RuntimeException( 'Failed to create the Theratears attachment.' );
			}

			update_post_meta( $id, '_wp_attached_file', $relative );
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
		}

		$options[] = [
			'label' => 'Theratears',
			'value' => 'Theratears',
			'image' => (string) $id,
		];

		$updated = $wpdb->update( $fields, [ 'options' => maybe_serialize( $options ) ], [ 'id' => 1756 ] );

		if ( false === $updated ) {
			throw new RuntimeException( 'Failed to save field 1756: ' . $wpdb->last_error );
		}

		wp_cache_delete( 1756, 'frm_field' );

		if ( class_exists( 'FrmField' ) ) {
			FrmField::delete_form_transient( 55 );
		}

		return "Theratears card added (attachment {$id}).";
	},
];

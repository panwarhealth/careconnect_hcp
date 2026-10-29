<?php
/**
 * Order Samples (form 55): lighter images. The logged-out preview screenshot,
 * the Care Package range shot and the Select Brands logos switch to WebP sized
 * for how they display (range and logos show at 400px, so 800px covers 2x
 * screens): about 823 KB down to 137 KB.
 *
 * The images ship as files in uploads/2026/10/caph0141/ and must be uploaded
 * before this runs. Attachments are reused if they already exist. Runs after
 * 2026-10-14-caph0141-fess-logo.
 *
 * Idempotent. To roll back, point field 1754 back at the samples-range.jpg and
 * sample-brand-logos-fess.png attachments and the page image back to
 * order-samples-preview-fess.png.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Order Samples (form 55): preview, range shot and brand logos as sized WebP (CAPH0141)',
	'up'          => function (): string {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$subdir  = '2026/10/caph0141/';
		$uploads = wp_upload_dir();
		$fields  = $wpdb->prefix . 'frm_fields';
		$notes   = [];

		$attach = function ( string $file, string $title ) use ( $wpdb, $subdir, $uploads, &$notes ): int {
			$path = $uploads['basedir'] . '/' . $subdir . $file;

			if ( ! file_exists( $path ) ) {
				throw new RuntimeException( "Missing image {$subdir}{$file}; upload it first." );
			}

			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$subdir . $file
			) );

			if ( $existing ) {
				$notes[] = "{$file} already attached (ID {$existing})";
				return $existing;
			}

			$id = wp_insert_attachment(
				[
					'post_title'     => $title,
					'post_mime_type' => 'image/webp',
					'post_status'    => 'inherit',
				],
				$path
			);

			if ( ! $id || is_wp_error( $id ) ) {
				throw new RuntimeException( "Failed to create attachment for {$file}." );
			}

			update_post_meta( $id, '_wp_attached_file', $subdir . $file );
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
			$notes[] = "{$file} attached (ID {$id})";

			return $id;
		};

		// Field 1754 option, by label prefix => [ file, title ].
		$swaps = [
			'Care Package'      => [ 'samples-range-800.webp', 'Care range samples' ],
			'<h4>Select Brands' => [ 'sample-brand-logos-fess.webp', 'Sample order brand logos' ],
		];

		$options = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT options FROM {$fields} WHERE id = %d", 1754 ) ) );

		foreach ( $swaps as $label => [ $file, $title ] ) {
			$id    = $attach( $file, $title );
			$index = null;

			foreach ( (array) $options as $key => $option ) {
				$text = wp_strip_all_tags( $option['label'] ?? '' );
				if ( is_array( $option ) && isset( $option['image'] ) && ( 0 === strpos( $option['label'] ?? '', $label ) || 0 === strpos( $text, wp_strip_all_tags( $label ) ) ) ) {
					$index = $key;
					break;
				}
			}

			if ( null === $index ) {
				throw new RuntimeException( "Field 1754 option \"{$label}\" not found." );
			}

			if ( (int) $options[ $index ]['image'] === $id ) {
				$notes[] = "field 1754 \"{$label}\" already uses {$file}";
				continue;
			}

			$options[ $index ]['image'] = $id;
			$notes[] = "field 1754 \"{$label}\" image set to {$id}";
		}

		if ( false === $wpdb->update( $fields, [ 'options' => maybe_serialize( $options ) ], [ 'id' => 1754 ] ) ) {
			throw new RuntimeException( 'Failed to update field 1754.' );
		}

		if ( class_exists( 'FrmField' ) ) {
			FrmField::delete_form_transient( 55 );
		}

		$page = get_post( 145 );

		if ( ! $page ) {
			throw new RuntimeException( 'Order Samples page (145) not found.' );
		}

		$content = $page->post_content;

		if ( false !== strpos( $content, 'order-samples-preview-fess.webp' ) ) {
			$notes[] = 'page already uses order-samples-preview-fess.webp';
		} elseif ( 1 !== substr_count( $content, 'caph0141/order-samples-preview-fess.png' ) ) {
			throw new RuntimeException( 'Preview image (order-samples-preview-fess.png) not found once on page 145.' );
		} else {
			$attach( 'order-samples-preview-fess.webp', 'Order samples preview' );
			$content = str_replace( 'caph0141/order-samples-preview-fess.png', 'caph0141/order-samples-preview-fess.webp', $content );
			kses_remove_filters();
			$updated = wp_update_post( [ 'ID' => 145, 'post_content' => wp_slash( $content ) ], true );
			kses_init_filters();

			if ( is_wp_error( $updated ) ) {
				throw new RuntimeException( 'Failed to update page 145: ' . $updated->get_error_message() );
			}

			$notes[] = 'page preview swapped to order-samples-preview-fess.webp';
		}

		return implode( '; ', $notes );
	},
];

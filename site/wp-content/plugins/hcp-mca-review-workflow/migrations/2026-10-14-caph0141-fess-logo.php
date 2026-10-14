<?php
/**
 * Order Samples (form 55): latest FESS logo everywhere it appears. The Select
 * Brands logos image, the Nasal & Sinus Health brand card, the page's brand
 * strip and the logged-out preview screenshot all swap to the new versions.
 *
 * The images ship as files in uploads/2026/10/caph0141/ and must be uploaded
 * before this runs. Attachments are reused if they already exist. Runs after
 * 2026-10-02-caph0141-samples-range-shot.
 *
 * Idempotent. To roll back, set field 1754 option 1 back to the
 * sample-brand-logos.png attachment, field 1756 Nasal & Sinus Health back to
 * 41531, and swap the two page images back to 2023/08/FESS_Logo.jpg and
 * caph0141/order-samples-preview.png.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Order Samples (form 55): latest FESS logo in the brand images, brand card, logo strip and preview (CAPH0141)',
	'up'          => function (): string {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$subdir  = '2026/10/caph0141/';
		$uploads = wp_upload_dir();
		$fields  = $wpdb->prefix . 'frm_fields';
		$notes   = [];

		$attach = function ( string $file, string $mime, string $title ) use ( $wpdb, $subdir, $uploads, &$notes ): int {
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
					'post_mime_type' => $mime,
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

		// Field => [ option label prefix, file, mime, title ].
		$swaps = [
			1754 => [ '<h4>Select Brands', 'sample-brand-logos-fess.png', 'image/png', 'Sample order brand logos' ],
			1756 => [ 'Nasal & Sinus Health', 'fess-samples.png', 'image/png', 'FESS samples' ],
		];

		foreach ( $swaps as $field_id => [ $label, $file, $mime, $title ] ) {
			$id      = $attach( $file, $mime, $title );
			$options = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT options FROM {$fields} WHERE id = %d", $field_id ) ) );
			$index   = null;

			foreach ( (array) $options as $key => $option ) {
				if ( is_array( $option ) && isset( $option['image'] ) && 0 === strpos( $option['label'] ?? '', $label ) ) {
					$index = $key;
					break;
				}
			}

			if ( null === $index ) {
				throw new RuntimeException( "Field {$field_id} has no image choice labelled \"{$label}\"." );
			}

			$options[ $index ]['image'] = (string) $id;

			if ( false === $wpdb->update( $fields, [ 'options' => maybe_serialize( $options ) ], [ 'id' => $field_id ] ) ) {
				throw new RuntimeException( "Failed to write options for field {$field_id}: {$wpdb->last_error}" );
			}

			wp_cache_delete( $field_id, 'frm_field' );
			$notes[] = "field {$field_id} \"{$label}\" image set to {$id}";
		}

		if ( class_exists( 'FrmField' ) ) {
			FrmField::delete_form_transient( 55 );
		}

		$page = get_post( 145 );

		if ( ! $page ) {
			throw new RuntimeException( 'Order Samples page (145) not found.' );
		}

		// Old src pattern => new file.
		$images = [
			'/(<img[^>]*src=")[^"]*2023\/08\/FESS_Logo\.jpg(")/'                           => 'fess-logo.jpg',
			'/(<img[^>]*src=")[^"]*2026\/10\/caph0141\/order-samples-preview\.png(")/' => 'order-samples-preview-fess.png',
		];

		$content = $page->post_content;

		foreach ( $images as $pattern => $file ) {
			if ( ! file_exists( $uploads['basedir'] . '/' . $subdir . $file ) ) {
				throw new RuntimeException( "Missing image {$subdir}{$file}; upload it first." );
			}

			if ( false !== strpos( $content, $subdir . $file ) ) {
				$notes[] = "page already uses {$file}";
			} elseif ( ! preg_match( $pattern, $content ) ) {
				throw new RuntimeException( "Image to replace with {$file} not found on page 145." );
			} else {
				$content = preg_replace( $pattern, '${1}' . esc_url( $uploads['baseurl'] . '/' . $subdir . $file ) . '$2', $content, 1 );
				$notes[] = "page image swapped to {$file}";
			}
		}

		if ( $content !== $page->post_content ) {
			kses_remove_filters();
			$updated = wp_update_post( [ 'ID' => 145, 'post_content' => $content ], true );
			kses_init_filters();

			if ( is_wp_error( $updated ) ) {
				throw new RuntimeException( 'Failed to update page 145: ' . $updated->get_error_message() );
			}
		}

		return implode( '; ', $notes );
	},
];

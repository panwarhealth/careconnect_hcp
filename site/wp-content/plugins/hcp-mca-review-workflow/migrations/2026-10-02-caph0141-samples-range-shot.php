<?php
/**
 * Order Samples (form 55): new range shot on the Care Package choice and a
 * brand logos image that includes Theratears on the Select Brands choice.
 * Theratears is also added to the page's brand strip after Aci-Jel, and the
 * logged-out preview screenshot of the form is replaced with the new version.
 *
 * The images ship as files in uploads/2026/10/caph0141/ and must be uploaded
 * before this runs. Attachments are reused if they already exist.
 *
 * Idempotent. To roll back, set the field's option images back to 83933
 * (Care Package) and 83934 (Select Brands).
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Order Samples (form 55): new range shot and Theratears in the brand logos (CAPH0141)',
	'up'          => function (): string {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$field_id = 1754;
		$subdir   = '2026/10/caph0141/';
		$images   = [
			0 => [ 'samples-range.jpg', 'image/jpeg', 'Care range samples' ],
			1 => [ 'sample-brand-logos.png', 'image/png', 'Sample order brand logos' ],
		];

		$uploads = wp_upload_dir();
		$ids     = [];
		$notes   = [];

		foreach ( $images as $index => [ $file, $mime, $title ] ) {
			$path = $uploads['basedir'] . '/' . $subdir . $file;

			if ( ! file_exists( $path ) ) {
				throw new RuntimeException( "Missing image {$subdir}{$file}; upload it first." );
			}

			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$subdir . $file
			) );

			if ( $existing ) {
				$ids[ $index ] = $existing;
				$notes[]       = "{$file} already attached (ID {$existing})";
				continue;
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

			$ids[ $index ] = $id;
			$notes[]       = "{$file} attached (ID {$id})";
		}

		$fields  = $wpdb->prefix . 'frm_fields';
		$options = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT options FROM {$fields} WHERE id = %d", $field_id ) ) );

		if ( ! is_array( $options ) || ! isset( $options[0]['image'], $options[1]['image'] ) ) {
			throw new RuntimeException( "Field {$field_id} options are not the expected image choices." );
		}

		foreach ( $ids as $index => $id ) {
			$options[ $index ]['image'] = (string) $id;
		}

		if ( false === $wpdb->update( $fields, [ 'options' => maybe_serialize( $options ) ], [ 'id' => $field_id ] ) ) {
			throw new RuntimeException( "Failed to write options for field {$field_id}: {$wpdb->last_error}" );
		}

		wp_cache_delete( $field_id, 'frm_field' );
		if ( class_exists( 'FrmField' ) ) {
			FrmField::delete_form_transient( 55 );
		}

		$notes[] = "field {$field_id} images set to {$ids[0]} and {$ids[1]}";

		$page = get_post( 145 );

		if ( ! $page ) {
			throw new RuntimeException( 'Order Samples page (145) not found.' );
		}

		foreach ( [ 'theratears-logo.jpg', 'order-samples-preview.png' ] as $file ) {
			if ( ! file_exists( $uploads['basedir'] . '/' . $subdir . $file ) ) {
				throw new RuntimeException( "Missing image {$subdir}{$file}; upload it first." );
			}
		}

		$content = $page->post_content;
		$after   = '/(<img[^>]*AciJel_HeroLogo_Pink\.jpg[^>]*\/>)/';
		$preview = '/(<img[^>]*src=")[^"]*2025\/10\/order-samples\.png(")/';

		if ( false !== strpos( $content, 'theratears-logo.jpg' ) ) {
			$notes[] = 'Theratears already in the page logo strip';
		} elseif ( ! preg_match( $after, $content ) ) {
			throw new RuntimeException( 'Aci-Jel logo not found in the page logo strip.' );
		} else {
			$img     = '<img decoding="async" src="' . esc_url( $uploads['baseurl'] . '/' . $subdir . 'theratears-logo.jpg' ) . '" class="mx-auto mb-0" alt="Theratears" />';
			$content = preg_replace( $after, '$1 ' . $img, $content, 1 );
			$notes[] = 'Theratears added to the page logo strip';
		}

		if ( false !== strpos( $content, 'order-samples-preview.png' ) ) {
			$notes[] = 'logged-out preview already updated';
		} elseif ( ! preg_match( $preview, $content ) ) {
			throw new RuntimeException( 'Logged-out preview image (2025/10/order-samples.png) not found on page 145.' );
		} else {
			$content = preg_replace( $preview, '${1}' . esc_url( $uploads['baseurl'] . '/' . $subdir . 'order-samples-preview.png' ) . '$2', $content, 1 );
			$notes[] = 'logged-out preview updated';
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

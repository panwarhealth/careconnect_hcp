<?php
/**
 * CAPH0150: every place that shows a RACGP activity ID reads it from the
 * variant registry, so the 2026 audit's placeholder "XXX" becomes the real
 * ID with a one-line change once accredited.
 *
 *  - 2026 certificate: blank template artwork (no baked-in ID or hours);
 *    the plugin draws them at render time.
 *  - Activity homepage + public landing page: the Activity ID table cell
 *    renders [hcp_mca_activity_ids].
 *  - 2026 activity evaluation header: "(Activity ID [hcp_mca_activity_id variant="v2"])".
 *
 * Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: activity IDs derive from the variant registry (2026 certificate template, homepage/landing table, evaluation header).',

	'up' => function (): string {
		$notes   = [];
		$variant = hcp_mca_variants( true )[ HCP_MCA_VARIANT_V2 ] ?? null;
		if ( ! $variant ) {
			throw new \RuntimeException( '2026 variant not provisioned; run the provision migration first.' );
		}

		// 1. Certificate template artwork as the 2026 certificate's featured image.
		$template = hcp_mca_certificate_template_path( $variant );
		if ( ! $template ) {
			throw new \RuntimeException( 'certificate template artwork missing from the plugin' );
		}
		$cert_id  = (int) $variant['cert'];
		$existing = (int) get_post_thumbnail_id( $cert_id );
		$marker   = 'hcp_mca_cert_template';
		if ( $existing && get_post_meta( $existing, $marker, true ) === $variant['key'] ) {
			$notes[] = 'certificate template already attached';
		} else {
			$uploads = wp_upload_dir();
			$dir     = trailingslashit( $uploads['basedir'] ) . 'hcp-mca';
			wp_mkdir_p( $dir );
			$dest = $dir . '/certificate-2026-background.jpg';
			if ( ! file_exists( $dest ) && ! copy( $template, $dest ) ) {
				throw new \RuntimeException( 'could not copy certificate template into uploads' );
			}
			$attachment_id = wp_insert_attachment( [
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Clinical Audit 2026 certificate background',
				'post_status'    => 'inherit',
			], $dest, $cert_id );
			if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
				throw new \RuntimeException( 'could not create the certificate background attachment' );
			}
			update_post_meta( $attachment_id, $marker, $variant['key'] );
			// Metadata generation needs GD; without it the file is still a valid featured image.
			if ( function_exists( 'wp_generate_attachment_metadata' ) && ( extension_loaded( 'gd' ) || extension_loaded( 'imagick' ) ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $dest ) );
			} else {
				update_post_meta( $attachment_id, '_wp_attachment_metadata', [ 'width' => 1241, 'height' => 1754, 'file' => 'hcp-mca/certificate-2026-background.jpg' ] );
			}
			set_post_thumbnail( $cert_id, $attachment_id );
			$notes[] = "certificate template attached as {$attachment_id}";
		}

		// 2. Homepage and landing page Activity ID cell.
		foreach ( [ 111281, 108753 ] as $page_id ) {
			$post = get_post( $page_id );
			if ( ! $post ) {
				continue;
			}
			$new = preg_replace(
				'/<b class="">1460034<\/b> and <b class="">1460044\s*<\/b>/',
				'[hcp_mca_activity_ids]',
				$post->post_content
			);
			if ( $new !== $post->post_content ) {
				wp_update_post( [ 'ID' => $page_id, 'post_content' => $new ] );
				$notes[] = "page {$page_id} activity IDs now from registry";
			} else {
				$notes[] = "page {$page_id} " . ( false !== strpos( $post->post_content, '[hcp_mca_activity_ids]' ) ? 'already updated' : 'pattern not found' );
			}
		}

		// 3. 2026 evaluation header.
		$header_key = HCP_MCA_V2_FIELD_KEY_PREFIX . '4ix3v2';
		$field      = FrmField::getOne( $header_key );
		if ( $field ) {
			$new = str_replace( '(Activity ID XXX)', '(Activity ID [hcp_mca_activity_id variant="v2"])', $field->description );
			if ( $new !== $field->description ) {
				FrmField::update( (int) $field->id, [ 'description' => $new ] );
				$notes[] = 'evaluation header now from registry';
			} else {
				$notes[] = 'evaluation header ' . ( false !== strpos( $field->description, 'hcp_mca_activity_id' ) ? 'already updated' : 'pattern not found' );
			}
		}

		return implode( '; ', $notes );
	},
];

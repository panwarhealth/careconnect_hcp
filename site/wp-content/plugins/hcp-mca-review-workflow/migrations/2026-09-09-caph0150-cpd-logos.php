<?php
/**
 * CAPH0150: RACGP CPD hours logos for the 2026 audit.
 *
 * Uploads the plugin's 2026 artwork (audit only: RP 2.0 / MO 3.0; whole
 * activity: EA 1.0 / RP 2.0 / MO 3.0) into the media library, then swaps the
 * hard-coded legacy <img> tags for [hcp_mca_cpd_logo] so the artwork derives
 * from the variant registry:
 *
 *  - Activity homepage and public landing page: whole-activity logo.
 *  - 2026 course page and 2026 evaluation header: audit-only logo.
 *
 * Legacy course page and evaluation keep their existing images. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 RACGP CPD logos uploaded and embedded via [hcp_mca_cpd_logo] (homepage, landing page, 2026 course page, 2026 evaluation header).',

	'up' => function (): string {
		$notes   = [];
		$variant = hcp_mca_variants( true )[ HCP_MCA_VARIANT_V2 ] ?? null;
		if ( ! $variant ) {
			throw new \RuntimeException( '2026 variant not provisioned; run the provision migration first.' );
		}

		// 1. Upload both logos.
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'hcp-mca';
		wp_mkdir_p( $dir );
		foreach ( [ 'audit' => 'Clinical Audit 2026 RACGP CPD logo', 'total' => 'Anal fissure activity 2026 RACGP CPD logo' ] as $type => $title ) {
			if ( hcp_mca_cpd_logo_attachment_id( $variant, $type ) ) {
				$notes[] = "{$type} logo already uploaded";
				continue;
			}
			$source = hcp_mca_cpd_logo_path( $variant, $type );
			if ( ! $source ) {
				throw new \RuntimeException( "{$type} logo artwork missing from the plugin" );
			}
			$dest = $dir . '/' . basename( $source );
			if ( ! file_exists( $dest ) && ! copy( $source, $dest ) ) {
				throw new \RuntimeException( "could not copy the {$type} logo into uploads" );
			}
			$attachment_id = wp_insert_attachment( [
				'post_mime_type' => 'image/png',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			], $dest );
			if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
				throw new \RuntimeException( "could not create the {$type} logo attachment" );
			}
			update_post_meta( $attachment_id, 'hcp_mca_cpd_logo', $variant['key'] . ':' . $type );
			if ( extension_loaded( 'gd' ) || extension_loaded( 'imagick' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $dest ) );
			} else {
				update_post_meta( $attachment_id, '_wp_attachment_metadata', [ 'width' => 834, 'height' => 556, 'file' => 'hcp-mca/' . basename( $source ) ] );
			}
			$notes[] = "{$type} logo uploaded as {$attachment_id}";
		}
		// Drop the static cache so the shortcode sees the new attachments in this request.
		hcp_mca_cpd_logo_attachment_id( $variant, 'audit' );

		// 2. Homepage and landing page: whole-activity logo.
		foreach ( [ 111281, 108753 ] as $page_id ) {
			$post = get_post( $page_id );
			if ( ! $post ) {
				continue;
			}
			$new = preg_replace_callback(
				'/<img\b([^>]*)\bsrc="[^"]*RACGP-logo-for-Total-Activity\.png"([^>]*)>/i',
				function ( $m ) {
					preg_match( '/\bclass="([^"]*)"/', $m[1] . $m[2], $c );
					$class = isset( $c[1] ) ? ' class="' . $c[1] . '"' : '';
					return '[hcp_mca_cpd_logo variant="v2" type="total"' . $class . ']';
				},
				$post->post_content
			);
			if ( $new !== $post->post_content ) {
				kses_remove_filters();
				wp_update_post( [ 'ID' => $page_id, 'post_content' => $new ] );
				kses_init_filters();
				$notes[] = "page {$page_id} whole-activity logo now from registry";
			} else {
				$notes[] = "page {$page_id} " . ( false !== strpos( $post->post_content, '[hcp_mca_cpd_logo' ) ? 'already updated' : 'pattern not found' );
			}
		}

		// 3. 2026 course page: audit-only logo in place of the legacy image block.
		$course = get_post( (int) $variant['course'] );
		if ( $course ) {
			$new = preg_replace(
				'~<!-- wp:image [^>]*-->\s*<figure class="wp-block-image[^"]*"><img [^>]*RACGP-logo-for-Clinical-Audit-Only\.png[^>]*/></figure>\s*<!-- /wp:image -->~',
				'<!-- wp:shortcode -->' . "\n" . '[hcp_mca_cpd_logo variant="v2" type="audit" style="width:250px"]' . "\n" . '<!-- /wp:shortcode -->',
				$course->post_content
			);
			if ( $new !== $course->post_content ) {
				kses_remove_filters();
				wp_update_post( [ 'ID' => $course->ID, 'post_content' => $new ] );
				kses_init_filters();
				$notes[] = 'course page audit logo now from registry';
			} else {
				$notes[] = 'course page ' . ( false !== strpos( $course->post_content, '[hcp_mca_cpd_logo' ) ? 'already updated' : 'pattern not found' );
			}
		}

		// 4. 2026 evaluation header.
		$field = FrmField::getOne( HCP_MCA_V2_FIELD_KEY_PREFIX . '4ix3v2' );
		if ( $field ) {
			$new = preg_replace(
				'/<img\b[^>]*RACGP-logo-for-Clinical-Audit-Only\.png[^>]*>/i',
				'[hcp_mca_cpd_logo variant="v2" type="audit" class="mb-lg block"]',
				$field->description
			);
			if ( $new !== $field->description ) {
				FrmField::update( (int) $field->id, [ 'description' => $new ] );
				FrmField::delete_form_transient( (int) $field->form_id );
				$notes[] = 'evaluation header logo now from registry';
			} else {
				$notes[] = 'evaluation header ' . ( false !== strpos( $field->description, '[hcp_mca_cpd_logo' ) ? 'already updated' : 'pattern not found' );
			}
		}

		return implode( '; ', $notes );
	},
];

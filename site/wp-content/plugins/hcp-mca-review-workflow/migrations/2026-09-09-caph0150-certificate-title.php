<?php
/**
 * CAPH0150: the 2026 certificate title is drawn from the registry, so the
 * template artwork no longer carries "Mini Clinical Audit". Refreshes the
 * uploaded copy of the template from the plugin asset. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: 2026 certificate template refreshed (title now drawn from the registry).',

	'up' => function (): string {
		$variant = hcp_mca_variants( true )[ HCP_MCA_VARIANT_V2 ] ?? null;
		if ( ! $variant ) {
			throw new \RuntimeException( '2026 variant not provisioned; run the provision migration first.' );
		}
		$source = hcp_mca_certificate_template_path( $variant );
		if ( ! $source ) {
			throw new \RuntimeException( 'certificate template artwork missing from the plugin' );
		}
		$uploads = wp_upload_dir();
		$dest    = trailingslashit( $uploads['basedir'] ) . 'hcp-mca/' . basename( $source );
		if ( file_exists( $dest ) && md5_file( $dest ) === md5_file( $source ) ) {
			return 'template already current';
		}
		wp_mkdir_p( dirname( $dest ) );
		if ( ! copy( $source, $dest ) ) {
			throw new \RuntimeException( 'could not refresh the certificate template in uploads' );
		}
		return 'certificate template refreshed';
	},
];

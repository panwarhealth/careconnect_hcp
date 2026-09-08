<?php
/**
 * RACGP CPD hours logos per variant.
 *
 * The artwork ships with the plugin (`cpd_logo`, `cpd_logo_total` in the
 * registry); a migration copies it into the media library and marks the
 * attachment with `hcp_mca_cpd_logo` = "<variant>:<type>". Pages embed the
 * logo through [hcp_mca_cpd_logo] so a new artwork is a one-place swap.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hcp_mca_cpd_logo', 'hcp_mca_shortcode_cpd_logo' );

/**
 * [hcp_mca_cpd_logo variant="v2" type="audit|total" class="" style=""]
 */
function hcp_mca_shortcode_cpd_logo( $atts ): string {
	$atts    = shortcode_atts( [ 'variant' => '', 'type' => 'audit', 'class' => '', 'style' => '', 'alt' => '' ], (array) $atts );
	$variant = hcp_mca_variant_from_context( $atts );
	$url     = $variant ? hcp_mca_cpd_logo_url( $variant, $atts['type'] ) : null;
	if ( ! $url ) {
		return '';
	}
	return sprintf(
		'<img src="%s" alt="%s"%s%s />',
		esc_url( $url ),
		esc_attr( $atts['alt'] ),
		$atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '',
		$atts['style'] ? ' style="' . esc_attr( $atts['style'] ) . '"' : ''
	);
}

/**
 * Registry key for a logo type.
 */
function hcp_mca_cpd_logo_field( string $type ): string {
	return 'total' === $type ? 'cpd_logo_total' : 'cpd_logo';
}

/**
 * Attachment ID of the uploaded logo, or 0 when the migration has not run.
 */
function hcp_mca_cpd_logo_attachment_id( array $variant, string $type ): int {
	static $cache = [];
	$marker = $variant['key'] . ':' . ( 'total' === $type ? 'total' : 'audit' );
	if ( ! array_key_exists( $marker, $cache ) ) {
		$found = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'hcp_mca_cpd_logo',
			'meta_value'     => $marker,
		] );
		$cache[ $marker ] = $found ? (int) $found[0] : 0;
	}
	return $cache[ $marker ];
}

/**
 * Public URL of the logo: media library first, plugin asset as fallback.
 */
function hcp_mca_cpd_logo_url( array $variant, string $type ): ?string {
	$field = hcp_mca_cpd_logo_field( $type );
	if ( empty( $variant[ $field ] ) ) {
		return null;
	}
	$attachment_id = hcp_mca_cpd_logo_attachment_id( $variant, $type );
	if ( $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );
		if ( $url ) {
			return $url;
		}
	}
	return hcp_mca_cpd_logo_path( $variant, $type ) ? plugins_url( $variant[ $field ], HCP_MCA_PLUGIN_DIR . 'hcp-mca-review-workflow.php' ) : null;
}

/**
 * Absolute path of the logo artwork inside the plugin, or null.
 */
function hcp_mca_cpd_logo_path( array $variant, string $type ): ?string {
	$field = hcp_mca_cpd_logo_field( $type );
	if ( empty( $variant[ $field ] ) ) {
		return null;
	}
	$path = HCP_MCA_PLUGIN_DIR . $variant[ $field ];
	return file_exists( $path ) ? $path : null;
}

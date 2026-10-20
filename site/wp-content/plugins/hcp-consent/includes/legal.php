<?php
/**
 * Terms of Use and Privacy Policy.
 *
 * The published version is a date, set by the migration that publishes the
 * text. It is stamped on every consent log row and shown as the Privacy
 * Policy's "Last Updated", so the two can never disagree.
 */

defined( 'ABSPATH' ) || exit;

const HCP_LEGAL_VERSION_OPTION = 'hcp_legal_version';
const HCP_LEGAL_PAGE_SLUGS     = array( 'terms-of-use', 'privacy-policy' );

function hcp_legal_version(): string {
	return (string) get_option( HCP_LEGAL_VERSION_OPTION, '' );
}

/**
 * Writes migrations/content/<slug>.html into each legal page and sets the
 * version. Overwrites the page body, so it is safe to run again; WordPress
 * keeps the previous text as a revision.
 *
 * @return string What was updated, for the migration log.
 */
function hcp_legal_publish( string $version ): string {
	$log = array();
	foreach ( HCP_LEGAL_PAGE_SLUGS as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $page ) {
			throw new RuntimeException( "Page /{$slug}/ not found." );
		}

		$content = file_get_contents( HCP_CONSENT_DIR . "migrations/content/{$slug}.html" );
		if ( false === $content || '' === trim( $content ) ) {
			throw new RuntimeException( "Content for /{$slug}/ missing." );
		}

		$result = wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ), true );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		$log[] = "/{$slug}/ (page {$page->ID})";
	}

	update_option( HCP_LEGAL_VERSION_OPTION, $version );

	return 'Updated ' . implode( ', ', $log ) . "; legal version {$version}.";
}

add_shortcode( 'hcp_legal_last_updated', 'hcp_legal_last_updated_shortcode' );
function hcp_legal_last_updated_shortcode(): string {
	$version = hcp_legal_version();

	// Same d/m/yy form the lawyers' draft used.
	return $version ? esc_html( mysql2date( 'j/n/y', $version ) ) : '';
}

add_action( 'wp_enqueue_scripts', 'hcp_legal_enqueue' );
function hcp_legal_enqueue(): void {
	if ( ! is_page( HCP_LEGAL_PAGE_SLUGS ) ) {
		return;
	}

	wp_enqueue_style( 'hcp-legal', HCP_CONSENT_URL . 'assets/css/legal.css', array(), hcp_consent_asset_version( 'assets/css/legal.css' ) );
}

function hcp_consent_asset_version( string $relative ): string {
	$path = HCP_CONSENT_DIR . $relative;

	return file_exists( $path ) ? (string) filemtime( $path ) : '0.1.0';
}

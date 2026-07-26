<?php
/**
 * Plugin Name: HCP SEO Guards
 * Description: Hard noindex safety net for membership-gated content and auth/system pages, plus sitemap exclusions. In-house.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public-but-never-index slugs (auth/system/utility pages).
 */
function hcp_seo_noise_slugs(): array {
	return [
		'log-in',
		'register',
		'no-access',
		'login-customizer',
		'certificate',
		'pre-learning-survey',
		'edit-your-profile',
		'forgot-password',
		'therapy-areas',
		'therapy-hub',
		'therapy-product',
		'therapy-areas-v2',
		// Logged-in activity hub; 302s to the landing page for anonymous visitors.
		'anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage',
	];
}

/**
 * Gated = an anonymous visitor cannot access it. Delegates to RCP so the
 * answer always matches what the frontend actually serves (302/404 for
 * anonymous users). Fails closed to "not gated" only when RCP is absent,
 * in which case the frontend gate is gone anyway.
 */
function hcp_seo_is_gated( int $post_id ): bool {
	static $cache = [];
	if ( ! isset( $cache[ $post_id ] ) ) {
		$cache[ $post_id ] = function_exists( 'rcp_user_can_access' )
			&& ! rcp_user_can_access( 0, $post_id );
	}
	return $cache[ $post_id ];
}

/**
 * Only these post types may ever be indexed. Everything else (LearnDash
 * courses/quizzes/certificates, resources, brands, download endpoints...)
 * is HCP-only or utility content.
 */
function hcp_seo_indexable_post_types(): array {
	return [ 'post', 'page' ];
}

/**
 * PDF-redirect interstitials (eDM tracking pages) render a GA event then
 * bounce straight to a noindexed PDF — never content, never in search.
 */
function hcp_seo_is_pdf_redirect( int $post_id ): bool {
	return get_post_meta( $post_id, '_wp_page_template', true ) === 'template-pdf-redirect.php';
}

/**
 * @param WP_Post|object $post Full WP_Post or a lightweight row exposing
 *                             ID/post_type/post_name (Rank Math's sitemap
 *                             provider passes stdClass, not WP_Post).
 */
function hcp_seo_must_noindex_post( object $post ): bool {
	if ( empty( $post->ID ) ) {
		return false;
	}
	$post_type = $post->post_type ?? get_post_type( $post->ID );
	$slug      = $post->post_name ?? get_post_field( 'post_name', $post->ID );

	return ! in_array( $post_type, hcp_seo_indexable_post_types(), true )
		|| hcp_seo_is_gated( (int) $post->ID )
		|| hcp_seo_is_pdf_redirect( (int) $post->ID )
		|| in_array( $slug, hcp_seo_noise_slugs(), true );
}

function hcp_seo_must_noindex(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_queried_object();
	return $post instanceof WP_Post && hcp_seo_must_noindex_post( $post );
}

add_filter( 'wp_robots', function ( array $robots ): array {
	if ( hcp_seo_must_noindex() ) {
		$robots = [
			'noindex'  => true,
			'nofollow' => true,
		];
	}
	return $robots;
}, 99 );

// Keep Rank Math's tag consistent so only one robots directive is emitted.
add_filter( 'rank_math/frontend/robots', function ( $robots ) {
	if ( hcp_seo_must_noindex() ) {
		return [
			'noindex'  => 'noindex',
			'nofollow' => 'nofollow',
		];
	}
	return $robots;
}, 99 );

// Drop gated and noise URLs from the Rank Math sitemap.
add_filter( 'rank_math/sitemap/entry', function ( $entry, $type, $object ) {
	if ( 'post' === $type && is_object( $object ) && hcp_seo_must_noindex_post( $object ) ) {
		return false;
	}
	return $entry;
}, 10, 3 );

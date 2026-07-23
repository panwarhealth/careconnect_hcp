<?php
/**
 * Apply the teaser blur pattern to the /resources/ page: hero section stays
 * public, the [resources] grid section gets logged_in_users_only (blur +
 * login/register overlay for logged-out visitors). The shortcode's anon
 * filtering keeps the blurred DOM content OTC-only.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'SEO: blur-gate the /resources/ cards grid (hero stays public).',
	'up'          => function (): string {
		$page = get_page_by_path( 'resources' );
		if ( ! $page ) {
			throw new \RuntimeException( 'resources page not found.' );
		}

		$gate_class = 'logged_in_users_only';
		$c          = $page->post_content;

		preg_match_all( '/<div class="[^"]*"[^>]*data-pb-label="Section"/', $c, $m, PREG_OFFSET_CAPTURE );
		if ( count( $m[0] ) < 2 ) {
			throw new \RuntimeException( "Page {$page->ID}: expected 2 sections, found " . count( $m[0] ) );
		}

		// Gate every section after the hero, walking backwards so earlier
		// offsets stay valid after insertion.
		for ( $i = count( $m[0] ) - 1; $i >= 1; $i-- ) {
			$off = $m[0][ $i ][1];
			if ( ! preg_match( '/^<div class="([^"]*)"/', substr( $c, $off, 400 ), $mm ) ) {
				continue;
			}
			if ( str_contains( $mm[1], $gate_class ) ) {
				continue;
			}
			$c = substr_replace( $c, '<div class="' . $mm[1] . ' ' . $gate_class . '"', $off, strlen( $mm[0] ) );
		}

		if ( $c === $page->post_content ) {
			return "Page {$page->ID}: already gated, no change.";
		}

		kses_remove_filters();
		$updated = wp_update_post( [ 'ID' => $page->ID, 'post_content' => $c ], true );
		kses_init_filters();
		if ( is_wp_error( $updated ) ) {
			throw new \RuntimeException( "Page {$page->ID}: " . $updated->get_error_message() );
		}

		return "Page {$page->ID}: grid section gated with {$gate_class}.";
	},
];

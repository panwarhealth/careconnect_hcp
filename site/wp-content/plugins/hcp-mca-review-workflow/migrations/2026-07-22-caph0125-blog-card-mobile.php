<?php
/**
 * Blog featured-card mobile fixes (MD v2 feedback):
 *  - the excerpt was hidden below md (`hidden md:block`) — show it on mobile too
 *  - the CTA button was cross-axis end-aligned on mobile (`self-end`) — sit it left
 *    (`self-start`) for consistency with the other article tiles
 *
 * Idempotent str_replace on the Blog page (26038). Runs after the 2026-07-16
 * excerpt/cta migrations that introduced these classes.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Blog featured card: show excerpt on mobile + left-align the CTA button on mobile.',

	'up' => function (): string {
		$page = get_page_by_path( 'blog' );
		if ( ! $page ) {
			throw new \RuntimeException( 'Blog page not found.' );
		}

		$content = $page->post_content;
		$notes   = [];

		$replacements = [
			// show the excerpt on mobile
			'<div class="pr-xl hidden md:block">{{excerpt.rendered}}</div>' => '<div class="pr-xl">{{excerpt.rendered}}</div>',
			// left-align the CTA on mobile
			'class="btn cta mt-1 md:mt-2 self-end md:self-auto"'           => 'class="btn cta mt-1 md:mt-2 self-start md:self-auto"',
		];

		foreach ( $replacements as $old => $new ) {
			if ( strpos( $content, $new ) !== false ) {
				$notes[] = 'already applied';
			} elseif ( strpos( $content, $old ) === false ) {
				$notes[] = 'source not matched (skipped)';
			} else {
				$content   = str_replace( $old, $new, $content );
				$notes[]   = 'applied';
			}
		}

		if ( $content !== $page->post_content ) {
			$result = wp_update_post( [ 'ID' => $page->ID, 'post_content' => $content ], true );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( 'wp_update_post failed: ' . $result->get_error_message() );
			}
		}

		return "Blog card mobile fixes on page {$page->ID}: " . implode( '; ', $notes ) . '.';
	},
];

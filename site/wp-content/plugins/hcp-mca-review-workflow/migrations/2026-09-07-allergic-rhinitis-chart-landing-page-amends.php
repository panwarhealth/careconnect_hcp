<?php
/**
 * Reviewer amendments to the /allergic-rhinitis-chart/ landing page:
 * intro copy, shorter card titles, and a "NEW" tag on the 2026 thumbnail.
 *
 * Runs after 2026-09-07-allergic-rhinitis-chart-landing-page. Idempotent:
 * each replacement is skipped once its result is present.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'AR chart landing page: "Posters from the National Asthma Council" intro, shorter card titles, NEW tag on the 2026 chart.',

	'up' => function (): string {
		$page = get_page_by_path( 'allergic-rhinitis-chart', OBJECT, 'page' );
		if ( ! $page ) {
			throw new \RuntimeException( 'Page allergic-rhinitis-chart not found.' );
		}

		$badge = '<span class="hcp-new-tag" style="position:absolute;top:0.75rem;right:0.75rem;background:#002a48;color:#fff;font-weight:700;font-size:0.8125rem;letter-spacing:0.08em;line-height:1;padding:0.4rem 0.7rem;border-radius:0.25rem;box-shadow:0 2px 6px rgba(0,0,0,0.25);">NEW</span>';

		$replacements = [
			'intro'      => [
				'National Asthma Council Australia charts summarising the intranasal treatment options for allergic rhinitis.',
				'Posters from the National Asthma Council summarising the intranasal treatment options for allergic rhinitis.',
			],
			'title-2025' => [
				'Download the 2025 Allergic Rhinitis Treatments Chart',
				'Download the 2025 Chart',
			],
			'title-2026' => [
				'Download the 2026 Allergic Rhinitis Treatments Chart',
				'Download the 2026 Chart',
			],
		];

		$content = $page->post_content;
		$notes   = [];

		foreach ( $replacements as $label => [ $old, $new ] ) {
			if ( false !== strpos( $content, $new ) ) {
				continue;
			}
			$count = substr_count( $content, $old );
			if ( 1 !== $count ) {
				throw new \RuntimeException( "expected 1 '{$label}' match, found {$count}" );
			}
			$content = str_replace( $old, $new, $content );
			$notes[] = $label;
		}

		// NEW tag: the 2026 card's thumbnail link becomes the positioning context.
		if ( false === strpos( $content, 'hcp-new-tag' ) ) {
			$pattern = '~(<div class="card[^"]*"[^>]*id="chart-2026">.*?<a href="[^"]*" target="_blank" style=")display:block;margin:0 0 1\.5rem;(">)(<img [^>]*/>)~s';
			$content = preg_replace( $pattern, '$1position:relative;display:block;margin:0 0 1.5rem;$2$3' . $badge, $content, 1, $hits );
			if ( null === $content || 1 !== $hits ) {
				throw new \RuntimeException( 'could not place the NEW tag on the 2026 card' );
			}
			$notes[] = 'new-tag';
		}

		if ( ! $notes ) {
			return "Page {$page->ID} already amended.";
		}

		kses_remove_filters();
		$updated = wp_update_post( [ 'ID' => $page->ID, 'post_content' => $content ], true );
		kses_init_filters();

		if ( is_wp_error( $updated ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $updated->get_error_message() );
		}

		return "Page {$page->ID} amended (" . implode( ', ', $notes ) . ').';
	},
];

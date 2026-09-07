<?php
/**
 * Convert /allergic-rhinitis-chart/ (page 143251) from a PDF redirect into a
 * landing page offering both editions of the National Asthma Council
 * Allergic Rhinitis Treatments Chart, for the September eDMs.
 *
 * The redirect template and its target meta are removed, so the page renders
 * normally and the hcp-seo auto-noindex for redirect pages no longer applies.
 * The page is deliberately ungated.
 *
 * PDF and thumbnail URLs are read from the two resource posts, so this must
 * run after 2026-09-07-nac-ar-treatments-chart-2026-resource.
 *
 * Idempotent: content is rewritten from the template on every run.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Turn /allergic-rhinitis-chart/ into a landing page for the 2025 and 2026 NAC treatment charts.',

	'up' => function (): string {
		$page = get_page_by_path( 'allergic-rhinitis-chart', OBJECT, 'page' );
		if ( ! $page ) {
			throw new \RuntimeException( 'Page allergic-rhinitis-chart not found.' );
		}

		$charts = [
			2025 => get_post( 167 ),
			2026 => get_page_by_path( '2026-national-asthma-council-allergic-rhinitis-treatments-chart', OBJECT, 'resources' ),
		];

		$cards = '';
		foreach ( $charts as $year => $resource ) {
			if ( ! $resource || 'resources' !== $resource->post_type ) {
				throw new \RuntimeException( "{$year} chart resource not found — run the resource migration first." );
			}

			$pdf   = wp_get_attachment_url( (int) get_post_meta( $resource->ID, 'download', true ) );
			$thumb = wp_get_attachment_url( (int) get_post_meta( $resource->ID, '_thumbnail_id', true ) );
			if ( ! $pdf || ! $thumb ) {
				throw new \RuntimeException( "{$year} chart resource {$resource->ID} is missing its PDF or thumbnail." );
			}

			$cards .= sprintf(
				'<div class="card column flex flex-col items-center text-center" data-pb-label="Column" id="chart-%1$d">'
				. '<div class="content-block w-full" data-pb-label="Content Block">'
				. '<a href="%4$s" target="_blank"><img src="%2$s" alt="%3$s" class="w-full" /></a>'
				. '</div>'
				. '<div class="card-body content-block" data-pb-label="Content Block" style="padding:2rem 1.5rem 2.5rem;">'
				. '<h3 class=" ">Download the %1$d Allergic Rhinitis Treatments Chart  </h3>'
				. '<a class="btn cta my-0" href="%4$s" target="_blank">Download Chart</a>'
				. '</div>'
				. '</div>',
				$year,
				esc_url( $thumb ),
				esc_attr( $resource->post_title ),
				esc_url( $pdf )
			);
		}

		$content = '<div class="section pb-0" data-pb-label="Section" style="padding-top:5rem;">'
			. '<div class="container grid lg:max-w-7xl" data-pb-label="Container">'
			. '<div class="column" data-pb-label="Column">'
			. '<div class="content-block text-center" data-pb-label="Content Block">'
			. '<h1 class=" ">Allergic Rhinitis Treatments Charts  </h1>'
			. '<p class="">National Asthma Council Australia charts summarising the intranasal treatment options for allergic rhinitis.  </p>'
			. '</div></div></div></div>'
			. '<div class="section" data-pb-label="Section">'
			. '<div class="container grid md:grid-cols-2 lg:max-w-7xl" data-pb-label="Container" style="gap:3rem;">'
			. $cards
			. '</div></div>';

		kses_remove_filters();
		$updated = wp_update_post(
			[
				'ID'           => $page->ID,
				'post_title'   => 'Allergic Rhinitis Treatments Charts',
				'post_content' => $content,
			],
			true
		);
		kses_init_filters();

		if ( is_wp_error( $updated ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $updated->get_error_message() );
		}

		delete_post_meta( $page->ID, '_wp_page_template' );
		delete_post_meta( $page->ID, '_pdf_redirect_url' );

		return "Page {$page->ID} converted to landing page with 2025 and 2026 chart cards.";
	},
];

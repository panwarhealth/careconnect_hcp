<?php
/**
 * Replace the Zaditen Detailer card in "Can you spot the allergy eye?" (post 93745)
 * with the Allergic Conjunctivitis Treatments Chart.
 *
 * Card id="tool": image, heading, body copy and CTA all retarget to the
 * /allergic-conjunctivitis-chart/ redirect page so downloads are tracked.
 * The [restrict] gate around the CTA is left untouched.
 *
 * Idempotent: no-ops once the new heading is present.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Swap the Zaditen Detailer card for the Allergic Conjunctivitis Treatments Chart in post 93745.',

	'up' => function (): string {
		$article = get_page_by_path( 'can-you-spot-the-allergy-eye', OBJECT, 'post' );

		if ( ! $article ) {
			throw new \RuntimeException( 'Article can-you-spot-the-allergy-eye not found' );
		}

		$heading = 'Allergic Conjunctivitis Treatments Chart';

		if ( false !== strpos( $article->post_content, $heading ) ) {
			return "post {$article->ID} already updated";
		}

		$thumb = 'https://hcp.carepharma.com.au/wp-content/uploads/2026/08/caph0102-zaditen-ac-treatments-chart-thumb.jpg';

		$replacements = [
			'image' => [
				'~https://hcp\.carepharma\.com\.au/wp-content/uploads/2025/09/CARE0158-CARE-ZADITEN-DETAILER\.png~',
				$thumb,
			],
			'heading' => [
				'~<h3 class=" ">Zaditen Detailer\s*</h3>~',
				'<h3 class=" ">' . $heading . '  </h3>',
			],
			'copy' => [
				'~<p class="">Download the Zaditen \(ketotifen\) detailer[^<]*</p>~u',
				'<p class="">Download this summary of guideline-recommended eyedrop options, including information on Zaditen&reg;  </p>',
			],
			'cta' => [
				'~<a class="btn cta my-0" href="/wp-content/uploads/2025/09/CARE0158-CARE-ZADITEN-DETAILER-P\.pdf" target="_blank">Download Detailer</a>~',
				'<a class="btn cta my-0" href="/allergic-conjunctivitis-chart/" target="_blank">Download Chart</a>',
			],
		];

		$content = $article->post_content;
		$notes   = [];

		foreach ( $replacements as $label => [ $pattern, $value ] ) {
			$content = preg_replace( $pattern, $value, $content, -1, $hits );

			if ( null === $content ) {
				throw new \RuntimeException( "preg_replace failed on '{$label}'" );
			}

			if ( 1 !== $hits ) {
				throw new \RuntimeException( "expected 1 '{$label}' match, found {$hits}" );
			}

			$notes[] = $label;
		}

		kses_remove_filters();
		$updated = wp_update_post( [ 'ID' => $article->ID, 'post_content' => $content ], true );
		kses_init_filters();

		if ( is_wp_error( $updated ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $updated->get_error_message() );
		}

		return "post {$article->ID} card swapped (" . implode( ', ', $notes ) . ')';
	},
];

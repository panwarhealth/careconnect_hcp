<?php
/**
 * Contact page (44343): Care Pharma's wording for who the form reaches, plus
 * pointers to Customer Care (complaints, adverse events, medical information)
 * and Order Samples above the form. The adverse event box stays.
 *
 * Idempotent; refuses to run if the original text isn't found.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Contact page: HCP Marketing wording, Customer Care and Order Samples links',
	'up'          => function (): string {
		$page = get_post( 44343 );

		if ( ! $page || 'contact' !== $page->post_name ) {
			throw new RuntimeException( 'Contact page (44343) not found.' );
		}

		$content = $page->post_content;

		if ( false !== strpos( $content, 'our HCP Marketing team will respond' ) ) {
			return 'already applied';
		}

		$swaps = [
			'/<p class="">\s*Have a specific inquiry\?\s*<\/p>/' =>
				'<p class="">Have a question about our healthcare professional resources, educational materials, marketing programs or other HCP initiatives?</p>',
			'/<p class=" ">\s*Complete the form below, write to, or call us to get in touch with our team\.\s*<\/p>/' =>
				'<p class=" ">Complete the form below and our HCP Marketing team will respond to your inquiry.</p>',
			'/(<div class="content-block bg-light rounded-2xl p-lg" data-pb-label="Content Block">\s*\[formidable id=1\])/' =>
				'<div class="content-block mb-md" data-pb-label="Content Block"> <p class="">To submit a product complaint, report an adverse event, or request medical information about one of our products, please contact our <a href="https://carepharmaceuticals.com.au/contact-us/" class="underline" target="_blank" rel="noopener">Customer Care team</a>.</p> <p class="mb-0">Need product samples for your clinic? <a href="/order-samples/" class="underline">Order samples online</a>.</p> </div> $1',
		];

		foreach ( $swaps as $pattern => $replacement ) {
			$content = preg_replace( $pattern, $replacement, $content, -1, $count );

			if ( 1 !== $count ) {
				throw new RuntimeException( "Expected one match for {$pattern}, found {$count}." );
			}
		}

		kses_remove_filters();
		$updated = wp_update_post( [ 'ID' => 44343, 'post_content' => wp_slash( $content ) ], true );
		kses_init_filters();

		if ( is_wp_error( $updated ) ) {
			throw new RuntimeException( 'Failed to update page 44343: ' . $updated->get_error_message() );
		}

		return 'Contact page wording and links updated';
	},
];

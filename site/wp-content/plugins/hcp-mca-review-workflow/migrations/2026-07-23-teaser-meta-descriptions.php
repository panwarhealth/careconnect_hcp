<?php
/**
 * Three of the six teaser-converted articles have thin intro text, so Rank
 * Math's auto-description falls back to the category name ("Rectal Health" /
 * "Nasal Health"). Set explicit meta descriptions so search snippets are
 * usable. No product names: descriptions are public HTML.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'SEO: set meta descriptions on the 3 teaser articles with junk auto-snippets.',
	'up'          => function (): string {
		$descriptions = [
			63079 => 'Anal fissures cause disproportionate pain for patients. Five practical ways GPs can help ease the pain of anal fissures and support healing.',
			34038 => "Practical tools GPs can give parents to help relieve kids' winter cold and flu symptoms, from nasal congestion to sore throats.",
			64151 => 'Obstructive sleep apnoea is underdiagnosed in Australia. Key facts for GPs on recognising, assessing and managing OSA in primary care.',
		];

		$log = [];
		foreach ( $descriptions as $post_id => $desc ) {
			if ( ! get_post( $post_id ) ) {
				throw new \RuntimeException( "Post {$post_id} not found." );
			}
			update_post_meta( $post_id, 'rank_math_description', $desc );
			$log[] = $post_id;
		}

		return 'Set rank_math_description on: ' . implode( ', ', $log );
	},
];

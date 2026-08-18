<?php
/**
 * Client feedback on the eps 2-3 resource cards:
 *   - Retitle the pregnancy factsheet resource (drops the internal "HCP -"
 *     prefix; matches the copy doc). Also shown on the resources listing.
 *   - Shorten the URTIs article title ON ITS CARD ONLY via `_hcpvid_card_title`
 *     — the live article keeps its full title everywhere else.
 *
 * Idempotent: plain overwrites.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'FESS eps 2-3 card feedback: pregnancy factsheet retitle + URTIs card-only short title.',
	'up'          => function () {
		$log = array();

		$factsheet = get_page_by_path( 'hcp-pregnancy-factsheet', OBJECT, 'resources' );
		if ( ! $factsheet ) {
			throw new RuntimeException( 'Pregnancy factsheet resource not found.' );
		}
		wp_update_post( array(
			'ID'         => $factsheet->ID,
			'post_title' => 'Nasal Congestion During Pregnancy Factsheet',
		) );
		$log[] = "factsheet retitled ({$factsheet->ID})";

		$article = get_page_by_path( 'rethink-your-approach-to-paediatric-urtis', OBJECT, 'post' );
		if ( ! $article ) {
			throw new RuntimeException( 'URTIs article not found.' );
		}
		update_post_meta( $article->ID, '_hcpvid_card_title', 'Expert Q&A: Rethink your approach to paediatric URTIs' );
		$log[] = "URTIs card title set ({$article->ID})";

		return implode( '; ', $log );
	},
);

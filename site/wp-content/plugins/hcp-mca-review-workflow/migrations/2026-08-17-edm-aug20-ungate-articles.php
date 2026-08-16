<?php
/**
 * Ungate windows for the 20 Aug 2026 eDM: three articles open to logged-out
 * visitors for two days (20-21 Aug AEST).
 *
 * Windows are `_ungated_from` / `_ungated_until` unix-timestamp post meta,
 * read by inc/ungate.php in the child theme. They expire on their own; no
 * clean-up migration is needed.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 20 Aug: ungate glucose quiz, Sip to Stand and GI tolerability articles for 2 days.',
	'up'          => function () {
		$from  = 1787148000; // 2026-08-20 00:00 AEST
		$until = 1787320800; // 2026-08-22 00:00 AEST

		$slugs = array(
			'guess-the-glucose-challenge',
			'sip-to-stand-why-hydration-is-essential-in-pots',
			'how-gps-can-support-gi-tolerability-for-patients-taking-anti-obesity-medications',
		);

		$log = array();
		foreach ( $slugs as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'post' );
			if ( ! $post ) {
				throw new RuntimeException( "Article '$slug' not found — window not set." );
			}
			update_post_meta( $post->ID, '_ungated_from', $from );
			update_post_meta( $post->ID, '_ungated_until', $until );
			$log[] = "$slug ({$post->ID})";
		}

		return 'Ungated 20-21 Aug: ' . implode( '; ', $log ) . '.';
	},
);

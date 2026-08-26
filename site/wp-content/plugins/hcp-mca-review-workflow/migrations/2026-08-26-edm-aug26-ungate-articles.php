<?php
/**
 * Ungate windows for the 26 Aug 2026 eDM: three articles open to logged-out
 * visitors for two days (26-27 Aug AEST).
 *
 * Same three articles as the 20 Aug send; that window has lapsed, so this
 * re-opens them on new timestamps. Windows expire on their own; no clean-up
 * migration is needed.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'eDM 26 Aug: ungate glucose quiz, Sip to Stand and GI tolerability articles for 2 days.',
	'up'          => function () {
		$from  = 1787666400; // 2026-08-26 00:00 AEST
		$until = 1787839200; // 2026-08-28 00:00 AEST

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

		return 'Ungated 26-27 Aug: ' . implode( '; ', $log ) . '.';
	},
);

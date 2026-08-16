<?php
/**
 * Label the featured Clinical Bites card as "Ep 1:".
 *
 * The single card beside the series copy always shows episode 1, but read on
 * its own it gave no sign of where in the series it sat. The carousel below
 * already numbers its cards with an "Episode N" eyebrow and is left alone.
 *
 * Applies to the hub (Tools & Videos) and the series landing page, both of
 * which carry the same featured shortcode.
 *
 * Idempotent: no-ops once the prefix is present.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Featured Clinical Bites card titled "Ep 1: …" on the hub and landing page.',
	'up'          => function () {
		$from = '[video_grid topic="clinical-bites-diabetes" columns="1" limit="1"]';
		$to   = '[video_grid topic="clinical-bites-diabetes" columns="1" limit="1" title_prefix="Ep 1: "]';

		$results = array();

		foreach ( array( 'tools-and-videos', 'clinical-bites' ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page ) {
				$results[] = "{$slug}: page not found";
				continue;
			}

			if ( false === strpos( $page->post_content, $from ) ) {
				$results[] = false !== strpos( $page->post_content, $to )
					? "{$slug}: already applied"
					: "{$slug}: featured shortcode not found, nothing changed";
				continue;
			}

			wp_update_post( array(
				'ID'           => $page->ID,
				'post_content' => str_replace( $from, $to, $page->post_content ),
			) );

			$results[] = "{$slug}: prefixed";
		}

		return implode( '; ', $results );
	},
);

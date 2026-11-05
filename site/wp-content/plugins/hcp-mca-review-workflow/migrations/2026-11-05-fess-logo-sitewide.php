<?php
/**
 * Latest FESS logo on every published page that still shows the 2023 one
 * (home, welcome pages, log in, register, our company, contact). Same artwork
 * and size as Order Samples uses since 2026-10-14-caph0141-fess-logo.
 *
 * Needs uploads/2026/10/caph0141/fess-logo.jpg (shipped with that migration).
 * Idempotent. To roll back, swap the two paths.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Latest FESS logo on every published page still using the 2023 logo',
	'up'          => function (): string {
		global $wpdb;

		$old = '/wp-content/uploads/2023/08/FESS_Logo.jpg';
		$new = '/wp-content/uploads/2026/10/caph0141/fess-logo.jpg';

		if ( ! file_exists( wp_upload_dir()['basedir'] . '/2026/10/caph0141/fess-logo.jpg' ) ) {
			throw new RuntimeException( 'Missing uploads/2026/10/caph0141/fess-logo.jpg; upload it first.' );
		}

		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type NOT IN ('revision', 'attachment')
			 AND post_content LIKE %s",
			'%' . $wpdb->esc_like( $old ) . '%'
		) );

		if ( ! $posts ) {
			return 'No published page uses the 2023 FESS logo.';
		}

		$done = [];
		foreach ( $posts as $post ) {
			$wpdb->update( $wpdb->posts, [ 'post_content' => str_replace( $old, $new, $post->post_content ) ], [ 'ID' => $post->ID ] );
			clean_post_cache( (int) $post->ID );
			$done[] = $post->ID;
		}

		return 'FESS logo updated on posts ' . implode( ', ', $done ) . '.';
	},
];

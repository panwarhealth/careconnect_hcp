<?php
/**
 * Tools → HCP Video Migrations — single-click runner for pending migrations.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page(
		'HCP Video Migrations',
		'HCP Video Migrations',
		'manage_options',
		'hcp-videos-migrations',
		'hcp_videos_render_migrations_page'
	);
} );

function hcp_videos_render_migrations_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$log = array();
	if ( isset( $_POST['hcp_videos_run'] ) && check_admin_referer( 'hcp_videos_run_migrations' ) ) {
		$log = hcp_videos_migrations_run_pending();
	}

	$pending = hcp_videos_migrations_pending();
	$applied = hcp_videos_migrations_applied();

	echo '<div class="wrap"><h1>HCP Video Migrations</h1>';

	if ( $log ) {
		echo '<div class="notice notice-info"><ul style="margin:8px 0;">';
		foreach ( $log as $entry ) {
			printf(
				'<li><strong>%s</strong> — %s: %s</li>',
				esc_html( $entry['slug'] ),
				esc_html( $entry['status'] ),
				esc_html( $entry['message'] )
			);
		}
		echo '</ul></div>';
	}

	echo '<h2>Pending (' . count( $pending ) . ')</h2>';
	if ( $pending ) {
		echo '<ul style="list-style:disc;margin-left:20px;">';
		foreach ( $pending as $slug => $m ) {
			printf( '<li><code>%s</code> — %s</li>', esc_html( $slug ), esc_html( $m['description'] ) );
		}
		echo '</ul>';
		echo '<form method="post">';
		wp_nonce_field( 'hcp_videos_run_migrations' );
		echo '<p><button type="submit" name="hcp_videos_run" class="button button-primary">Run pending migrations</button></p>';
		echo '</form>';
	} else {
		echo '<p>None — all migrations applied. (Safe to re-run any time.)</p>';
	}

	echo '<h2>Applied (' . count( $applied ) . ')</h2>';
	if ( $applied ) {
		echo '<ul style="list-style:disc;margin-left:20px;">';
		foreach ( $applied as $slug => $meta ) {
			printf( '<li><code>%s</code> — %s</li>', esc_html( $slug ), esc_html( $meta['applied_at'] ?? '' ) );
		}
		echo '</ul>';
	}

	echo '</div>';
}

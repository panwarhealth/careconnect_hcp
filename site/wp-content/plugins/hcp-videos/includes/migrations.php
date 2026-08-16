<?php
/**
 * Plugin-scoped DB migrations runner (mirrors hcp-mca-review-workflow).
 *
 * Migrations live in plugin/migrations/YYYY-MM-DD-slug.php and each file must
 * `return` an array with keys 'description' (string) and 'up' (callable).
 * The callable must be idempotent. Applied migrations are tracked in the
 * `hcp_videos_migrations_run` option, keyed by slug.
 */

defined( 'ABSPATH' ) || exit;

const HCP_VIDEOS_MIGRATIONS_OPTION = 'hcp_videos_migrations_run';
const HCP_VIDEOS_MIGRATIONS_DIR    = 'migrations';

function hcp_videos_migrations_discover(): array {
	$dir = trailingslashit( HCP_VIDEOS_DIR ) . HCP_VIDEOS_MIGRATIONS_DIR;
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$files = glob( $dir . '/*.php' );
	sort( $files );

	$migrations = array();
	foreach ( $files as $path ) {
		$slug = basename( $path, '.php' );
		$defn = require $path;
		if ( ! is_array( $defn ) || ! isset( $defn['up'] ) || ! is_callable( $defn['up'] ) ) {
			continue;
		}
		$migrations[ $slug ] = array(
			'slug'        => $slug,
			'description' => $defn['description'] ?? '',
			'up'          => $defn['up'],
		);
	}
	return $migrations;
}

function hcp_videos_migrations_applied(): array {
	$applied = get_option( HCP_VIDEOS_MIGRATIONS_OPTION, array() );
	return is_array( $applied ) ? $applied : array();
}

function hcp_videos_migrations_pending(): array {
	return array_diff_key( hcp_videos_migrations_discover(), hcp_videos_migrations_applied() );
}

/**
 * @return array Log entries [ 'slug', 'status' => 'ok'|'error', 'message' ].
 */
function hcp_videos_migrations_run_pending(): array {
	// Migrations run from CLI or a front-end runner, where no user holds
	// unfiltered_html — KSES then silently strips <script>/<style> from any
	// content a migration saves, leaving inline code as visible page text.
	// Migrations are trusted code shipping trusted content; drop the filters
	// for the run and restore them after.
	kses_remove_filters();

	$log = array();
	foreach ( hcp_videos_migrations_pending() as $slug => $m ) {
		try {
			$result  = call_user_func( $m['up'] );
			$message = is_string( $result ) ? $result : 'Applied.';

			$applied          = hcp_videos_migrations_applied();
			$applied[ $slug ] = array(
				'applied_at' => current_time( 'mysql' ),
				'applied_by' => get_current_user_id() ?: 0,
			);
			update_option( HCP_VIDEOS_MIGRATIONS_OPTION, $applied, false );

			$log[] = array( 'slug' => $slug, 'status' => 'ok', 'message' => $message );
		} catch ( \Throwable $e ) {
			// A failed migration stays pending and re-runs next time; later
			// migrations still get their chance.
			$log[] = array( 'slug' => $slug, 'status' => 'error', 'message' => $e->getMessage() );
		}
	}

	kses_init();

	return $log;
}

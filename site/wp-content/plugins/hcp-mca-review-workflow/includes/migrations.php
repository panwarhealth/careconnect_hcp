<?php
/**
 * Plugin-scoped DB migrations runner.
 *
 * Migrations live in plugin/migrations/YYYY-MM-DD-slug.php and each file must
 * `return` an array with keys 'description' (string) and 'up' (callable).
 * The callable must be idempotent.
 *
 * Applied migrations are tracked in the `hcp_mca_migrations_run` option, keyed
 * by slug, with `applied_at` + `applied_by` metadata.
 */

defined( 'ABSPATH' ) || exit;

const HCP_MCA_MIGRATIONS_OPTION = 'hcp_mca_migrations_run';
const HCP_MCA_MIGRATIONS_DIR    = 'migrations';

function hcp_mca_migrations_discover(): array {
	$dir = trailingslashit( HCP_MCA_PLUGIN_DIR ) . HCP_MCA_MIGRATIONS_DIR;
	if ( ! is_dir( $dir ) ) {
		return [];
	}

	$files = glob( $dir . '/*.php' );
	sort( $files );

	$migrations = [];
	foreach ( $files as $path ) {
		$slug = basename( $path, '.php' );
		$defn = require $path;
		if ( ! is_array( $defn ) || ! isset( $defn['up'] ) || ! is_callable( $defn['up'] ) ) {
			continue;
		}
		$migrations[ $slug ] = [
			'slug'        => $slug,
			'path'        => $path,
			'description' => $defn['description'] ?? '',
			'up'          => $defn['up'],
		];
	}
	return $migrations;
}

function hcp_mca_migrations_applied(): array {
	$applied = get_option( HCP_MCA_MIGRATIONS_OPTION, [] );
	return is_array( $applied ) ? $applied : [];
}

function hcp_mca_migrations_pending(): array {
	$all     = hcp_mca_migrations_discover();
	$applied = hcp_mca_migrations_applied();
	return array_diff_key( $all, $applied );
}

/**
 * @return array Log entries [ 'slug' => string, 'status' => 'ok'|'error', 'message' => string ].
 */
function hcp_mca_migrations_run_pending(): array {
	$log     = [];
	$pending = hcp_mca_migrations_pending();

	foreach ( $pending as $slug => $m ) {
		try {
			$result  = call_user_func( $m['up'] );
			$message = is_string( $result ) ? $result : 'Applied.';

			$applied          = hcp_mca_migrations_applied();
			$applied[ $slug ] = [
				'applied_at' => current_time( 'mysql' ),
				'applied_by' => get_current_user_id() ?: 0,
			];
			update_option( HCP_MCA_MIGRATIONS_OPTION, $applied, false );

			$log[] = [ 'slug' => $slug, 'status' => 'ok', 'message' => $message ];
		} catch ( \Throwable $e ) {
			// Stop on first error so slug ordering stays deterministic.
			$log[] = [ 'slug' => $slug, 'status' => 'error', 'message' => $e->getMessage() ];
			break;
		}
	}

	return $log;
}

register_activation_hook(
	HCP_MCA_PLUGIN_DIR . 'hcp-mca-review-workflow.php',
	'hcp_mca_migrations_run_pending'
);

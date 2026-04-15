<?php
/**
 * Tools → HCP Migrations admin page. Capability: manage_options.
 */

defined( 'ABSPATH' ) || exit;

const HCP_MCA_MIGRATIONS_MENU_SLUG = 'hcp-mca-migrations';
const HCP_MCA_MIGRATIONS_NONCE     = 'hcp_mca_run_migrations';

add_action( 'admin_menu', 'hcp_mca_migrations_register_menu' );
add_action( 'admin_post_' . HCP_MCA_MIGRATIONS_NONCE, 'hcp_mca_migrations_handle_post' );

function hcp_mca_migrations_register_menu(): void {
	add_management_page(
		__( 'HCP Migrations', 'hcp-mca-review' ),
		__( 'HCP Migrations', 'hcp-mca-review' ),
		'manage_options',
		HCP_MCA_MIGRATIONS_MENU_SLUG,
		'hcp_mca_migrations_render_page'
	);
}

function hcp_mca_migrations_handle_post(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'hcp-mca-review' ) );
	}
	check_admin_referer( HCP_MCA_MIGRATIONS_NONCE );

	$log = hcp_mca_migrations_run_pending();
	set_transient( 'hcp_mca_migrations_last_log', $log, 60 );

	wp_safe_redirect( add_query_arg( 'page', HCP_MCA_MIGRATIONS_MENU_SLUG, admin_url( 'tools.php' ) ) );
	exit;
}

function hcp_mca_migrations_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$all     = hcp_mca_migrations_discover();
	$applied = hcp_mca_migrations_applied();
	$pending = hcp_mca_migrations_pending();
	$log     = get_transient( 'hcp_mca_migrations_last_log' );
	if ( $log ) {
		delete_transient( 'hcp_mca_migrations_last_log' );
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'HCP MCA Migrations', 'hcp-mca-review' ); ?></h1>
		<p><?php esc_html_e( 'Plugin-scoped DB migrations. Running is idempotent — already-applied migrations are skipped. Safe to run twice.', 'hcp-mca-review' ); ?></p>

		<?php if ( is_array( $log ) && $log ) : ?>
			<div class="notice notice-info">
				<p><strong><?php esc_html_e( 'Last run:', 'hcp-mca-review' ); ?></strong></p>
				<ul style="list-style:disc;padding-left:20px;">
					<?php foreach ( $log as $entry ) : ?>
						<li>
							<code><?php echo esc_html( $entry['slug'] ); ?></code>
							— <strong><?php echo esc_html( strtoupper( $entry['status'] ) ); ?></strong>
							— <?php echo esc_html( $entry['message'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Pending', 'hcp-mca-review' ); ?> (<?php echo count( $pending ); ?>)</h2>
		<?php if ( ! $pending ) : ?>
			<p><em><?php esc_html_e( 'Nothing to run — you are up to date.', 'hcp-mca-review' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Slug', 'hcp-mca-review' ); ?></th><th><?php esc_html_e( 'Description', 'hcp-mca-review' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $pending as $m ) : ?>
					<tr>
						<td><code><?php echo esc_html( $m['slug'] ); ?></code></td>
						<td><?php echo esc_html( $m['description'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem;">
				<input type="hidden" name="action" value="<?php echo esc_attr( HCP_MCA_MIGRATIONS_NONCE ); ?>" />
				<?php wp_nonce_field( HCP_MCA_MIGRATIONS_NONCE ); ?>
				<?php submit_button( __( 'Run pending migrations', 'hcp-mca-review' ), 'primary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<h2 style="margin-top:2rem;"><?php esc_html_e( 'Applied', 'hcp-mca-review' ); ?> (<?php echo count( $applied ); ?>)</h2>
		<?php if ( ! $applied ) : ?>
			<p><em><?php esc_html_e( 'No migrations have been applied yet.', 'hcp-mca-review' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Slug', 'hcp-mca-review' ); ?></th><th><?php esc_html_e( 'Applied at', 'hcp-mca-review' ); ?></th><th><?php esc_html_e( 'By', 'hcp-mca-review' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $applied as $slug => $meta ) : ?>
					<tr>
						<td><code><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo esc_html( $meta['applied_at'] ?? '' ); ?></td>
						<td>
							<?php
							$user_id = (int) ( $meta['applied_by'] ?? 0 );
							$user    = $user_id ? get_user_by( 'id', $user_id ) : null;
							echo esc_html( $user ? $user->user_login : ( $user_id ? "uid:$user_id" : 'system' ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

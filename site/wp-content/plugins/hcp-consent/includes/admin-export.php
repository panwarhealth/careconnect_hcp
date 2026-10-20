<?php
/**
 * Users → Marketing consent: answer counts and CSV downloads.
 *
 * The send list is everyone whose most recent marketing answer is Yes; it is
 * what the eDM mailer may send to. The full log is every row, for proving
 * what someone agreed to and when.
 */

defined( 'ABSPATH' ) || exit;

const HCP_CONSENT_EXPORT_CAP = 'list_users';

add_action( 'admin_menu', 'hcp_consent_export_menu' );
function hcp_consent_export_menu(): void {
	add_users_page( 'Marketing consent', 'Marketing consent', HCP_CONSENT_EXPORT_CAP, 'hcp-consent', 'hcp_consent_export_page' );
}

/**
 * Each user's latest marketing answer, joined to their account.
 *
 * @param string|null $response Limit to one answer, or null for both.
 */
function hcp_consent_current_answers( ?string $response = null ): array {
	global $wpdb;

	$log   = hcp_consent_table();
	$where = $response ? $wpdb->prepare( 'AND l.response = %s', $response ) : '';

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT l.user_id, l.response, l.method, l.tc_version, l.created_at,
			        u.user_email, u.user_login,
			        fn.meta_value AS first_name, ln.meta_value AS last_name, ah.meta_value AS ahpra_id
			 FROM {$log} l
			 JOIN (SELECT user_id, MAX(id) AS id FROM {$log} WHERE response IN (%s, %s) GROUP BY user_id) latest ON latest.id = l.id
			 JOIN {$wpdb->users} u ON u.ID = l.user_id
			 LEFT JOIN {$wpdb->usermeta} fn ON fn.user_id = u.ID AND fn.meta_key = 'first_name'
			 LEFT JOIN {$wpdb->usermeta} ln ON ln.user_id = u.ID AND ln.meta_key = 'last_name'
			 LEFT JOIN {$wpdb->usermeta} ah ON ah.user_id = u.ID AND ah.meta_key = 'ahpra_id'
			 WHERE 1 = 1 {$where}
			 ORDER BY l.created_at",
			HCP_CONSENT_OPTED_IN,
			HCP_CONSENT_OPTED_OUT
		),
		ARRAY_A
	);
}

function hcp_consent_export_page(): void {
	if ( ! current_user_can( HCP_CONSENT_EXPORT_CAP ) ) {
		return;
	}

	$answers = hcp_consent_current_answers();
	$yes     = count( array_filter( $answers, static fn( $a ) => HCP_CONSENT_OPTED_IN === $a['response'] ) );
	$no      = count( $answers ) - $yes;
	$users   = (int) count_users()['total_users'];

	$link = static function ( string $file, string $label, string $class ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=hcp_consent_export&file=' . $file ), 'hcp_consent_export' );
		printf( '<a class="button %s" href="%s">%s</a> ', esc_attr( $class ), esc_url( $url ), esc_html( $label ) );
	};
	?>
	<div class="wrap">
		<h1>Marketing consent</h1>
		<p>Answers to the marketing consent question (pop-up, Edit Your Profile and the consent preferences page). Only people whose current answer is Yes may be sent marketing.</p>
		<table class="widefat striped" style="max-width:32rem">
			<tbody>
				<tr><th>Yes (on the send list)</th><td><strong><?php echo esc_html( number_format_i18n( $yes ) ); ?></strong></td></tr>
				<tr><th>No</th><td><?php echo esc_html( number_format_i18n( $no ) ); ?></td></tr>
				<tr><th>Not answered yet</th><td><?php echo esc_html( number_format_i18n( max( 0, $users - $yes - $no ) ) ); ?></td></tr>
				<tr><th>Registered users</th><td><?php echo esc_html( number_format_i18n( $users ) ); ?></td></tr>
			</tbody>
		</table>
		<p style="margin-top:1.5rem">
			<?php
			$link( 'send-list', 'Download send list (CSV)', 'button-primary' );
			$link( 'log', 'Download full log (CSV)', '' );
			?>
		</p>
		<p class="description">Send list: current Yes answers only. Full log: every answer ever given, including changes of mind and Terms acceptance at registration.</p>
	</div>
	<?php
}

add_action( 'admin_post_hcp_consent_export', 'hcp_consent_export_download' );
function hcp_consent_export_download(): void {
	if ( ! current_user_can( HCP_CONSENT_EXPORT_CAP ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'hcp_consent_export' );

	$file = sanitize_key( $_GET['file'] ?? '' );
	if ( 'send-list' === $file ) {
		$header = array( 'first_name', 'last_name', 'email', 'ahpra_id', 'user_id', 'consented_at', 'method', 'tc_version' );
		$rows   = array_map(
			static fn( $a ) => array( $a['first_name'], $a['last_name'], $a['user_email'], $a['ahpra_id'], $a['user_id'], $a['created_at'], $a['method'], $a['tc_version'] ),
			hcp_consent_current_answers( HCP_CONSENT_OPTED_IN )
		);
	} elseif ( 'log' === $file ) {
		global $wpdb;
		$header = array( 'id', 'user_id', 'email', 'response', 'method', 'tc_version', 'statement', 'ip_address', 'user_agent', 'created_at' );
		$rows   = $wpdb->get_results(
			'SELECT l.id, l.user_id, u.user_email, l.response, l.method, l.tc_version, l.statement, l.ip_address, l.user_agent, l.created_at
			 FROM ' . hcp_consent_table() . " l LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id ORDER BY l.id",
			ARRAY_N
		);
	} else {
		wp_die( 'Unknown export.', 400 );
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="marketing-consent-' . $file . '-' . wp_date( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM, so Excel reads UTF-8 names correctly.
	fputcsv( $out, $header );
	foreach ( $rows as $row ) {
		fputcsv( $out, array_map( 'hcp_consent_csv_cell', $row ) );
	}
	fclose( $out );
	exit;
}

/**
 * Names are typed by users; a leading = + - @ would run as a formula in Excel.
 */
function hcp_consent_csv_cell( $value ): string {
	$value = (string) $value;

	return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
}

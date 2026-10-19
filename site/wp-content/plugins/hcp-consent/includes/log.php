<?php
/**
 * Consent audit log.
 *
 * Append-only: every answer is a new row, never an update, so the history of
 * what someone agreed to, when, from where and under which version of the
 * Terms survives a change of mind. A person's current marketing preference is
 * their most recent opted-in / opted-out row.
 */

defined( 'ABSPATH' ) || exit;

const HCP_CONSENT_DB_VERSION = '1';
const HCP_CONSENT_DB_OPTION  = 'hcp_consent_db_version';

const HCP_CONSENT_OPTED_IN   = 'opted-in';
const HCP_CONSENT_OPTED_OUT  = 'opted-out';
const HCP_CONSENT_TC_ACCEPTED = 'tc-accepted';

const HCP_CONSENT_METHODS = array( 'in-portal-modal', 'account-settings', 'consent-page', 'registration' );

function hcp_consent_table(): string {
	global $wpdb;

	return $wpdb->prefix . 'hcp_consent_log';
}

function hcp_consent_install(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = hcp_consent_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			response VARCHAR(20) NOT NULL,
			method VARCHAR(20) NOT NULL,
			tc_version VARCHAR(20) NOT NULL DEFAULT '',
			statement TEXT NOT NULL,
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_response (user_id, response),
			KEY created_at (created_at)
		) {$collate};"
	);

	update_option( HCP_CONSENT_DB_OPTION, HCP_CONSENT_DB_VERSION );
}

add_action( 'plugins_loaded', 'hcp_consent_maybe_install' );
function hcp_consent_maybe_install(): void {
	if ( get_option( HCP_CONSENT_DB_OPTION ) !== HCP_CONSENT_DB_VERSION ) {
		hcp_consent_install();
	}
}

/**
 * @param string $statement The words the person was shown when they answered.
 */
function hcp_consent_record( int $user_id, string $response, string $method, string $statement ): bool {
	global $wpdb;

	if ( $user_id <= 0 || ! in_array( $method, HCP_CONSENT_METHODS, true ) ) {
		return false;
	}

	$inserted = $wpdb->insert(
		hcp_consent_table(),
		array(
			'user_id'    => $user_id,
			'response'   => $response,
			'method'     => $method,
			'tc_version' => hcp_legal_version(),
			'statement'  => $statement,
			'ip_address' => hcp_consent_client_ip(),
			'user_agent' => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
			'created_at' => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		if ( function_exists( 'hcp_sentry_capture' ) ) {
			hcp_sentry_capture( 'Consent log insert failed', \Sentry\Severity::error(), array( 'method' => $method, 'response' => $response ) );
		}
		return false;
	}

	return true;
}

/**
 * The person's current marketing answer, or null if they have never given one.
 *
 * @return array{response:string, method:string, created_at:string}|null
 */
function hcp_consent_marketing_status( int $user_id ): ?array {
	global $wpdb;

	if ( $user_id <= 0 ) {
		return null;
	}

	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT response, method, created_at FROM ' . hcp_consent_table() . '
			 WHERE user_id = %d AND response IN (%s, %s)
			 ORDER BY id DESC LIMIT 1',
			$user_id,
			HCP_CONSENT_OPTED_IN,
			HCP_CONSENT_OPTED_OUT
		),
		ARRAY_A
	);

	return $row ?: null;
}

/**
 * Behind the Azure front end REMOTE_ADDR is the proxy, and the client is the
 * first X-Forwarded-For entry; on the Stratus host REMOTE_ADDR is the client.
 * Only a value that parses as an IP is stored.
 */
function hcp_consent_client_ip(): string {
	$candidates = array();
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$candidates[] = trim( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
	}
	$candidates[] = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

	foreach ( $candidates as $ip ) {
		// Azure appends the client port: "1.2.3.4:5678".
		if ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m ) ) {
			$ip = $m[1];
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}

	return '';
}

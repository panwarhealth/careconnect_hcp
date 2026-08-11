<?php
/**
 * Impression tracking.
 *
 * Two independent records. This table is the reliable one: how many times a
 * pop-up appeared, on what page, and when. GA4 gets the same events for funnel
 * work, but ad blockers and consent tools drop beacons, so its count will be
 * lower. Expect a gap; the table is the source of truth.
 *
 * A row is written when the pop-up actually appears on screen, not when its
 * markup is emitted, so a dwell timer that never elapses is never counted.
 */

defined( 'ABSPATH' ) || exit;

const HCP_POPUPS_DB_VERSION = '1';
const HCP_POPUPS_DB_OPTION  = 'hcp_popups_db_version';
const HCP_POPUPS_EVENTS     = array( 'shown', 'dismissed', 'cta_click' );

function hcp_popups_table(): string {
	global $wpdb;

	return $wpdb->prefix . 'hcp_popup_events';
}

function hcp_popups_install(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = hcp_popups_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			popup_id VARCHAR(64) NOT NULL,
			event VARCHAR(20) NOT NULL,
			user_id BIGINT UNSIGNED NULL,
			session_id VARCHAR(40) NOT NULL DEFAULT '',
			page_path VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY popup_event (popup_id, event),
			KEY created_at (created_at),
			KEY page_path (page_path(100))
		) {$collate};"
	);

	update_option( HCP_POPUPS_DB_OPTION, HCP_POPUPS_DB_VERSION );
}

add_action( 'plugins_loaded', 'hcp_popups_maybe_install' );
function hcp_popups_maybe_install(): void {
	if ( get_option( HCP_POPUPS_DB_OPTION ) !== HCP_POPUPS_DB_VERSION ) {
		hcp_popups_install();
	}
}

/**
 * Per-visit id, so impressions, dismissals and clicks from one sitting tie
 * together. Session cookie by design: a new visit is a new browser session, and
 * there is no permanent flag to clear when a campaign is re-run.
 */
function hcp_popups_session_id(): string {
	$sid = (string) ( $_COOKIE[ HCP_POPUPS_SESSION_COOKIE ] ?? '' );

	if ( ! preg_match( '/^[a-f0-9]{32}$/', $sid ) ) {
		$sid = md5( wp_generate_password( 32, false ) . microtime() );
		if ( ! headers_sent() ) {
			setcookie( HCP_POPUPS_SESSION_COOKIE, $sid, array( 'path' => COOKIEPATH ? COOKIEPATH : '/', 'samesite' => 'Lax', 'secure' => is_ssl() ) );
		}
		$_COOKIE[ HCP_POPUPS_SESSION_COOKIE ] = $sid;
	}

	return $sid;
}

add_action( 'rest_api_init', 'hcp_popups_register_routes' );
function hcp_popups_register_routes(): void {
	register_rest_route(
		'hcp-popups/v1',
		'/event',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'hcp_popups_record_event',
			'args'                => array(
				'popup_id'   => array( 'required' => true, 'type' => 'string' ),
				'event'      => array( 'required' => true, 'type' => 'string' ),
				'page_path'  => array( 'type' => 'string' ),
				'session_id' => array( 'type' => 'string' ),
			),
		)
	);
}

function hcp_popups_record_event( WP_REST_Request $request ) {
	global $wpdb;

	$event = (string) $request->get_param( 'event' );
	if ( ! in_array( $event, HCP_POPUPS_EVENTS, true ) ) {
		return new WP_Error( 'hcp_popups_bad_event', 'Unknown event.', array( 'status' => 400 ) );
	}

	$wpdb->insert(
		hcp_popups_table(),
		array(
			'popup_id'   => substr( sanitize_key( $request->get_param( 'popup_id' ) ), 0, 64 ),
			'event'      => $event,
			'user_id'    => get_current_user_id() ?: null,
			'session_id' => substr( preg_replace( '/[^a-f0-9]/', '', (string) $request->get_param( 'session_id' ) ), 0, 40 ),
			'page_path'  => substr( (string) wp_parse_url( (string) $request->get_param( 'page_path' ), PHP_URL_PATH ), 0, 255 ),
			'created_at' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%d', '%s', '%s', '%s' )
	);

	return rest_ensure_response( array( 'ok' => true ) );
}

<?php
/**
 * Auto-recover an admin stranded in a switched "Login as User" session.
 *
 * The Login as User plugin (login-as-user) replaces the whole browser session
 * when switching into a member, so a stale wp-admin tab keeps working as the
 * member and dead-ends on "Sorry, you are not allowed to access this page".
 * When that denial (or a denied re-switch attempt) happens and the plugin's
 * old-user cookie proves an admin is underneath, bounce through the plugin's
 * own switch-back action and land on the page that was originally requested.
 *
 * Self-contained on purpose: reads the plugin's cookie and builds its
 * nonce-secured switch-back URL directly, because the plugin's initializer
 * (initialize_w357_login_as_user) is not safely re-callable.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The admin hiding under the current switched session, or null.
 */
function hcp_mca_login_as_rescue_old_admin(): ?WP_User {
	if ( ! class_exists( 'w357LoginAsUser' ) ) {
		return null;
	}
	$cookie = $_COOKIE[ 'wp_loginasuser_olduser_' . COOKIEHASH ] ?? '';
	if ( '' === $cookie ) {
		return null;
	}
	$old_id = wp_validate_auth_cookie( wp_unslash( $cookie ), 'logged_in' );
	if ( ! $old_id ) {
		return null;
	}
	$old = get_userdata( $old_id );
	return ( $old && user_can( $old, 'edit_users' ) ) ? $old : null;
}

/**
 * Redirect through the plugin's switch-back action, returning to $redirect_to.
 */
function hcp_mca_login_as_rescue_redirect( WP_User $old, string $redirect_to ): void {
	// Not wp_nonce_url(): that HTML-escapes the URL, which corrupts a Location header.
	$url = add_query_arg(
		array(
			'action'      => 'login_as_olduser',
			'redirect_to' => rawurlencode( $redirect_to ),
			'_wpnonce'    => wp_create_nonce( "login_as_olduser_{$old->ID}" ),
		),
		site_url( '/' )
	);
	wp_safe_redirect( $url );
	exit;
}

/**
 * Case 1: a switched session lands anywhere in wp-admin (stale admin tab).
 * The member identity has no business in wp-admin, and most admin screens
 * would wp_die on their own capability checks (each with a different hook or
 * none at all) — so treat any wp-admin request while switched as "I meant to
 * be the admin again" and switch back, continuing to the requested URL.
 */
add_action( 'admin_init', function (): void {
	// admin-post.php serves front-end form handlers; hijacking a POST there
	// would eat the submission of whoever we're switched into.
	if ( wp_doing_ajax() || 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ) || current_user_can( 'edit_users' ) ) {
		return;
	}
	$old = hcp_mca_login_as_rescue_old_admin();
	if ( ! $old ) {
		return;
	}
	$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
	hcp_mca_login_as_rescue_redirect( $old, home_url( $request_uri ) );
}, 1 );

/**
 * Case 2: switched session clicks another "Login as user" link (stale tab).
 * The plugin would wp_die on its capability check at init priority 5; step in
 * at priority 4 and switch back to the admin on the target user's profile
 * instead, so the next login-as click is one made with a fresh nonce.
 */
add_action( 'init', function (): void {
	if ( 'login_as_user' !== ( $_REQUEST['action'] ?? '' ) || is_admin() ) {
		return;
	}
	$target = absint( $_REQUEST['user_id'] ?? 0 );
	if ( ! $target || current_user_can( 'edit_users' ) ) {
		return;
	}
	$old = hcp_mca_login_as_rescue_old_admin();
	if ( ! $old ) {
		return;
	}
	hcp_mca_login_as_rescue_redirect( $old, admin_url( 'user-edit.php?user_id=' . $target ) );
}, 4 );

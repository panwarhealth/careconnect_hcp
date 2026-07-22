<?php
/**
 * Auto-attach the HCP membership to manually created subscribers.
 *
 * A WP account and an RCP membership are separate things on this site: the role only
 * decides whether someone can log in, while every gated area checks
 * `rcp_user_has_active_membership()`. An account created by hand in
 * wp-admin -> Users -> Add New therefore logs in fine but gets bounced to /register/,
 * which reads as "you need to sign in" even though the user is signed in.
 *
 * Real HCP signups never hit this because the AHPRA registration flow creates the
 * membership alongside the user. This covers the manual path only:
 * `edit_user_created_user` fires exclusively from wp-admin/user-new.php, so front-end
 * registrations are untouched and nobody skips AHPRA verification.
 */

defined( 'ABSPATH' ) || exit;

/** Membership level granted to manually created subscribers (level 22 = "HCP"). */
const HCP_AUTO_MEMBERSHIP_LEVEL = 22;

/**
 * Give a manually created subscriber an active HCP membership.
 *
 * Idempotent and defensive: never throws, so a problem here can't break user creation.
 *
 * @param int    $user_id Newly created user.
 * @param string $notify  Which notification wp-admin sent (unused).
 */
function hcp_mca_auto_attach_membership( $user_id, $notify = '' ) {
	if ( ! function_exists( 'rcp_add_membership' ) || ! function_exists( 'rcp_add_customer' ) ) {
		return; // RCP not available — nothing to do.
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'subscriber', (array) $user->roles, true ) ) {
		return; // Only plain subscribers; admins/editors bypass the gate anyway.
	}

	if ( function_exists( 'rcp_user_has_active_membership' ) && rcp_user_has_active_membership( $user_id ) ) {
		return; // Already entitled.
	}

	$level = (int) apply_filters( 'hcp_mca_auto_membership_level', HCP_AUTO_MEMBERSHIP_LEVEL, $user_id );
	if ( $level <= 0 ) {
		return; // Filtered off.
	}

	try {
		$customer    = function_exists( 'rcp_get_customer_by_user_id' ) ? rcp_get_customer_by_user_id( $user_id ) : null;
		$customer_id = $customer ? $customer->get_id() : rcp_add_customer( [ 'user_id' => $user_id ] );
		if ( ! $customer_id ) {
			throw new \RuntimeException( 'could not resolve an RCP customer' );
		}

		$membership_id = rcp_add_membership( [
			'customer_id'     => $customer_id,
			'object_id'       => $level,
			'object_type'     => 'membership',
			'status'          => 'active',
			'created_date'    => current_time( 'mysql' ),
			'expiration_date' => 'none',
		] );

		if ( ! $membership_id ) {
			throw new \RuntimeException( 'rcp_add_membership() returned no id' );
		}

		set_transient( 'hcp_mca_auto_membership_' . $user_id, (int) $membership_id, MINUTE_IN_SECONDS );
	} catch ( \Throwable $e ) {
		// Never surface as a fatal during user creation — the account is still usable.
		error_log( sprintf( 'hcp_mca_auto_attach_membership: failed for user %d — %s', $user_id, $e->getMessage() ) );
	}
}
add_action( 'edit_user_created_user', 'hcp_mca_auto_attach_membership', 10, 2 );

/**
 * Confirm on-screen that the membership was attached, so the admin isn't left guessing.
 */
function hcp_mca_auto_membership_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'users' !== $screen->id ) {
		return;
	}

	$user_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $user_id ) {
		return;
	}

	$membership_id = get_transient( 'hcp_mca_auto_membership_' . $user_id );
	if ( ! $membership_id ) {
		return;
	}
	delete_transient( 'hcp_mca_auto_membership_' . $user_id );

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( sprintf(
			'Active HCP membership (level %d) attached automatically — this account can access gated content.',
			HCP_AUTO_MEMBERSHIP_LEVEL
		) )
	);
}
add_action( 'admin_notices', 'hcp_mca_auto_membership_notice' );

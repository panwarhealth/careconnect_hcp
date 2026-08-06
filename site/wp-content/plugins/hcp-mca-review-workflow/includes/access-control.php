<?php
/**
 * Access policy for Mini Clinical Audit data.
 *
 * Only accounts carrying the hcp_mca_reviewer role may view audit submissions,
 * the audit course chain, and the approval controls. The role is additive: it
 * is granted alongside administrator, not instead of it.
 *
 * This is a visibility control, not a security boundary. Other administrators
 * retain manage_options and can grant themselves the capability back.
 */

defined( 'ABSPATH' ) || exit;

const HCP_MCA_VIEW_CAP = 'view_mca_data';
const HCP_MCA_ROLE     = 'hcp_mca_reviewer';

const HCP_MCA_FORM_IDS_TRANSIENT = 'hcp_mca_restricted_form_ids';
const HCP_MCA_POST_IDS_TRANSIENT = 'hcp_mca_restricted_post_ids';

/**
 * Post types covered by the restriction.
 *
 * Every LearnDash object on this site belongs to the CPD offering, so the
 * whole type is restricted rather than an enumerated list of IDs. That is
 * deliberately fail-closed: content added later is covered without anyone
 * having to remember to add it. An unrelated future course would need an
 * explicit exemption here.
 */
function hcp_mca_restricted_post_types(): array {
	return [
		'sfwd-courses',
		'sfwd-lessons',
		'sfwd-topic',
		'sfwd-quiz',
		'sfwd-certificates',
		'groups',
	];
}

/**
 * Formidable forms holding course data: the contact form, the surveys, the
 * audit and the activity evaluation, plus any child forms such as repeaters.
 */
function hcp_mca_restricted_form_ids(): array {
	$cached = get_transient( HCP_MCA_FORM_IDS_TRANSIENT );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	$parents = [
		HCP_MCA_CONTACT_FORM_ID,
		HCP_MCA_PRE_SURVEY_FORM_ID,
		HCP_MCA_POST_SURVEY_FORM_ID,
		HCP_MCA_AUDIT_FORM_ID,
		HCP_MCA_EVAL_FORM_ID,
	];

	$children = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}frm_forms WHERE parent_form_id IN ( " . implode( ',', array_fill( 0, count( $parents ), '%d' ) ) . ' )',
			$parents
		)
	);

	$ids = array_values( array_unique( array_merge( $parents, array_map( 'absint', (array) $children ) ) ) );

	set_transient( HCP_MCA_FORM_IDS_TRANSIENT, $ids, HOUR_IN_SECONDS );

	return $ids;
}

/**
 * Every post of a restricted type.
 *
 * Used to filter listings. Direct request guards check the post type itself,
 * so a post created after this was cached is still refused.
 */
function hcp_mca_restricted_post_ids(): array {
	$cached = get_transient( HCP_MCA_POST_IDS_TRANSIENT );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	$types = hcp_mca_restricted_post_types();

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( " . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . " ) AND post_status != 'trash'",
			$types
		)
	);

	$ids = array_map( 'absint', (array) $ids );

	set_transient( HCP_MCA_POST_IDS_TRANSIENT, $ids, HOUR_IN_SECONDS );

	return $ids;
}

/**
 * Drop the cached ID lists when restricted content changes.
 */
function hcp_mca_flush_restricted_caches( $post_id = 0 ): void {
	if ( $post_id && ! in_array( get_post_type( $post_id ), hcp_mca_restricted_post_types(), true ) ) {
		return;
	}

	delete_transient( HCP_MCA_POST_IDS_TRANSIENT );
	delete_transient( HCP_MCA_FORM_IDS_TRANSIENT );
}

add_action( 'save_post', 'hcp_mca_flush_restricted_caches' );
add_action( 'deleted_post', 'hcp_mca_flush_restricted_caches' );
add_action( 'trashed_post', 'hcp_mca_flush_restricted_caches' );

/**
 * Whether the current user may view audit data.
 *
 * Falls back to manage_options while the role is absent, so a plugin update
 * that lands before its migration runs cannot lock the CPD reviewers out of
 * their own approval workflow.
 */
function hcp_mca_current_user_can_view(): bool {
	if ( ! hcp_mca_access_control_active() ) {
		return current_user_can( 'manage_options' );
	}

	return current_user_can( HCP_MCA_VIEW_CAP );
}

/**
 * Whether the restriction is live. False until the migration registers the role.
 */
function hcp_mca_access_control_active(): bool {
	return null !== get_role( HCP_MCA_ROLE );
}

/**
 * Deny the current request with a permission error.
 */
function hcp_mca_deny_access(): void {
	wp_die(
		esc_html__( 'You do not have permission to view this course data.', 'hcp-mca-review' ),
		esc_html__( 'Access denied', 'hcp-mca-review' ),
		[ 'response' => 403 ]
	);
}

<?php
/**
 * Enforcement of the audit access policy across wp-admin.
 *
 * Two layers. Direct requests are refused outright, because removing a menu
 * item does nothing against a pasted URL. Lists and menus are then filtered so
 * restricted objects are not advertised in the first place.
 *
 * Every callback re-checks the gatekeeper rather than the hooks being
 * registered conditionally, so the current user is always resolved by the time
 * the check runs.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'hcp_mca_guard_admin_request', 1 );
add_action( 'wp_ajax_frm_entries_csv', 'hcp_mca_guard_entries_export', 0 );
add_action( 'wp_ajax_nopriv_frm_entries_csv', 'hcp_mca_guard_entries_export', 0 );
add_action( 'admin_menu', 'hcp_mca_remove_restricted_menus', 999 );
add_action( 'admin_init', 'hcp_mca_remove_learndash_profile_section', 20 );
add_action( 'admin_init', 'hcp_mca_remove_raw_user_meta_panel', 20 );
add_action( 'wp_ajax_SS88_VUM_export', 'hcp_mca_guard_user_meta_ajax', 0 );
add_action( 'wp_ajax_SS88_VUM_delete', 'hcp_mca_guard_user_meta_ajax', 0 );
add_filter( 'manage_users_columns', 'hcp_mca_filter_user_columns', 999 );
add_action( 'admin_footer', 'hcp_mca_remove_rank_math_meta_payload', -1 );
add_filter( 'wp_privacy_personal_data_exporters', 'hcp_mca_filter_privacy_handlers', 999 );
add_filter( 'wp_privacy_personal_data_erasers', 'hcp_mca_filter_privacy_handlers', 999 );
add_action( 'admin_bar_menu', 'hcp_mca_remove_admin_bar_nodes', 999 );
add_action( 'pre_get_posts', 'hcp_mca_exclude_restricted_posts' );
add_filter( 'frm_forms_list_class', 'hcp_mca_filter_forms_list_class', 99 );
add_filter( 'frm_entries_list_class', 'hcp_mca_filter_entries_list_class', 99 );

/**
 * Refuse any admin request targeting a restricted object.
 */
function hcp_mca_guard_admin_request(): void {
	if ( hcp_mca_current_user_can_view() ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing check, not a state change.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( in_array( $page, [ 'hcp-mca-admin', 'tbst-custom-reports' ], true ) ) {
		hcp_mca_deny_access();
	}

	if ( 'formidable' === $page || 'formidable-entries' === $page ) {
		hcp_mca_guard_formidable_request( $page );
	}

	if ( 'formidable-import' === $page ) {
		hcp_mca_guard_formidable_export();
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	hcp_mca_guard_post_request();
}

/**
 * Formidable form builder, settings and entry screens.
 */
function hcp_mca_guard_formidable_request( string $page ): void {
	$restricted = hcp_mca_restricted_form_ids();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$form = isset( $_REQUEST['form'] ) ? absint( $_REQUEST['form'] ) : 0;
	if ( $form && in_array( $form, $restricted, true ) ) {
		hcp_mca_deny_access();
	}

	$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
	if ( ! $id ) {
		return;
	}

	// On the forms screen the id is a form id.
	if ( 'formidable' === $page ) {
		if ( in_array( $id, $restricted, true ) ) {
			hcp_mca_deny_access();
		}

		return;
	}

	// On the entries screen the id is an entry id, so resolve its parent form.
	if ( ! class_exists( 'FrmEntry' ) ) {
		return;
	}

	$entry = FrmEntry::getOne( $id );
	if ( $entry && in_array( (int) $entry->form_id, $restricted, true ) ) {
		hcp_mca_deny_access();
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/**
 * Formidable Import/Export screen: block XML and CSV exports naming a
 * restricted form.
 */
function hcp_mca_guard_formidable_export(): void {
	if ( empty( $_POST['frm_export_forms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Formidable verifies its own nonce; this only decides whether to refuse earlier.
		return;
	}

	$ids = array_map( 'absint', (array) wp_unslash( $_POST['frm_export_forms'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( array_intersect( $ids, hcp_mca_restricted_form_ids() ) ) {
		hcp_mca_deny_access();
	}
}

/**
 * The per-form CSV export served over admin-ajax.
 */
function hcp_mca_guard_entries_export(): void {
	if ( hcp_mca_current_user_can_view() ) {
		return;
	}

	$form = isset( $_REQUEST['form'] ) ? absint( $_REQUEST['form'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $form || in_array( $form, hcp_mca_restricted_form_ids(), true ) ) {
		hcp_mca_deny_access();
	}
}

/**
 * Post edit screens and bulk actions for the audit course chain.
 */
function hcp_mca_guard_post_request(): void {
	global $pagenow;

	if ( ! in_array( $pagenow, [ 'post.php', 'edit.php', 'post-new.php' ], true ) ) {
		return;
	}

	$types = hcp_mca_restricted_post_types();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';
	if ( $post_type && in_array( $post_type, $types, true ) ) {
		hcp_mca_deny_access();
	}

	$requested = [];
	foreach ( [ 'post', 'post_ID' ] as $key ) {
		if ( isset( $_REQUEST[ $key ] ) ) {
			$requested = array_merge( $requested, array_map( 'absint', (array) wp_unslash( $_REQUEST[ $key ] ) ) );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Checked by type rather than against the cached ID list, so a post created
	// since the cache was built is still refused.
	foreach ( array_filter( $requested ) as $id ) {
		if ( in_array( get_post_type( $id ), $types, true ) ) {
			hcp_mca_deny_access();
		}
	}
}

/**
 * Drop restricted menu entries.
 *
 * The pages stay registered under manage_options so the gatekeeper's fail-safe
 * keeps working before the migration runs; visibility is decided here instead.
 */
function hcp_mca_remove_restricted_menus(): void {
	if ( hcp_mca_current_user_can_view() ) {
		return;
	}

	remove_menu_page( 'hcp-mca-admin' );
	remove_submenu_page( 'options-general.php', 'tbst-custom-reports' );

	// Every LearnDash object is restricted, so the LMS menu would only lead to
	// empty lists and permission errors.
	remove_menu_page( 'learndash-lms' );

	foreach ( hcp_mca_restricted_post_types() as $type ) {
		remove_menu_page( 'edit.php?post_type=' . $type );
	}
}

/**
 * Strip LearnDash's own block from the user profile screens.
 *
 * Narrowing its queries is not enough: LearnDash only rebuilds its course
 * progress list when the query returns rows, and falls back to the raw
 * unfiltered list from user meta when it returns none. Since every LearnDash
 * object is restricted, the whole block goes.
 */
function hcp_mca_remove_learndash_profile_section(): void {
	if ( hcp_mca_current_user_can_view() ) {
		return;
	}

	global $wp_filter;

	foreach ( [ 'show_user_profile', 'edit_user_profile', 'edit_user_profile_update', 'personal_options_update' ] as $hook ) {
		if ( empty( $wp_filter[ $hook ] ) ) {
			continue;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( ! is_array( $callback['function'] ) || ! is_object( $callback['function'][0] ) ) {
					continue;
				}

				if ( $callback['function'][0] instanceof Learndash_Admin_User_Profile_Edit ) {
					remove_action( $hook, $callback['function'], $priority );
				}
			}
		}
	}
}

/**
 * Strip the View User Meta panel from the profile screens.
 *
 * It dumps every user meta row verbatim, which includes course progress,
 * quiz results, completion timestamps and enrolment dates. Hiding the course
 * UI is pointless while the raw values sit underneath it.
 */
function hcp_mca_remove_raw_user_meta_panel(): void {
	if ( hcp_mca_current_user_can_view() || ! class_exists( 'SS88_ViewUserMetadata' ) ) {
		return;
	}

	global $wp_filter;

	foreach ( [ 'show_user_profile', 'edit_user_profile' ] as $hook ) {
		if ( empty( $wp_filter[ $hook ] ) ) {
			continue;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( ! is_array( $callback['function'] ) || ! is_object( $callback['function'][0] ) ) {
					continue;
				}

				if ( $callback['function'][0] instanceof SS88_ViewUserMetadata ) {
					remove_action( $hook, $callback['function'], $priority );
				}
			}
		}
	}
}

/**
 * Refuse the View User Meta export and delete endpoints, which would otherwise
 * hand over the same data the panel was hidden to protect.
 */
function hcp_mca_guard_user_meta_ajax(): void {
	if ( hcp_mca_current_user_can_view() ) {
		return;
	}

	wp_send_json_error(
		[
			'httpcode' => 403,
			'body'     => __( 'You do not have permission to view this course data.', 'hcp-mca-review' ),
		],
		403
	);
}

/**
 * Drop the LearnDash enrolment columns from the Users list table.
 *
 * @param array $columns
 * @return array
 */
function hcp_mca_filter_user_columns( $columns ) {
	if ( hcp_mca_current_user_can_view() ) {
		return $columns;
	}

	foreach ( array_keys( (array) $columns ) as $key ) {
		$key = (string) $key;

		if ( str_starts_with( $key, 'learndash' ) || 'groups_courses' === $key ) {
			unset( $columns[ $key ] );
		}
	}

	return $columns;
}

/**
 * Drop Rank Math's copy of the user's meta from its admin JSON payload.
 *
 * On user-edit screens Rank Math serialises every meta row into inline
 * JavaScript for its variable picker, course progress and enrolment dates
 * included. Runs before its own output callback on admin_footer.
 */
function hcp_mca_remove_rank_math_meta_payload(): void {
	if ( hcp_mca_current_user_can_view() || ! class_exists( 'RankMath\Helper' ) ) {
		return;
	}

	RankMath\Helper::remove_json( 'customFields', 'rankMath' );
}

/**
 * Remove the course and form handlers from the privacy export and erase tools.
 *
 * Tools > Export Personal Data would otherwise package up course progress and
 * every form entry, audit answers included, as a downloadable file, and the
 * erase tool would delete the same records. Core WordPress data is untouched.
 *
 * @param array $handlers
 * @return array
 */
function hcp_mca_filter_privacy_handlers( $handlers ) {
	if ( hcp_mca_current_user_can_view() ) {
		return $handlers;
	}

	// LearnDash registers its erasers with numeric keys, so the callback owner
	// is matched as well as the key.
	foreach ( (array) $handlers as $key => $handler ) {
		$subject = strtolower( (string) $key );

		$callback = $handler['callback'] ?? null;
		if ( is_array( $callback ) ) {
			$subject .= ' ' . strtolower( is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0] );
		} elseif ( is_string( $callback ) ) {
			$subject .= ' ' . strtolower( $callback );
		}

		foreach ( [ 'learndash', 'formidable', 'frm' ] as $needle ) {
			if ( str_contains( $subject, $needle ) ) {
				unset( $handlers[ $key ] );
				break;
			}
		}
	}

	return $handlers;
}

/**
 * Drop "+ New" admin bar entries for restricted post types.
 *
 * @param WP_Admin_Bar $bar
 */
function hcp_mca_remove_admin_bar_nodes( $bar ): void {
	if ( hcp_mca_current_user_can_view() ) {
		return;
	}

	foreach ( hcp_mca_restricted_post_types() as $type ) {
		$bar->remove_node( 'new-' . $type );
	}
}

/**
 * Keep the audit course chain out of every admin query.
 *
 * This covers the LearnDash post list tables, the enrolled-courses binary
 * selector, and the course progress and quiz attempt blocks on user profiles,
 * all of which resolve their posts through WP_Query.
 */
function hcp_mca_exclude_restricted_posts( WP_Query $query ): void {
	if ( ! is_admin() || hcp_mca_current_user_can_view() ) {
		return;
	}

	$restricted = hcp_mca_restricted_post_ids();
	$post__in   = array_filter( array_map( 'absint', (array) $query->get( 'post__in' ) ) );

	/*
	 * WP_Query applies post__not_in only when post__in is absent, so an
	 * allow-list has to be narrowed rather than paired with a deny-list.
	 * An emptied post__in would read as "no restriction", hence the 0.
	 */
	if ( $post__in ) {
		$allowed = array_values( array_diff( $post__in, $restricted ) );
		$query->set( 'post__in', $allowed ?: [ 0 ] );

		return;
	}

	$excluded = array_merge( (array) $query->get( 'post__not_in' ), $restricted );

	$query->set( 'post__not_in', array_values( array_unique( array_filter( $excluded ) ) ) );
}

/**
 * Swap in a list class that hides the audit forms.
 *
 * Registered late so Formidable Pro has already substituted its own helper;
 * the guard then extends whatever won rather than displacing it.
 *
 * @param string $class
 * @return string
 */
function hcp_mca_filter_forms_list_class( $class ) {
	if ( hcp_mca_current_user_can_view() ) {
		return $class;
	}

	return hcp_mca_build_list_guard( $class, 'HCP_MCA_Forms_List_Base', 'HCP_MCA_Forms_List_Guard', 'class-hcp-mca-forms-list-guard.php' );
}

/**
 * @param string $class
 * @return string
 */
function hcp_mca_filter_entries_list_class( $class ) {
	if ( hcp_mca_current_user_can_view() ) {
		return $class;
	}

	return hcp_mca_build_list_guard( $class, 'HCP_MCA_Entries_List_Base', 'HCP_MCA_Entries_List_Guard', 'class-hcp-mca-entries-list-guard.php' );
}

/**
 * Alias the incoming class as the guard's parent, then declare the guard.
 *
 * The alias has to be in place before the subclass is declared, which is why
 * each guard sits in its own conditionally-included file. Falls back to the
 * unguarded class if anything is missing, so a Formidable change degrades to
 * the pre-existing behaviour rather than a fatal.
 *
 * @return string Class name to instantiate.
 */
function hcp_mca_build_list_guard( string $class, string $alias, string $guard, string $file ): string {
	if ( class_exists( $guard, false ) ) {
		return $guard;
	}

	if ( ! class_exists( $class ) || class_exists( $alias, false ) ) {
		return $class;
	}

	class_alias( $class, $alias );
	require_once HCP_MCA_PLUGIN_DIR . 'includes/' . $file;

	return class_exists( $guard, false ) ? $guard : $class;
}

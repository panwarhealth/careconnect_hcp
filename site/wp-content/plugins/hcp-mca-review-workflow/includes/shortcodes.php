<?php
/**
 * Frontend shortcodes for the MCA review workflow.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hcp_mca_audit_button', 'hcp_mca_render_audit_button' );

/**
 * Which variant a user should be sent to by default: the newest provisioned
 * one. Users with progress on an older variant go through the chooser instead.
 */
function hcp_mca_default_variant(): ?array {
	$variants = hcp_mca_variants();
	return $variants ? end( $variants ) : null;
}

/**
 * Variants the user has progress on, excluding the default one.
 */
function hcp_mca_user_older_variants_with_progress( int $user_id ): array {
	$default = hcp_mca_default_variant();
	$older   = [];
	foreach ( hcp_mca_variants() as $key => $variant ) {
		if ( $default && $key === $default['key'] ) {
			continue;
		}
		if ( hcp_mca_user_has_variant_progress( $user_id, $variant ) ) {
			$older[ $key ] = $variant;
		}
	}
	return $older;
}

function hcp_mca_state_button_label( array $state ): string {
	switch ( $state['state'] ) {
		case HCP_MCA_STATE_PARTIAL:
			return 'Resume';
		case HCP_MCA_STATE_AWAITING_REVIEW:
		case HCP_MCA_STATE_COMPLETE:
			return 'Review';
		default:
			return hcp_mca_user_has_draft( (int) get_current_user_id(), hcp_mca_variant( $state['variant'] ) ) ? 'Resume' : 'Start';
	}
}

function hcp_mca_user_has_draft( int $user_id, ?array $variant ): bool {
	global $wpdb;
	if ( $user_id <= 0 || null === $variant ) {
		return false;
	}
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM {$wpdb->prefix}frm_items WHERE user_id = %d AND form_id = %d AND is_draft = 1 LIMIT 1",
		$user_id, (int) $variant['audit_form']
	) );
}

/**
 * Render the Clinical Audit CTA button on the activity homepage.
 *
 * Users with progress on an older variant are sent to the chooser page;
 * everyone else goes straight to the default variant's course.
 */
function hcp_mca_render_audit_button(): string {
	$gate_message = '<p class="italic font-semibold">You must complete the Online Learning Module before commencing the Mini Clinical Audit</p>';
	$disabled     = sprintf(
		'<a class="btn cta mt-0 opacity-50 mb-0">%s</a>%s',
		esc_html__( 'Start Mini Clinical Audit', 'hcp-mca-review' ),
		$gate_message
	);

	$user_id = get_current_user_id();
	$default = hcp_mca_default_variant();
	if ( $user_id <= 0 || null === $default ) {
		return $disabled;
	}

	$state = hcp_mca_get_state( $user_id, $default );
	if ( empty( $state['learning_complete'] ) ) {
		return $disabled;
	}

	$older = hcp_mca_user_older_variants_with_progress( $user_id );
	if ( $older ) {
		$url   = home_url( '/' . HCP_MCA_CHOOSER_PAGE_SLUG . '/' );
		$state = hcp_mca_get_state( $user_id, reset( $older ) );
	} else {
		$url = get_permalink( (int) $default['course'] );
	}

	return sprintf(
		'<a class="btn cta mt-0" href="%s">%s Mini Clinical Audit</a>',
		esc_url( $url ),
		esc_html( hcp_mca_state_button_label( $state ) )
	);
}

add_shortcode( 'hcp_mca_audit_chooser', 'hcp_mca_render_audit_chooser' );

/**
 * Chooser page body: one card per variant the user can work on. The default
 * variant is always offered; older variants only when the user has progress.
 */
function hcp_mca_render_audit_chooser(): string {
	$user_id = get_current_user_id();
	$default = hcp_mca_default_variant();
	if ( $user_id <= 0 || null === $default ) {
		return '';
	}

	$state = hcp_mca_get_state( $user_id, $default );
	if ( empty( $state['learning_complete'] ) ) {
		return '<p class="italic font-semibold text-center">You must complete the Online Learning Module before commencing the Mini Clinical Audit.</p>';
	}

	$cards = [];
	foreach ( hcp_mca_user_older_variants_with_progress( $user_id ) as $variant ) {
		$cards[] = hcp_mca_render_variant_card( $user_id, $variant, true );
	}
	$cards[] = hcp_mca_render_variant_card( $user_id, $default, false );

	$cols = count( $cards ) > 1 ? 'md:grid-cols-2' : 'md:max-w-2xl';

	return '<div class="container grid ' . $cols . ' lg:max-w-7xl" style="gap:3rem;">' . implode( '', $cards ) . '</div>';
}

function hcp_mca_variant_status_text( int $user_id, array $variant, array $state ): string {
	switch ( $state['state'] ) {
		case HCP_MCA_STATE_COMPLETE:
			return 'Completed and approved';
		case HCP_MCA_STATE_AWAITING_REVIEW:
			return 'Submitted, awaiting review';
		case HCP_MCA_STATE_PARTIAL:
			return $state['has_audit_entry'] ? 'Audit submitted, activity evaluation outstanding' : 'Activity evaluation submitted, audit outstanding';
		default:
			return hcp_mca_user_has_draft( $user_id, $variant ) ? 'In progress, draft saved' : 'Not started';
	}
}

function hcp_mca_render_variant_card( int $user_id, array $variant, bool $is_older ): string {
	$state = hcp_mca_get_state( $user_id, $variant );

	$note = $is_older
		? 'You began this version before the audit was updated. You can finish it, or start the updated version. Both versions can be completed.'
		: 'The current version of the audit, with updated case study assessments.';

	return '<div class="card column border-l-4" style="border-left-color:#00B3D6">'
		. '<div class="card-body content-block shadow-md">'
		. '<h3 class=" ">' . esc_html( $variant['label'] ) . '</h3>'
		. '<hr class=" " />'
		. '<p class=" ">' . esc_html( $note ) . '</p>'
		. '<p class="font-bold">Approved RACGP CPD hours:<br />' . esc_html( $variant['hours'] ) . '</p>'
		. '<p class=" "><span class="font-bold">Your status:</span> ' . esc_html( hcp_mca_variant_status_text( $user_id, $variant, $state ) ) . '</p>'
		. sprintf(
			'<a class="btn cta mt-0" href="%s">%s this version</a>',
			esc_url( get_permalink( (int) $variant['course'] ) ),
			esc_html( hcp_mca_state_button_label( $state ) )
		)
		. '</div></div>';
}

/**
 * The chooser page exists only for users with something to choose between.
 * Anonymous visitors get the public landing page, matching the activity
 * homepage; users with no progress on an older variant go straight to the
 * current course.
 */
add_action( 'template_redirect', 'hcp_mca_chooser_redirects' );
function hcp_mca_chooser_redirects(): void {
	if ( ! is_page( HCP_MCA_CHOOSER_PAGE_SLUG ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/anal-fissures-breaking-the-cycle-and-the-stigma-landing/' ) );
		exit;
	}
	$default = hcp_mca_default_variant();
	if ( null !== $default && ! hcp_mca_user_older_variants_with_progress( get_current_user_id() ) ) {
		wp_safe_redirect( get_permalink( (int) $default['course'] ) );
		exit;
	}
}

// Old name kept as alias so any out-of-band content referencing it still renders.
add_shortcode( 'hcp_mca_learning_enrol_button',  'hcp_mca_render_learning_module_button' );
add_shortcode( 'hcp_mca_learning_module_button', 'hcp_mca_render_learning_module_button' );

/**
 * Always-render button for the prereq Online Learning Module (course 95553).
 *
 * Replaces the previous patchwork of [course_notstarted] / [course_inprogress] /
 * [course_complete] LearnDash shortcodes whose rendering was conditional on internal
 * LD state and could silently disappear when meta/activity got out of sync (e.g. after
 * an unenrol). One shortcode, one source of truth, one button — always present.
 *
 * Label adapts to actual course progress:
 *   - Not enrolled OR enrolled but not started   → "Start Online Learning Module"
 *   - In progress                                → "Resume Learning Module"
 *   - Completed                                  → "Review Learning Module"
 *
 * Click on the rendered button is intercepted by enrol-modal.js when the user lacks
 * a racgp_number; otherwise the click proceeds normally to the course page.
 */
function hcp_mca_render_learning_module_button(): string {
	$course_id  = HCP_MCA_LEARNING_COURSE_ID;
	$course_url = home_url( '/courses/anal-fissures-breaking-the-cycle-and-the-stigma/' );
	$label      = __( 'Start Online Learning Module', 'hcp-mca-review' );

	$user_id = get_current_user_id();
	if ( $user_id > 0 ) {
		// Read directly from the LearnDash activity table — it's the most honest
		// signal of "has this user actually opened the course yet". LD's own
		// learndash_course_status() returns "Not Started" until a lesson-level
		// progress row is written, which misses users who've started but only
		// touched intro content / surveys.
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT activity_status, activity_started
			 FROM {$wpdb->prefix}learndash_user_activity
			 WHERE user_id = %d AND post_id = %d AND activity_type = 'course'
			 ORDER BY activity_updated DESC LIMIT 1",
			$user_id, $course_id
		) );

		if ( $row ) {
			if ( (int) $row->activity_status === 1 ) {
				$label = __( 'Review Learning Module', 'hcp-mca-review' );
			} elseif ( (int) $row->activity_started > 0 ) {
				$label = __( 'Resume Learning Module', 'hcp-mca-review' );
			}
		}
	}

	return sprintf(
		'<a class="btn cta mt-0" href="%s">%s</a>',
		esc_url( $course_url ),
		esc_html( $label )
	);
}

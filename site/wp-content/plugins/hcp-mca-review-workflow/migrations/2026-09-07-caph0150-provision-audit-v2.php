<?php
/**
 * CAPH0150: provision the 2026 Clinical Audit variant alongside the legacy one.
 *
 * Duplicates the audit form (with its repeater child) and the activity
 * evaluation form, then builds a second LearnDash course (lesson + quiz),
 * certificate and completion notification pointing at the copies, adds the
 * new course to the idle reminder, and creates the chooser page.
 *
 * Content amendments to the 2026 form and course live in later migrations.
 * The legacy course, forms and entries are not touched.
 *
 * Idempotent: every step is keyed on the slug / form key it creates.
 */

defined( 'ABSPATH' ) || exit;

// Helpers are guarded: the runner requires this file once per discovery pass.

/**
 * Duplicate a Formidable form under a fixed key. Field keys of the copy are
 * the legacy keys behind HCP_MCA_V2_FIELD_KEY_PREFIX so later migrations and
 * JS can address them deterministically. Email actions on the copy are left
 * as drafts: the review plugin owns admin notifications.
 */
if ( ! function_exists( 'hcp_mca_v2_provision_form' ) ) {
function hcp_mca_v2_provision_form( int $source_id, string $form_key, string $name, array &$notes ): int {
	global $wpdb, $frm_duplicate_ids;

	$existing = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}frm_forms WHERE form_key = %s",
		$form_key
	) );
	if ( $existing ) {
		return $existing;
	}

	// Formidable only registers its repeater, conditional-logic, rootline and
	// form-action duplication handlers on admin screens. Register them here so
	// a CLI / runner duplicate is complete.
	add_action( 'frm_after_duplicate_form', 'FrmForm::after_duplicate', 10, 2 );
	add_filter( 'frm_duplicated_field', 'FrmProField::duplicate', 10, 2 );
	add_filter( 'frm_create_repeat_form', 'FrmProField::create_repeat_form', 10, 2 );
	add_filter( 'frm_after_duplicate_form_values', 'FrmProFormsController::after_duplicate', 10, 2 );
	add_action( 'frm_after_duplicate_form', 'FrmFormActionsController::duplicate_form_actions', 20, 3 );

	$frm_duplicate_ids = [];
	$new_id = FrmForm::duplicate( $source_id );
	if ( ! $new_id ) {
		throw new \RuntimeException( "FrmForm::duplicate({$source_id}) failed" );
	}

	$wpdb->update(
		$wpdb->prefix . 'frm_forms',
		[ 'form_key' => $form_key, 'name' => $name ],
		[ 'id' => $new_id ]
	);

	// Deterministic field keys: legacy key behind the v2 prefix.
	$old_keys = $wpdb->get_results( $wpdb->prepare(
		"SELECT f.id, f.field_key FROM {$wpdb->prefix}frm_fields f
		 LEFT JOIN {$wpdb->prefix}frm_forms fr ON fr.id = f.form_id
		 WHERE f.form_id = %d OR fr.parent_form_id = %d",
		$source_id, $source_id
	), OBJECT_K );
	$rekeyed = 0;
	foreach ( $frm_duplicate_ids as $old => $new ) {
		if ( ! is_int( $old ) || empty( $old_keys[ $old ] ) ) {
			continue;
		}
		$wpdb->update(
			$wpdb->prefix . 'frm_fields',
			[ 'field_key' => HCP_MCA_V2_FIELD_KEY_PREFIX . $old_keys[ $old ]->field_key ],
			[ 'id' => (int) $new ]
		);
		$rekeyed++;
	}

	// Child (repeater) forms get a matching name and a deterministic key.
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}frm_forms WHERE parent_form_id = %d ORDER BY id", $new_id ) ) as $i => $child_id ) {
		$wpdb->update(
			$wpdb->prefix . 'frm_forms',
			[
				'name'     => $name . ' repeater' . ( $i ? ' ' . ( $i + 1 ) : '' ),
				'form_key' => $form_key . '-repeater' . ( $i ? '-' . ( $i + 1 ) : '' ),
			],
			[ 'id' => (int) $child_id ]
		);
	}
	$rekeyed += hcp_mca_v2_rekey_child_fields( $source_id, (int) $new_id );

	// Email actions: draft. The plugin sends the review-ready email itself.
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->posts} SET post_status = 'draft'
		 WHERE post_type = 'frm_form_actions' AND post_excerpt = 'email' AND menu_order = %d",
		$new_id
	) );

	FrmForm::clear_form_cache();
	if ( class_exists( 'FrmField' ) ) {
		FrmField::delete_form_transient( $new_id );
	}

	$notes[] = "form {$source_id} -> {$new_id} ({$form_key}, {$rekeyed} fields rekeyed)";

	return (int) $new_id;
}
}

/**
 * String-replace inside a form's stored options and its confirmation actions.
 */
if ( ! function_exists( 'hcp_mca_v2_retarget_form_urls' ) ) {
function hcp_mca_v2_retarget_form_urls( int $form_id, array $map, array &$notes ): void {
	global $wpdb;

	$options = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT options FROM {$wpdb->prefix}frm_forms WHERE id = %d", $form_id ) ) );
	$updated = false;
	foreach ( [ 'success_msg', 'edit_msg', 'success_url' ] as $key ) {
		if ( empty( $options[ $key ] ) ) {
			continue;
		}
		$new = strtr( $options[ $key ], $map );
		if ( $new !== $options[ $key ] ) {
			$options[ $key ] = $new;
			$updated         = true;
		}
	}
	if ( $updated ) {
		$wpdb->update( $wpdb->prefix . 'frm_forms', [ 'options' => maybe_serialize( $options ) ], [ 'id' => $form_id ] );
		FrmForm::clear_form_cache();
	}

	$actions = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'frm_form_actions' AND post_excerpt = 'on_submit' AND menu_order = %d",
		$form_id
	) );
	foreach ( $actions as $action ) {
		$new = strtr( $action->post_content, array_combine(
			array_map( fn( $k ) => str_replace( '/', '\\/', $k ), array_keys( $map ) ),
			array_map( fn( $v ) => str_replace( '/', '\\/', $v ), $map )
		) + $map );
		if ( $new !== $action->post_content ) {
			$wpdb->update( $wpdb->posts, [ 'post_content' => $new ], [ 'ID' => (int) $action->ID ] );
			clean_post_cache( (int) $action->ID );
			$updated = true;
		}
	}

	if ( $updated ) {
		$notes[] = "form {$form_id} urls retargeted";
	}
}
}

/**
 * Fields inside repeater child forms are created through the Pro repeat-form
 * path and miss the duplicate-id map, so key them by position against the
 * source child form.
 */
if ( ! function_exists( 'hcp_mca_v2_rekey_child_fields' ) ) {
function hcp_mca_v2_rekey_child_fields( int $source_form_id, int $new_form_id ): int {
	global $wpdb;

	$source_children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}frm_forms WHERE parent_form_id = %d ORDER BY id", $source_form_id ) );
	$new_children    = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}frm_forms WHERE parent_form_id = %d ORDER BY id", $new_form_id ) );
	$n               = 0;

	foreach ( $new_children as $i => $child_id ) {
		if ( ! isset( $source_children[ $i ] ) ) {
			break;
		}
		$source_fields = $wpdb->get_results( $wpdb->prepare( "SELECT id, field_key, type, name FROM {$wpdb->prefix}frm_fields WHERE form_id = %d ORDER BY field_order, id", $source_children[ $i ] ) );
		$new_fields    = $wpdb->get_results( $wpdb->prepare( "SELECT id, field_key, type, name FROM {$wpdb->prefix}frm_fields WHERE form_id = %d ORDER BY field_order, id", $child_id ) );
		if ( count( $source_fields ) !== count( $new_fields ) ) {
			throw new \RuntimeException( "child form {$child_id}: field count differs from source {$source_children[ $i ]}" );
		}
		foreach ( $new_fields as $j => $field ) {
			$source = $source_fields[ $j ];
			if ( $source->type !== $field->type || $source->name !== $field->name ) {
				throw new \RuntimeException( "child form {$child_id}: field {$field->id} does not match source {$source->id}" );
			}
			$wanted = HCP_MCA_V2_FIELD_KEY_PREFIX . $source->field_key;
			if ( $field->field_key !== $wanted ) {
				$wpdb->update( $wpdb->prefix . 'frm_fields', [ 'field_key' => $wanted ], [ 'id' => (int) $field->id ] );
				$n++;
			}
		}
	}

	return $n;
}
}

/**
 * Copy a post (and selected meta) under a fixed slug. Returns the existing
 * post id when the slug is already taken by that post type.
 */
if ( ! function_exists( 'hcp_mca_v2_copy_post' ) ) {
function hcp_mca_v2_copy_post( int $source_id, string $slug, array $overrides, array $copy_meta, array &$notes, array $set_meta = [] ): int {
	$source = get_post( $source_id );
	if ( ! $source ) {
		throw new \RuntimeException( "source post {$source_id} not found" );
	}

	$existing = get_page_by_path( $slug, OBJECT, $source->post_type );
	if ( $existing instanceof WP_Post ) {
		return (int) $existing->ID;
	}

	kses_remove_filters();
	$new_id = wp_insert_post( array_merge( [
		'post_type'    => $source->post_type,
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $source->post_title,
		'post_content' => $source->post_content,
		'post_excerpt' => $source->post_excerpt,
		'post_author'  => $source->post_author,
		'menu_order'   => $source->menu_order,
	], $overrides ), true );
	kses_init_filters();

	if ( is_wp_error( $new_id ) ) {
		throw new \RuntimeException( "copy of {$source_id}: " . $new_id->get_error_message() );
	}

	foreach ( $copy_meta as $key ) {
		$value = get_post_meta( $source_id, $key, true );
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $new_id, $key, $value );
		}
	}
	foreach ( $set_meta as $key => $value ) {
		update_post_meta( $new_id, $key, $value );
	}

	$notes[] = "{$source->post_type} {$source_id} -> {$new_id} ({$slug})";

	return (int) $new_id;
}
}

/**
 * The evaluation quiz: copy the ProQuiz master row, then the sfwd-quiz post
 * with its settings retargeted to the new course, ProQuiz row and form.
 */
if ( ! function_exists( 'hcp_mca_v2_provision_quiz' ) ) {
function hcp_mca_v2_provision_quiz( int $course_id, int $eval_form_id, array &$notes ): int {
	global $wpdb;

	$existing = get_page_by_path( HCP_MCA_V2_QUIZ_SLUG, OBJECT, 'sfwd-quiz' );
	if ( $existing instanceof WP_Post ) {
		return (int) $existing->ID;
	}

	$legacy_pro_id = (int) get_post_meta( HCP_MCA_QUIZ_ID, 'quiz_pro_id', true );
	$table         = $wpdb->prefix . 'learndash_pro_quiz_master';
	$columns       = array_diff( $wpdb->get_col( "DESCRIBE {$table}", 0 ), [ 'id' ] );
	$column_list   = implode( ', ', array_map( fn( $c ) => "`{$c}`", $columns ) );
	$inserted      = $wpdb->query( $wpdb->prepare(
		"INSERT INTO {$table} ({$column_list}) SELECT {$column_list} FROM {$table} WHERE id = %d",
		$legacy_pro_id
	) );
	if ( ! $inserted ) {
		throw new \RuntimeException( "could not copy ProQuiz row {$legacy_pro_id}" );
	}
	$pro_id = (int) $wpdb->insert_id;
	$wpdb->update( $table, [ 'name' => 'Clinical Audit activity evaluation (2026)' ], [ 'id' => $pro_id ] );

	$quiz_settings = get_post_meta( HCP_MCA_QUIZ_ID, '_sfwd-quiz', true );
	$quiz_settings['sfwd-quiz_course']   = $course_id;
	$quiz_settings['sfwd-quiz_quiz_pro'] = $pro_id;

	$quiz_id = hcp_mca_v2_copy_post(
		HCP_MCA_QUIZ_ID,
		HCP_MCA_V2_QUIZ_SLUG,
		[ 'post_content' => '[formidable id=' . $eval_form_id . ']' ],
		[ '_timeLimitCookie', '_viewProfileStatistics', 'ld_quiz_questions', '_ld_certificate', '_ld_certificate_threshold' ],
		$notes,
		[
			'_sfwd-quiz'                 => $quiz_settings,
			'quiz_pro_id'                => $pro_id,
			'quiz_pro_id_' . $pro_id     => $pro_id,
			'quiz_pro_primary_' . $pro_id => $pro_id,
			'course_id'                  => $course_id,
			'ld_course_' . $course_id    => $course_id,
		]
	);

	$notes[] = "proquiz {$legacy_pro_id} -> {$pro_id}";

	return $quiz_id;
}
}

/**
 * Completion email for the new course (hours + certificate link updated) and
 * the new course added to the six-week idle reminder.
 */
if ( ! function_exists( 'hcp_mca_v2_provision_notifications' ) ) {
function hcp_mca_v2_provision_notifications( int $course_id, array &$notes ): void {
	$legacy_complete = 112561;
	$idle_reminder   = 112673;
	$slug            = 'clinical-audit-2026-completion-email';

	if ( ! get_page_by_path( $slug, OBJECT, 'ld-notification' ) ) {
		$source  = get_post( $legacy_complete );
		$content = str_replace(
			[
				'course=' . HCP_MCA_COURSE_ID,
				'You have earned 6.5 hours of RACGP Measuring Outcomes plus 1.0 hour of RACGP Reviewing Performance',
			],
			[
				'course=' . $course_id,
				'You have earned 3.0 hours of RACGP Measuring Outcomes plus 2.0 hours of RACGP Reviewing Performance',
			],
			$source->post_content
		);

		kses_remove_filters();
		$new_id = wp_insert_post( [
			'post_type'    => 'ld-notification',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => 'Congratulations! Anal Fissure Management Clinical Audit (2026) complete',
			'post_content' => $content,
			'post_author'  => $source->post_author,
		], true );
		kses_init_filters();
		if ( is_wp_error( $new_id ) ) {
			throw new \RuntimeException( 'completion notification: ' . $new_id->get_error_message() );
		}

		foreach ( get_post_meta( $legacy_complete ) as $key => $values ) {
			if ( in_array( $key, [ '_edit_lock', '_edit_last' ], true ) ) {
				continue;
			}
			$value = maybe_unserialize( $values[0] );
			if ( '_ld_notifications_course_id' === $key ) {
				$value = [ (string) $course_id ];
			}
			update_post_meta( $new_id, $key, $value );
		}
		$notes[] = "completion notification {$new_id}";
	}

	$courses = (array) get_post_meta( $idle_reminder, '_ld_notifications_course_id', true );
	if ( ! in_array( (string) $course_id, array_map( 'strval', $courses ), true ) ) {
		$courses[] = (string) $course_id;
		update_post_meta( $idle_reminder, '_ld_notifications_course_id', array_values( $courses ) );
		$notes[] = 'idle reminder updated';
	}
}
}

return [
	'description' => 'CAPH0150: provision the 2026 Clinical Audit variant (forms, course, lesson, quiz, cert, notifications, chooser page).',

	'up' => function (): string {
		global $wpdb;
		$notes = [];

		// ------------------------------------------------------------------
		// Forms.
		// ------------------------------------------------------------------
		$audit_form_id = hcp_mca_v2_provision_form(
			HCP_MCA_AUDIT_FORM_ID,
			HCP_MCA_V2_AUDIT_FORM_KEY,
			'Clinical Audit Form (2026)',
			$notes
		);
		$eval_form_id  = hcp_mca_v2_provision_form(
			HCP_MCA_EVAL_FORM_ID,
			HCP_MCA_V2_EVAL_FORM_KEY,
			'Activity evaluation (2026)',
			$notes
		);

		// ------------------------------------------------------------------
		// Certificate.
		// ------------------------------------------------------------------
		$cert_id = hcp_mca_v2_copy_post(
			HCP_MCA_CERT_ID,
			HCP_MCA_V2_CERT_SLUG,
			[ 'post_title' => 'Clinical Audit Module (2026)' ],
			[ '_thumbnail_id', 'learndash_certificate_options' ],
			$notes
		);

		// ------------------------------------------------------------------
		// Course.
		// ------------------------------------------------------------------
		$course_settings = get_post_meta( HCP_MCA_COURSE_ID, '_sfwd-courses', true );
		$course_settings['sfwd-courses_certificate'] = $cert_id;

		$course_id = hcp_mca_v2_copy_post(
			HCP_MCA_COURSE_ID,
			HCP_MCA_V2_COURSE_SLUG,
			[ 'post_title' => 'Clinical Audit: Anal Fissure Management' ],
			[
				'_learndash_course_grid_short_description',
				'_learndash_course_grid_duration',
				'_learndash_course_grid_enable_video_preview',
				'_learndash_course_grid_video_embed_code',
				'_learndash_course_grid_custom_button_text',
				'_learndash_course_grid_custom_ribbon_text',
				'_ld_price_type',
				'course_points',
				'learndash_group_enrolled_124531',
			],
			$notes,
			[
				'_sfwd-courses'   => $course_settings,
				'_ld_certificate' => $cert_id,
			]
		);

		// ------------------------------------------------------------------
		// Lesson (embeds the 2026 audit form).
		// ------------------------------------------------------------------
		$lesson_settings = get_post_meta( HCP_MCA_LESSON_ID, '_sfwd-lessons', true );
		$lesson_settings['sfwd-lessons_course'] = $course_id;

		$lesson_content = str_replace(
			'[formidable id=' . HCP_MCA_AUDIT_FORM_ID . ']',
			'[formidable id=' . $audit_form_id . ']',
			get_post( HCP_MCA_LESSON_ID )->post_content
		);

		$lesson_id = hcp_mca_v2_copy_post(
			HCP_MCA_LESSON_ID,
			HCP_MCA_V2_LESSON_SLUG,
			[ 'post_title' => 'Complete Clinical Audit', 'post_content' => $lesson_content ],
			[],
			$notes,
			[
				'_sfwd-lessons'          => $lesson_settings,
				'course_id'              => $course_id,
				'ld_course_' . $course_id => $course_id,
			]
		);

		// ------------------------------------------------------------------
		// Quiz (a ProQuiz shell embedding the 2026 evaluation form).
		// ------------------------------------------------------------------
		$quiz_id = hcp_mca_v2_provision_quiz( $course_id, $eval_form_id, $notes );

		// ------------------------------------------------------------------
		// Success messages on the copied forms still point at the legacy
		// course. Retarget them (the theme overrides the audit one at render
		// time, but the stored copy should not lie).
		// ------------------------------------------------------------------
		hcp_mca_v2_retarget_form_urls( $audit_form_id, [
			'/courses/mini-clinical-audit/quizzes/activity-evaluation/' => wp_make_link_relative( get_permalink( $quiz_id ) ),
			'#post-' . HCP_MCA_LESSON_ID                                 => '#post-' . $lesson_id,
		], $notes );

		// ------------------------------------------------------------------
		// Course steps.
		// ------------------------------------------------------------------
		if ( learndash_get_course_steps( $course_id ) !== [ $lesson_id ] ) {
			LDLMS_Factory_Post::course_steps( $course_id )->set_steps( [
				'h' => [
					'sfwd-lessons' => [ $lesson_id => [ 'sfwd-topic' => [], 'sfwd-quiz' => [] ] ],
					'sfwd-quiz'    => [ $quiz_id => [] ],
				],
			] );
			$notes[] = 'course steps set';
		}

		// ------------------------------------------------------------------
		// Notifications: completion email copy + add course to idle reminder.
		// ------------------------------------------------------------------
		hcp_mca_v2_provision_notifications( $course_id, $notes );

		// ------------------------------------------------------------------
		// Chooser page.
		// ------------------------------------------------------------------
		if ( ! get_page_by_path( HCP_MCA_CHOOSER_PAGE_SLUG, OBJECT, 'page' ) ) {
			kses_remove_filters();
			$page_id = wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => HCP_MCA_CHOOSER_PAGE_SLUG,
				'post_title'   => 'Clinical Audit',
				'post_content' => '<div class="section" data-pb-label="Section" style="padding-top:5rem;padding-bottom:6rem;">'
					. '<div class="container text-center" data-pb-label="Container" style="margin-bottom:3rem;">'
					. '<h1 class=" ">Clinical Audit: Anal Fissure Management</h1>'
					. '<p class=" ">The clinical audit has been updated. Choose which version you would like to work on below.</p>'
					. '</div>'
					. '[hcp_mca_audit_chooser]'
					. '</div>',
			], true );
			kses_init_filters();
			if ( is_wp_error( $page_id ) ) {
				throw new \RuntimeException( 'chooser page: ' . $page_id->get_error_message() );
			}
			$notes[] = "chooser page {$page_id}";
		}

		hcp_mca_variants( true );

		return $notes ? implode( '; ', $notes ) : 'already provisioned';
	},
];

<?php
/**
 * Audit variants — the legacy Mini Clinical Audit and the 2026 rebuild run
 * side by side. Each variant is one LearnDash course (lesson + quiz), one
 * audit form, one evaluation form and one certificate. Everything in this
 * plugin and the theme that used to be keyed to the single course now asks
 * this registry instead.
 *
 * The legacy variant is fixed by post/form ID. Later variants are created by
 * a migration, so their IDs differ per environment and are resolved by slug
 * and form key at runtime. A variant whose course does not exist yet is
 * simply absent from hcp_mca_variants().
 */

defined( 'ABSPATH' ) || exit;

const HCP_MCA_VARIANT_LEGACY = 'legacy';
const HCP_MCA_VARIANT_V2     = 'v2';

/**
 * Static definitions. `ids` are fixed, `lookup` entries are resolved at runtime.
 */
function hcp_mca_variant_definitions(): array {
	return [
		HCP_MCA_VARIANT_LEGACY => [
			'key'          => HCP_MCA_VARIANT_LEGACY,
			'label'        => 'Mini Clinical Audit (original version)',
			'short_label'  => 'Mini Clinical Audit',
			'activity_id'  => '1460044',
			'hours_mo'     => '6.5',
			'hours_rp'     => '1.0',
			// Legacy artwork carries its own activity ID and hours; no overlay.
			'cert_template' => null,
			'ids'          => [
				'course'       => HCP_MCA_COURSE_ID,
				'lesson'       => HCP_MCA_LESSON_ID,
				'quiz'         => HCP_MCA_QUIZ_ID,
				'cert'         => HCP_MCA_CERT_ID,
				'audit_form'   => HCP_MCA_AUDIT_FORM_ID,
				'eval_form'    => HCP_MCA_EVAL_FORM_ID,
				'banner_field' => HCP_MCA_AUDIT_BANNER_FIELD_ID,
			],
		],
		HCP_MCA_VARIANT_V2     => [
			'key'          => HCP_MCA_VARIANT_V2,
			'label'        => 'Clinical Audit: Anal Fissure Management (2026 version)',
			'short_label'  => 'Clinical Audit 2026',
			// Placeholder until RACGP accredits the 2026 audit; this is the only place to change it.
			'activity_id'  => 'XXX',
			'hours_mo'     => '3.0',
			'hours_rp'     => '2.0',
			// Blank artwork: activity ID and hours are drawn at render time (see certificate.php).
			'cert_template' => 'assets/img/certificate-2026-background.jpg',
			'lookup'       => [
				'course'       => [ 'post', 'sfwd-courses', HCP_MCA_V2_COURSE_SLUG ],
				'lesson'       => [ 'post', 'sfwd-lessons', HCP_MCA_V2_LESSON_SLUG ],
				'quiz'         => [ 'post', 'sfwd-quiz', HCP_MCA_V2_QUIZ_SLUG ],
				'cert'         => [ 'post', 'sfwd-certificates', HCP_MCA_V2_CERT_SLUG ],
				'audit_form'   => [ 'form', HCP_MCA_V2_AUDIT_FORM_KEY ],
				'eval_form'    => [ 'form', HCP_MCA_V2_EVAL_FORM_KEY ],
				'banner_field' => [ 'field', HCP_MCA_V2_AUDIT_BANNER_FIELD_KEY ],
			],
		],
	];
}

/**
 * Provisioned variants, keyed by variant key, with every id resolved.
 *
 * @return array<string, array>
 */
function hcp_mca_variants( bool $reload = false ): array {
	static $resolved = null;
	if ( null !== $resolved && ! $reload ) {
		return $resolved;
	}

	$resolved = [];
	foreach ( hcp_mca_variant_definitions() as $key => $defn ) {
		$ids = $defn['ids'] ?? [];
		foreach ( $defn['lookup'] ?? [] as $name => $spec ) {
			$ids[ $name ] = hcp_mca_variant_resolve_id( $spec );
		}
		if ( empty( $ids['course'] ) ) {
			continue;
		}
		unset( $defn['lookup'], $defn['ids'] );
		$defn['hours']    = hcp_mca_hours_sentence( $defn );
		$resolved[ $key ] = $defn + $ids;
	}

	return $resolved;
}

/**
 * "3.0 hours Measuring Outcomes + 2.0 hours Reviewing Performance".
 */
function hcp_mca_hours_sentence( array $variant ): string {
	$unit = fn( string $n ) => ( (float) $n === 1.0 ) ? 'hour' : 'hours';
	return sprintf(
		'%s %s Measuring Outcomes + %s %s Reviewing Performance',
		$variant['hours_mo'], $unit( $variant['hours_mo'] ),
		$variant['hours_rp'], $unit( $variant['hours_rp'] )
	);
}

function hcp_mca_variant_resolve_id( array $spec ): int {
	global $wpdb;

	switch ( $spec[0] ) {
		case 'post':
			$post = get_page_by_path( $spec[2], OBJECT, $spec[1] );
			return $post instanceof WP_Post ? (int) $post->ID : 0;
		case 'form':
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}frm_forms WHERE form_key = %s LIMIT 1",
				$spec[1]
			) );
		case 'field':
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT f.id FROM {$wpdb->prefix}frm_fields f
				 JOIN {$wpdb->prefix}frm_forms fr ON fr.id = f.form_id
				 WHERE f.field_key = %s LIMIT 1",
				$spec[1]
			) );
	}

	return 0;
}

/**
 * @param array|string $variant Variant array or key.
 */
function hcp_mca_variant( $variant ): ?array {
	if ( is_array( $variant ) ) {
		return $variant;
	}
	return hcp_mca_variants()[ (string) $variant ] ?? null;
}

function hcp_mca_variant_by( string $field, int $id ): ?array {
	if ( $id <= 0 ) {
		return null;
	}
	foreach ( hcp_mca_variants() as $variant ) {
		if ( (int) ( $variant[ $field ] ?? 0 ) === $id ) {
			return $variant;
		}
	}
	return null;
}

function hcp_mca_variant_for_course( int $course_id ): ?array {
	return hcp_mca_variant_by( 'course', $course_id );
}

function hcp_mca_variant_for_lesson( int $lesson_id ): ?array {
	return hcp_mca_variant_by( 'lesson', $lesson_id );
}

function hcp_mca_variant_for_audit_form( int $form_id ): ?array {
	return hcp_mca_variant_by( 'audit_form', $form_id );
}

function hcp_mca_variant_for_eval_form( int $form_id ): ?array {
	return hcp_mca_variant_by( 'eval_form', $form_id );
}

/**
 * Variant owning either of its two forms.
 */
function hcp_mca_variant_for_form( int $form_id ): ?array {
	return hcp_mca_variant_for_audit_form( $form_id ) ?? hcp_mca_variant_for_eval_form( $form_id );
}

/**
 * @return int[] Every id of the given kind across provisioned variants.
 */
function hcp_mca_variant_ids( string $field ): array {
	$ids = [];
	foreach ( hcp_mca_variants() as $variant ) {
		if ( ! empty( $variant[ $field ] ) ) {
			$ids[] = (int) $variant[ $field ];
		}
	}
	return $ids;
}

/**
 * User-meta key scoped to a variant. Legacy keeps its historical unprefixed
 * keys so existing approvals stay valid.
 */
function hcp_mca_user_meta_key( array $variant, string $suffix ): string {
	if ( HCP_MCA_VARIANT_LEGACY === $variant['key'] ) {
		return 'hcp_mca_' . $suffix;
	}
	return 'hcp_mca_' . $variant['key'] . '_' . $suffix;
}

/**
 * Whether the user has touched this variant at all: a draft or submission on
 * either form, or any LearnDash activity on its course. Drives the chooser.
 */
function hcp_mca_user_has_variant_progress( int $user_id, array $variant ): bool {
	global $wpdb;

	if ( $user_id <= 0 ) {
		return false;
	}

	$entries = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}frm_items
		 WHERE user_id = %d AND form_id IN (%d, %d)",
		$user_id, (int) $variant['audit_form'], (int) $variant['eval_form']
	) );
	if ( $entries > 0 ) {
		return true;
	}

	// 'access' rows are written just by opening the course page; they are not progress.
	$activity = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}learndash_user_activity
		 WHERE user_id = %d AND course_id = %d AND activity_type <> 'access'",
		$user_id, (int) $variant['course']
	) );

	return $activity > 0;
}

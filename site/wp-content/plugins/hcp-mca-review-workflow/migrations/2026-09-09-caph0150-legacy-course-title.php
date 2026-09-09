<?php
/**
 * CAPH0150: the original audit course is titled "Mini Clinical Audit (legacy)"
 * so admin screens and breadcrumbs tell the two audits apart. Slug and IDs
 * are untouched. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: legacy audit course titled "Mini Clinical Audit (legacy)".',

	'up' => function (): string {
		$course = get_post( HCP_MCA_COURSE_ID );
		if ( ! $course ) {
			throw new \RuntimeException( 'legacy course not found' );
		}
		$title = 'Mini Clinical Audit (legacy)';
		if ( $course->post_title === $title ) {
			return 'already titled';
		}
		if ( 'Mini Clinical Audit' !== $course->post_title ) {
			throw new \RuntimeException( 'unexpected legacy course title: ' . $course->post_title );
		}
		wp_update_post( [ 'ID' => $course->ID, 'post_title' => $title ] );
		return 'legacy course retitled';
	},
];

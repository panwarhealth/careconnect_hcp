<?php
/**
 * Set the sick-day care-plan resource title to "Diabetes Sick Day Care Plan"
 * (drops the "Hydralyte" branding for the Clinical Bites context).
 *
 * Resource resolved by slug (stable per env). Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Set the sick-day care-plan resource title to "Diabetes Sick Day Care Plan".',
	'up'          => function () {
		$r = get_page_by_path( 'hydarlyte-sick-days-care-plan', OBJECT, 'resources' );
		if ( ! $r ) {
			return 'resource not found';
		}
		$target = 'Diabetes Sick Day Care Plan';
		if ( $r->post_title === $target ) {
			return 'already set';
		}
		wp_update_post( array( 'ID' => $r->ID, 'post_title' => $target ) );
		return 'retitled to: ' . $target;
	},
);

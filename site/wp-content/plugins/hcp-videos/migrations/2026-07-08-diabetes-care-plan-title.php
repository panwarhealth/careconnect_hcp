<?php
/**
 * Prepend "Diabetes" to the Hydralyte Sick Days Care Plan resource title, so it
 * reads "Diabetes Hydralyte Sick Days CARE PLAN" in the Clinical Bites context.
 *
 * Resource resolved by slug (stable per env). Idempotent: skips if the title
 * already starts with "Diabetes".
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Prepend "Diabetes" to the Hydralyte Sick Days Care Plan resource title.',
	'up'          => function () {
		$r = get_page_by_path( 'hydarlyte-sick-days-care-plan', OBJECT, 'resources' );
		if ( ! $r ) {
			return 'resource not found';
		}
		if ( strpos( $r->post_title, 'Diabetes' ) === 0 ) {
			return 'already prefixed';
		}
		$new = 'Diabetes ' . $r->post_title;
		wp_update_post( array( 'ID' => $r->ID, 'post_title' => $new ) );
		return 'retitled to: ' . $new;
	},
);

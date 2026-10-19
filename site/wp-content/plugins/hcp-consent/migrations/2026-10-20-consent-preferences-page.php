<?php
/**
 * Create /consent-preferences/, the page the Terms update email links to.
 * Rendered on the plugin's bare template. Skips if the page already exists.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Create the /consent-preferences/ page.',
	'up'          => function () {
		$existing = get_page_by_path( HCP_CONSENT_LANDING_SLUG, OBJECT, 'page' );
		if ( $existing ) {
			return 'Already exists (page ' . $existing->ID . ').';
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Communication preferences',
				'post_name'    => HCP_CONSENT_LANDING_SLUG,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[hcp_consent_landing]',
				'post_author'  => 1,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() );
		}

		return 'Created page ' . $post_id . '.';
	},
);

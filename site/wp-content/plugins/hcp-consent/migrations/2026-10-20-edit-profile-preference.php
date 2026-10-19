<?php
/**
 * Add the communication preference to Edit Your Profile, under the RCP
 * profile editor. Skips if it is already there.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Add [hcp_consent_preference] to Edit Your Profile.',
	'up'          => function () {
		$page = get_page_by_path( 'edit-your-profile', OBJECT, 'page' );
		if ( ! $page ) {
			throw new RuntimeException( 'Page /edit-your-profile/ not found.' );
		}
		if ( false !== strpos( $page->post_content, '[hcp_consent_preference]' ) ) {
			return 'Already present.';
		}

		$anchor = '[rcp_profile_editor]';
		if ( false === strpos( $page->post_content, $anchor ) ) {
			throw new RuntimeException( 'Profile editor shortcode not found on the page.' );
		}

		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => str_replace( $anchor, $anchor . ' [hcp_consent_preference]', $page->post_content ),
			)
		);

		return 'Added to page ' . $page->ID . '.';
	},
);

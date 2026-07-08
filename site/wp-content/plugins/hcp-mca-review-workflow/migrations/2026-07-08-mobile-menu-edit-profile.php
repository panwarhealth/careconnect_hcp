<?php
/**
 * Add an "Edit Profile" button to the mobile hamburger menu (logged-in only).
 *
 * On mobile the Edit Profile button was dropped from the top nav bar (it didn't
 * fit); this surfaces it in the hamburger menu instead. Inserted after the nav
 * menu inside the default spinnr_mobile_menu post. Idempotent.
 *
 * (Not video-specific; relocated to the hcp-mca-review-workflow runner so it
 * runs on main/prod independently of the hcp-videos plugin.)
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Add Edit Profile to the mobile hamburger menu (logged-in only).',
	'up'          => function () {
		$menus = get_posts( array(
			'post_type'      => 'spinnr_mobile_menu',
			'name'           => 'default_spinnr_mobile_menu',
			'posts_per_page' => 1,
		) );
		if ( ! $menus ) {
			return 'default mobile menu not found';
		}
		$post = $menus[0];
		if ( strpos( $post->post_content, 'edit-your-profile' ) !== false ) {
			return 'already present';
		}
		$anchor = '[wp-menus display="div" menu="258" link_class=""]</div>';
		if ( strpos( $post->post_content, $anchor ) === false ) {
			return 'menu anchor not found';
		}
		$button = $anchor . "\n" .
			'<div class="mt-4xl flex flex-col gap-md">[user_status_content show_to="logged_in"]' .
			'<a class="btn ghost m-0" href="/edit-your-profile">Edit Profile</a>' .
			'[/user_status_content]</div>';
		wp_update_post( array(
			'ID'           => $post->ID,
			'post_content' => str_replace( $anchor, $button, $post->post_content ),
		) );
		return 'added Edit Profile to mobile menu (post ' . $post->ID . ').';
	},
);

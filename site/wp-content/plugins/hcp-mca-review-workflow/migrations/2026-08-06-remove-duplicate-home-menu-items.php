<?php
/**
 * Removes both HOME items from the Main Menu.
 *
 * The two items were gated on RCP membership level: one shown to HCP or Staff
 * (-> /welcome/), the other to Pharmacist or Staff (-> /welcome-pharmacist/).
 * Staff appeared in both lists so staff accounts saw HOME twice, while
 * Practitioner accounts and anyone without a membership saw neither.
 *
 * Both are retired in favour of the logo, which links to the real front page.
 * The welcome pages themselves are left in place, unlinked, pending the site
 * revamp.
 *
 * Set to draft rather than deleted so the items can be restored by flipping
 * post_status back to publish.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Retire both membership-gated HOME menu items from the Main Menu',
	'up'          => function (): void {
		global $wpdb;

		// post ID => the page it points at, asserted before changing anything.
		$items = [
			83628 => 12,    // HOME -> Welcome
			83817 => 3062,  // HOME -> Welcome - Pharmacist
		];

		foreach ( $items as $item_id => $expected_target ) {
			$post = get_post( $item_id );

			if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
				continue;
			}

			if ( 'publish' !== $post->post_status ) {
				continue;
			}

			$target = (int) get_post_meta( $item_id, '_menu_item_object_id', true );

			if ( $target !== $expected_target ) {
				continue;
			}

			$wpdb->update(
				$wpdb->posts,
				[ 'post_status' => 'draft' ],
				[ 'ID' => $item_id ],
				[ '%s' ],
				[ '%d' ]
			);

			clean_post_cache( $item_id );
		}

		wp_cache_delete( 'last_changed', 'posts' );
	},
];

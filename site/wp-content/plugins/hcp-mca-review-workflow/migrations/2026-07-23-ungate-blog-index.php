<?php
/**
 * Make the /blog/ index page publicly viewable so crawlers can reach the
 * post cards. Individual gated posts keep their own RCP restrictions and
 * still redirect anonymous visitors when clicked.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Ungate the /blog/ index page (remove RCP restriction metas) so the post list is publicly crawlable.',
	'up'          => function (): string {
		$page = get_page_by_path( 'blog' );
		if ( ! $page ) {
			throw new \RuntimeException( 'No page with slug "blog" found.' );
		}

		$removed = [];
		foreach ( [ 'rcp_user_level', 'rcp_subscription_level', 'rcp_access_level', '_is_paid' ] as $key ) {
			if ( '' !== (string) get_post_meta( $page->ID, $key, true ) ) {
				delete_post_meta( $page->ID, $key );
				$removed[] = $key;
			}
		}

		return $removed
			? sprintf( 'Blog page %d ungated (removed: %s).', $page->ID, implode( ', ', $removed ) )
			: sprintf( 'Blog page %d already ungated, nothing to remove.', $page->ID );
	},
];

<?php
/**
 * Ungate the /resources/ index page (same treatment as /blog/): the [resources]
 * shortcode already hides member-restricted items per-item (all prescription
 * material is restricted), so the public page shows only OTC resource cards.
 * hcp-seo flips it to index,follow and into the sitemap automatically.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'SEO: ungate the /resources/ index page (per-item restriction already protects gated cards).',
	'up'          => function (): string {
		$page = get_page_by_path( 'resources' );
		if ( ! $page ) {
			throw new \RuntimeException( 'resources page not found.' );
		}

		$log = [];
		foreach ( [ 'rcp_user_level', 'rcp_subscription_level', 'rcp_access_level', '_is_paid' ] as $meta ) {
			if ( metadata_exists( 'post', $page->ID, $meta ) ) {
				delete_post_meta( $page->ID, $meta );
				$log[] = $meta;
			}
		}

		// The "3D interactive rectum model" resource is unrestricted but links
		// to the gated /rectogesic-3d-model/ page — the URL alone puts the
		// prescription brand into public HTML. Restrict the card so it only
		// renders for logged-in users.
		$model = get_posts( [ 'post_type' => 'resources', 'name' => '3d-interactive-rectum-model', 'numberposts' => 1, 'fields' => 'ids' ] );
		if ( ! $model ) {
			$model = get_posts( [ 'post_type' => 'resources', 's' => '3D interactive rectum model', 'numberposts' => 1, 'fields' => 'ids' ] );
		}
		if ( $model && ! get_post_meta( $model[0], 'rcp_user_level', true ) ) {
			update_post_meta( $model[0], 'rcp_user_level', [ 'all' ] );
			$log[] = "restricted resource {$model[0]} (3D model card)";
		}

		if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		return "Page {$page->ID}: removed " . ( $log ? implode( ', ', $log ) : 'nothing (already ungated)' );
	},
];

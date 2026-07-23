<?php
/**
 * The header nav (menu 258 via [wp-menus] in the spinnr_header chrome) has two
 * items whose URL is literally "#": Resources and Tools and Videos. Dead links
 * for every visitor, and they hide public indexed pages from crawlers and
 * logged-out users. Point them at their real destinations.
 *
 * /resources/ is RCP-gated: logged-out clicks land on /register/, which is the
 * intended funnel. /tools-and-videos/ is public.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Header nav: replace "#" URLs on Resources + Tools and Videos with real destinations.',
	'up'          => function (): string {
		$targets = [
			'Tools and Videos' => home_url( '/tools-and-videos/' ),
			'Resources'        => home_url( '/resources/' ),
		];

		$log   = [];
		$items = wp_get_nav_menu_items( 258 );
		if ( false === $items ) {
			throw new \RuntimeException( 'Menu 258 not found.' );
		}

		foreach ( $items as $item ) {
			if ( ! isset( $targets[ $item->title ] ) ) {
				continue;
			}
			if ( '#' !== $item->url ) {
				$log[] = "{$item->title}: already {$item->url}";
				continue;
			}
			update_post_meta( $item->ID, '_menu_item_url', $targets[ $item->title ] );
			$log[] = "{$item->title}: # -> " . $targets[ $item->title ];
		}

		if ( ! $log ) {
			$log[] = 'no matching items in menu 258 (mockup-polluted local menu is expected to skip)';
		}

		wp_cache_flush();
		return implode( '; ', $log );
	},
];

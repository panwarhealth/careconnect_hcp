<?php
/**
 * Removes out-of-date resources from the Resources page (Jodi's resources review, Nov 2026).
 * Each card is binned, so it disappears from every therapy area it was tagged to.
 * PDFs and thumbnails are untouched: the 2025 NAC chart PDF is still linked from
 * /allergic-rhinitis-chart/ and two articles.
 *
 * Idempotent. Rollback (both 2026-11-18 resources migrations): prod
 * ~/deploy-backups/resources-cleanup-2026-11-18/resources-rollback.sql, applied with mysql.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Bin 11 out-of-date resources (Lacto-Free, old bowel prep, Rectogesic, Murine, Hydralyte training, FESS/NAC)',
	'up'          => function (): string {
		$remove = [
			432   => 'lacto-free-rec-pad',
			170   => 'bowel-preparation',
			204   => 'patient-recommendation-sheet-4',
			12806 => 'pharmacy-murine-detailer',
			180   => 'factsheet',
			179   => 'murine-dry-eyes',
			173   => 'training-guide',
			25654 => 'allergic-rhinitis',
			167   => 'national-asthma-council-allergic-rhinitis-treatment-chart',
			166   => 'national-asthma-council-treatment-planner',
			165   => 'fess-recommendation',
		];

		foreach ( $remove as $id => $slug ) {
			$post = get_post( $id );
			if ( $post && 'trash' !== $post->post_status
				&& ( 'resources' !== $post->post_type || $slug !== $post->post_name ) ) {
				throw new RuntimeException( "Post {$id} is not resource '{$slug}'; nothing changed." );
			}
		}

		$binned  = [];
		$skipped = [];
		foreach ( $remove as $id => $slug ) {
			$post = get_post( $id );
			if ( ! $post || 'trash' === $post->post_status ) {
				$skipped[] = $id;
				continue;
			}
			if ( ! wp_trash_post( $id ) ) {
				throw new RuntimeException( "Could not bin resource {$id}." );
			}
			$binned[] = $id;
		}

		// Term counts on this site include binned posts, so a filter whose only cards
		// were binned stays in the sidebar with an empty grid. Untag those cards.
		$untagged = [];
		foreach ( [ 'brand', 'therapy_area', 'audience' ] as $taxonomy ) {
			foreach ( array_keys( $remove ) as $id ) {
				foreach ( wp_get_object_terms( $id, $taxonomy ) as $term ) {
					$live = get_posts( [
						'post_type'      => 'any',
						'post_status'    => [ 'publish', 'draft', 'private', 'future', 'pending' ],
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'terms' => $term->term_id ] ],
					] );
					if ( ! $live ) {
						wp_remove_object_terms( $id, $term->term_id, $taxonomy );
						$untagged[] = "{$term->name} (from {$id})";
					}
				}
			}
		}

		return 'Binned: ' . ( $binned ? implode( ', ', $binned ) : 'none' )
			. '. Already gone: ' . ( $skipped ? implode( ', ', $skipped ) : 'none' )
			. '. Emptied filters untagged: ' . ( $untagged ? implode( ', ', $untagged ) : 'none' ) . '.';
	},
];

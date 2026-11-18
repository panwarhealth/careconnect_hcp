<?php
/**
 * Lists the current Hydralyte bowel prep resources under Digestive Care as well as
 * Rehydration (Jodi's resources review, Nov 2026). Existing therapy areas are kept.
 *
 * Idempotent. Roll back by unticking Digestive Care on the three resources.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Add Digestive Care to the 3 current Hydralyte bowel prep resources',
	'up'          => function (): string {
		$resources = [
			121975 => 'hcp-hydralyte-bowel-prep',
			122039 => 'patient-hydralyte-bowel-prep',
			122023 => 'hydralyte-bowel-prep-leaflet',
		];

		if ( ! get_term_by( 'slug', 'digestive-care', 'therapy_area' ) ) {
			throw new RuntimeException( "Therapy area 'digestive-care' not found; nothing changed." );
		}

		foreach ( $resources as $id => $slug ) {
			$post = get_post( $id );
			if ( ! $post || 'resources' !== $post->post_type || $slug !== $post->post_name ) {
				throw new RuntimeException( "Post {$id} is not resource '{$slug}'; nothing changed." );
			}
		}

		foreach ( array_keys( $resources ) as $id ) {
			$result = wp_set_object_terms( $id, 'digestive-care', 'therapy_area', true );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( "Could not tag resource {$id}: " . $result->get_error_message() );
			}
		}

		return 'Digestive Care added to resources ' . implode( ', ', array_keys( $resources ) ) . '.';
	},
];

<?php
/**
 * Pages restricted by RCP to the subscriber role bounce staff logins (editor,
 * author, contributor) to /register/. Add the staff roles to each page's role
 * allow-list so they can view what HCPs can.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'RCP: allow staff roles on pages restricted to the subscriber role.',
	'up'          => function (): string {
		$pages = [
			41528 => 'rectogesic-3d-model',
			16    => 'our-company',
			203   => 'gp-rectogesic-factsheet',
			204   => 'patient-recommendation-sheet-4',
			25654 => 'allergic-rhinitis',
			12886 => 'hcp-managing-dry-eye-in-general-practice',
			12854 => 'hcp-dry-eye-how-aware-are-you',
			12806 => 'pharmacy-murine-detailer',
			12742 => 'patient-nasal-congestion-during-pregnancy',
			12694 => 'hcp-pregnancy-factsheet',
			1254  => 'hcp-dry-eye-factsheet',
			432   => 'lacto-free-rec-pad',
			166   => 'national-asthma-council-treatment-planner',
			167   => 'national-asthma-council-allergic-rhinitis-treatment-chart',
		];
		$staff = [ 'administrator', 'editor', 'author', 'contributor' ];

		$log = [];
		foreach ( $pages as $id => $slug ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_name !== $slug ) {
				$log[] = "{$id} skipped (not {$slug})";
				continue;
			}

			$roles = array_map( 'strtolower', (array) get_post_meta( $id, 'rcp_user_level', true ) );
			if ( ! in_array( 'subscriber', $roles, true ) ) {
				$log[] = "{$id} skipped (not subscriber-restricted)";
				continue;
			}

			$merged = array_values( array_unique( array_merge( $roles, $staff ) ) );
			if ( $merged === $roles ) {
				$log[] = "{$id} unchanged";
				continue;
			}

			update_post_meta( $id, 'rcp_user_level', $merged );
			$log[] = "{$id} updated";
		}

		return implode( '; ', $log );
	},
];

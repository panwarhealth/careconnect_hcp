<?php
/**
 * Consolidate _racgp_number → racgp_number across the site.
 *
 * Two things to fix:
 *
 * (a) Form 2 ("Registration") has a misconfigured "Register User" action that maps the
 *     RACGP number field to the meta_key "_racgp_number" (leading underscore = hidden
 *     meta in WP). Form 129's equivalent action correctly maps to "racgp_number".
 *     The course-95553 enrolment hook in the child theme only listens for the canonical
 *     key, so Form 2 sign-ups are silently stranded.
 *
 * (b) 112 users in the DB have an _racgp_number row from the bad action. 91 have ONLY
 *     the underscore row (no canonical) — these are the recovery candidates. The other
 *     21 have both; in every observed case the underscore value is empty/stale while
 *     the canonical row holds the real RACGP.
 *
 * What this migration does:
 *
 *   1. Patch Form 2's register action JSON: replace "meta_name":"_racgp_number"
 *      with "meta_name":"racgp_number". Stops new orphans.
 *   2. Delete _racgp_number rows where a canonical row already exists (the 21 dupes).
 *   3. Rename remaining _racgp_number rows to racgp_number (the 91 stranded users).
 *   4. Sweep empty canonical rows (junk from prior partial writes).
 *
 * Uses direct SQL (not update_user_meta) so the existing buggy theme-side enrol hook
 * does NOT fire during the rename. That hook is being removed in a follow-up code
 * change; explicit click-based course enrolment will replace it.
 *
 * Idempotent: re-running finds no _racgp_number rows or matching JSON and exits with
 * zero changes.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Fix Form 2 register action mapping and consolidate _racgp_number → racgp_number across all users.',

	'up' => function (): string {
		global $wpdb;

		$umeta = $wpdb->usermeta;
		$posts = $wpdb->posts;

		// 1. Patch Form 2's "Register User" action JSON.
		// Formidable stores action settings as JSON in wp_posts.post_content.
		// menu_order = form_id for frm_form_actions posts.
		$bad_token  = '"meta_name":"_racgp_number"';
		$good_token = '"meta_name":"racgp_number"';

		$actions_patched = $wpdb->query( $wpdb->prepare(
			"UPDATE {$posts}
			 SET post_content = REPLACE(post_content, %s, %s)
			 WHERE post_type    = 'frm_form_actions'
			   AND post_excerpt = 'register'
			   AND menu_order   = 2
			   AND post_content LIKE %s",
			$bad_token,
			$good_token,
			'%' . $wpdb->esc_like( $bad_token ) . '%'
		) );

		// 2. Delete _racgp_number rows where canonical already exists for the same user.
		$duplicates_deleted = $wpdb->query(
			"DELETE u_under
			 FROM {$umeta} u_under
			 JOIN {$umeta} u_canon
			   ON u_canon.user_id  = u_under.user_id
			  AND u_canon.meta_key = 'racgp_number'
			 WHERE u_under.meta_key = '_racgp_number'"
		);

		// 3. Rename remaining _racgp_number rows to racgp_number.
		$renamed = $wpdb->query(
			"UPDATE {$umeta}
			 SET meta_key = 'racgp_number'
			 WHERE meta_key = '_racgp_number'"
		);

		// 4. Drop any empty canonical rows (junk data from prior partial writes).
		$empties_deleted = $wpdb->query(
			"DELETE FROM {$umeta}
			 WHERE meta_key = 'racgp_number'
			   AND ( meta_value = '' OR meta_value IS NULL )"
		);

		// Bust Formidable's cache so the patched action JSON is re-read on next submit.
		wp_cache_flush();

		// Sanity check final state.
		$remaining_under = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$umeta} WHERE meta_key = '_racgp_number'"
		);
		$remaining_canon = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$umeta} WHERE meta_key = 'racgp_number'"
		);
		$bad_actions_left = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$posts}
			 WHERE post_type = 'frm_form_actions'
			   AND post_content LIKE %s",
			'%' . $wpdb->esc_like( $bad_token ) . '%'
		) );

		if ( $remaining_under !== 0 ) {
			throw new \RuntimeException(
				"Migration left {$remaining_under} _racgp_number rows behind — investigate."
			);
		}
		if ( $bad_actions_left !== 0 ) {
			throw new \RuntimeException(
				"Migration left {$bad_actions_left} Formidable actions still mapping to _racgp_number — investigate."
			);
		}

		return sprintf(
			'Patched %d Form 2 register action(s); deleted %d underscore duplicate rows; renamed %d stranded underscore rows to canonical; dropped %d empty canonical rows. Final state: %d racgp_number rows, 0 _racgp_number rows, 0 bad action mappings.',
			(int) $actions_patched,
			(int) $duplicates_deleted,
			(int) $renamed,
			(int) $empties_deleted,
			$remaining_canon
		);
	},
];

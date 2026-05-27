<?php
/**
 * Inject [hcp_mca_learning_enrol_button] into the activity homepage (post 111281).
 *
 * The page previously assumed users would arrive already enrolled in course 95553
 * (auto-enrolled by the old racgp_number theme hook on registration). With that hook
 * removed in favour of explicit click-based opt-in, brand-new users now have no
 * visible "Start Online Learning Module" button on the activity homepage — LearnDash's
 * existing [course_notstarted] / [course_inprogress] / [course_complete] shortcodes
 * only render for already-enrolled users.
 *
 * This migration prepends the new [hcp_mca_learning_enrol_button] shortcode immediately
 * before the [course_notstarted] block. The new shortcode renders only for logged-in
 * users with no LearnDash activity on course 95553; once a click goes through the modal
 * + AJAX enrol, LearnDash's own shortcodes take over.
 *
 * Idempotent: re-running matches zero rows once the marker is in place.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Add [hcp_mca_learning_enrol_button] to the Activity Homepage so non-enrolled users see a Start button.',

	'up' => function (): string {
		global $wpdb;

		$post_id = 111281;
		$anchor  = '[course_notstarted course_id="95553"]';
		$insert  = '[hcp_mca_learning_enrol_button]';

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID = %d",
			$post_id
		) );
		if ( ! $row ) {
			throw new \RuntimeException( "Activity Homepage post {$post_id} not found." );
		}

		// Already injected — no-op.
		if ( strpos( $row->post_content, $insert ) !== false ) {
			return 'Shortcode already present in post content. No change.';
		}

		// Collapse migration superseded this one — single always-on button already in place.
		if ( strpos( $row->post_content, '[hcp_mca_learning_module_button]' ) !== false ) {
			return 'Collapsed single-button shortcode already present; intermediate enrol-button injection not needed. No change.';
		}

		if ( strpos( $row->post_content, $anchor ) === false ) {
			throw new \RuntimeException(
				"Anchor '{$anchor}' not found in post {$post_id} — page structure has changed since migration was written."
			);
		}

		// Prepend the new shortcode immediately before the anchor — single replace,
		// only the first occurrence is touched. str_replace replaces all but the
		// anchor appears once on this page; we still guard for robustness.
		$pos = strpos( $row->post_content, $anchor );
		$updated_content =
			substr( $row->post_content, 0, $pos )
			. $insert
			. substr( $row->post_content, $pos );

		$result = $wpdb->update(
			$wpdb->posts,
			[ 'post_content' => $updated_content ],
			[ 'ID' => $post_id ],
			[ '%s' ],
			[ '%d' ]
		);
		if ( $result === false ) {
			throw new \RuntimeException( 'wpdb->update failed updating activity homepage content.' );
		}

		clean_post_cache( $post_id );

		return sprintf( 'Injected %s before %s in post %d.', $insert, $anchor, $post_id );
	},
];

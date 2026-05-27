<?php
/**
 * Collapse the four conditional learning-module button blocks on the activity
 * homepage (post 111281) into a single, always-rendering [hcp_mca_learning_module_button]
 * shortcode.
 *
 * Before this migration the page had:
 *   [hcp_mca_learning_enrol_button]                                 (not-enrolled state)
 *   [course_notstarted course_id="95553"]<a>Start...</a>[/...]       (enrolled, no progress)
 *   [course_inprogress course_id="95553"]<a>Resume...</a>[/...]      (in progress)
 *   [course_complete  course_id="95553"]<a>Review...</a>[/...]       (completed)
 *
 * That patchwork was fragile — any drift between LearnDash's internal state and the
 * meta keys these shortcodes read could leave ALL four blocks empty, hiding the
 * button entirely and leaving the user with no way into the course.
 *
 * One always-on button is more honest and easier to reason about.
 *
 * Idempotent: re-running finds no anchor and exits with a no-op message.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Collapse the 4 conditional learning-module buttons on the Activity Homepage into a single always-on shortcode.',

	'up' => function (): string {
		global $wpdb;

		$post_id     = 111281;
		$replacement = '[hcp_mca_learning_module_button]';

		// Two possible anchors depending on whether the not-enrolled-button migration
		// ran first (adds [hcp_mca_learning_enrol_button] prefix) or this runs on a
		// fresh DB where only the original 3 conditional blocks exist.
		$anchor_with_enrol    = '[hcp_mca_learning_enrol_button][course_notstarted course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Start Online Learning Module</a>[/course_notstarted][course_inprogress course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Resume Learning Module</a>[/course_inprogress][course_complete course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Review Learning Module</a>[/course_complete]';
		$anchor_without_enrol = '[course_notstarted course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Start Online Learning Module</a>[/course_notstarted][course_inprogress course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Resume Learning Module</a>[/course_inprogress][course_complete course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Review Learning Module</a>[/course_complete]';

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID = %d",
			$post_id
		) );
		if ( ! $row ) {
			throw new \RuntimeException( "Activity Homepage post {$post_id} not found." );
		}

		// Already collapsed — no-op.
		if ( strpos( $row->post_content, $replacement ) !== false
			&& strpos( $row->post_content, $anchor_with_enrol ) === false
			&& strpos( $row->post_content, $anchor_without_enrol ) === false ) {
			return 'Activity Homepage already collapsed to single shortcode. No change.';
		}

		if ( strpos( $row->post_content, $anchor_with_enrol ) !== false ) {
			$anchor = $anchor_with_enrol;
		} elseif ( strpos( $row->post_content, $anchor_without_enrol ) !== false ) {
			$anchor = $anchor_without_enrol;
		} else {
			throw new \RuntimeException(
				"Anchor block not found in post {$post_id} — page structure has drifted since migration was written. Inspect the post content before re-running."
			);
		}

		$updated = str_replace( $anchor, $replacement, $row->post_content );

		$result = $wpdb->update(
			$wpdb->posts,
			[ 'post_content' => $updated ],
			[ 'ID' => $post_id ],
			[ '%s' ],
			[ '%d' ]
		);
		if ( $result === false ) {
			throw new \RuntimeException( 'wpdb->update failed updating Activity Homepage content.' );
		}

		clean_post_cache( $post_id );

		return sprintf( 'Replaced 4-block conditional button mess with %s on post %d.', $replacement, $post_id );
	},
];

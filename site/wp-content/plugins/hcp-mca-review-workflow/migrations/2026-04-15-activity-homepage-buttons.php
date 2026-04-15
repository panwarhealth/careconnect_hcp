<?php
/**
 * Swap hardcoded buttons on page 111281 (Activity Homepage) for state-aware
 * shortcodes. Idempotent via pre-swap string presence check.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Swap hardcoded "Start" buttons on Activity Homepage (page 111281) for state-aware shortcodes.',

	'up' => function (): string {
		$post_id = 111281;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return "Page $post_id not found — skipping (page may have been renamed).";
		}

		$content   = $post->post_content;
		$original  = $content;
		$notes     = [];

		// --- Learning Module button ----------------------------------------
		$learning_old = '<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Start Online Learning Module</a>';
		$learning_new = '[course_notstarted course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Start Online Learning Module</a>[/course_notstarted]'
			. '[course_inprogress course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Resume Learning Module</a>[/course_inprogress]'
			. '[course_complete course_id="95553"]<a class="btn cta mt-0" href="/courses/anal-fissures-breaking-the-cycle-and-the-stigma/">Review Learning Module</a>[/course_complete]';

		if ( strpos( $content, $learning_new ) !== false ) {
			$notes[] = 'learning button already state-aware';
		} elseif ( strpos( $content, $learning_old ) !== false ) {
			$content = str_replace( $learning_old, $learning_new, $content );
			$notes[] = 'learning button swapped';
		} else {
			$notes[] = 'learning button pattern not found (edited by hand?)';
		}

		// --- Audit button block --------------------------------------------
		$audit_old = '[course_complete course_id="95553"]<a class="btn cta mt-0" href="/courses/mini-clinical-audit/">Start Mini Clinical Audit</a>[/course_complete] [course_inprogress course_id="95553"]<a class="btn cta mt-0 opacity-50 mb-0">Start Mini Clinical Audit</a>[/course_inprogress] [course_inprogress course_id="95553"]  <p class="italic font-semibold">You must complete the Online Learning Module before commencing the Mini Clinical Audit  </p>[/course_inprogress] [course_notstarted course_id="95553"]  <p class="italic font-semibold">You must complete the Online Learning Module before commencing the Mini Clinical Audit  </p>[/course_notstarted]';
		$audit_new = '[hcp_mca_audit_button]';

		if ( strpos( $content, $audit_new ) !== false ) {
			$notes[] = 'audit button already using shortcode';
		} elseif ( strpos( $content, $audit_old ) !== false ) {
			$content = str_replace( $audit_old, $audit_new, $content );
			$notes[] = 'audit button swapped';
		} else {
			$notes[] = 'audit button pattern not found (edited by hand?)';
		}

		if ( $content === $original ) {
			return 'No changes needed (' . implode( '; ', $notes ) . ').';
		}

		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $content,
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $result->get_error_message() );
		}

		return 'Page ' . $post_id . ' updated (' . implode( '; ', $notes ) . ').';
	},
];

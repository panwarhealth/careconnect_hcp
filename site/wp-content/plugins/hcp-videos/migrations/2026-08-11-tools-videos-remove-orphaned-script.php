<?php
/**
 * Remove the legacy inline video-trigger script from Tools & Videos.
 *
 * The 2026-08-10/11 content migrations saved the page from a context with KSES
 * active, which stripped the block's <script> tags and left its jQuery source
 * rendering as visible page text. (The runner now removes KSES filters for the
 * duration of a run, so this cannot recur.)
 *
 * Deleted rather than restored: the script drove in-card playback for cards
 * with a `js-video-trigger` class, and nothing on the page has carried that
 * class since the hub moved to [video_grid] cards that link to video pages.
 *
 * Removes the pb-element-inline-code-189 div, its contents, and the spinnr
 * state comment preceding it. Idempotent: no-ops once the block is gone.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Tools & Videos: remove the dead inline script block left rendering as visible text.',
	'up'          => function () {
		$page = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $page ) {
			return "Page 'tools-and-videos' not found.";
		}

		$content = $page->post_content;
		$open    = strpos( $content, '<div id="pb-element-inline-code-189">' );
		if ( false === $open ) {
			return 'Block already removed.';
		}

		// The block holds only text (its script tags are gone), so the first
		// closing div after the opener ends it.
		$close = strpos( $content, '</div>', $open );
		if ( false === $close ) {
			return 'Block close not found — page differs from expected, nothing changed.';
		}
		$end = $close + strlen( '</div>' );

		// The paired spinnr page-builder comment directly before the block.
		$start   = $open;
		$comment = strrpos( substr( $content, 0, $open ), '<!-- spinnr:' );
		if ( false !== $comment ) {
			$start = $comment;
		}

		wp_update_post( array(
			'ID'           => $page->ID,
			'post_content' => substr( $content, 0, $start ) . substr( $content, $end ),
		) );

		return 'Removed the orphaned script block (' . ( $end - $start ) . ' chars).';
	},
);

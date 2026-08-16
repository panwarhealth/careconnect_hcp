<?php
/**
 * Featured-card button text becomes per-post via {{card_cta}}.
 *
 * Pairs with the `card_cta` REST field in wp-spinnr-child/functions.php
 * (deploy functions.php together with this migration): the field returns the
 * `_hcp_card_cta` post meta, defaulting to "Read more". The blog featured-card
 * template swaps its hardcoded "Read more" for the tag, and the glucose quiz
 * post gets "Take the quiz".
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Blog featured card: per-post button text via {{card_cta}} (quiz post says "Take the quiz").',

	'up' => function (): string {
		$notes = [];

		$page = get_page_by_path( 'blog' );
		if ( ! $page ) {
			throw new \RuntimeException( 'Blog page not found.' );
		}

		$old = 'class="btn cta mt-1 md:mt-2 self-end md:self-auto" href="{{link}}">Read more</a>';
		$new = 'class="btn cta mt-1 md:mt-2 self-end md:self-auto" href="{{link}}">{{card_cta}}</a>';

		if ( strpos( $page->post_content, $new ) !== false ) {
			$notes[] = "Blog page {$page->ID} already uses {{card_cta}} — skipping template.";
		} elseif ( strpos( $page->post_content, $old ) === false ) {
			throw new \RuntimeException( "Featured-card button not matched on blog page {$page->ID} — content differs from expected; not changed." );
		} else {
			$result = wp_update_post(
				[ 'ID' => $page->ID, 'post_content' => str_replace( $old, $new, $page->post_content ) ],
				true
			);
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( 'wp_update_post failed: ' . $result->get_error_message() );
			}
			$notes[] = "Featured-card button now {{card_cta}} on blog page {$page->ID}.";
		}

		$quiz = get_page_by_path( 'guess-the-glucose-challenge', OBJECT, 'post' );
		if ( $quiz ) {
			update_post_meta( $quiz->ID, '_hcp_card_cta', 'Take the quiz' );
			$notes[] = "Quiz post {$quiz->ID} card CTA set to 'Take the quiz'.";
		} else {
			$notes[] = 'Quiz post not found — CTA meta not set (run the article migration first).';
		}

		return implode( ' ', $notes );
	},
];

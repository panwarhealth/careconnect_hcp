<?php
/**
 * Add the post excerpt to the /blog/ featured card (SPINNR wp-post-list template).
 *
 * The featured card (first wp-post-list element on the Blog page, per_page=1) only
 * rendered {{title.rendered}} + Read more. This inserts {{excerpt.rendered}} between
 * them, hidden on mobile where the card is compact.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Blog featured card: show the post excerpt between the title and the Read more button.',

	'up' => function (): string {
		$page = get_page_by_path( 'blog' );
		if ( ! $page ) {
			throw new \RuntimeException( 'Blog page not found.' );
		}

		$excerpt_div = '<div class="pr-xl hidden md:block">{{excerpt.rendered}}</div>';
		if ( strpos( $page->post_content, $excerpt_div ) !== false ) {
			return "Blog page {$page->ID} already has the excerpt in the featured card — skipping.";
		}

		$old = '<h5 class="pr-xl md:text-base lg:text-2xl">{{title.rendered}}</h5>' . "\n"
			. '         <a class="btn cta mt-1 md:mt-2 self-end md:self-auto" href="{{link}}">Read more</a>';
		$new = '<h5 class="pr-xl md:text-base lg:text-2xl">{{title.rendered}}</h5>' . "\n"
			. '         ' . $excerpt_div . "\n"
			. '         <a class="btn cta mt-1 md:mt-2 self-end md:self-auto" href="{{link}}">Read more</a>';

		if ( strpos( $page->post_content, $old ) === false ) {
			throw new \RuntimeException( "Featured-card template not matched on blog page {$page->ID} — content differs from expected; not changed." );
		}

		$result = wp_update_post(
			[ 'ID' => $page->ID, 'post_content' => str_replace( $old, $new, $page->post_content ) ],
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $result->get_error_message() );
		}

		return "Added excerpt to featured card template on blog page {$page->ID}.";
	},
];

<?php
/**
 * Client review round 2 on the Clinical Bites landing page and the Tools &
 * Videos hub (feedback PDF, 7 Aug 2026).
 *
 * Landing page:
 *   - Copy opens "Practical guidance..." rather than "Watch for practical...".
 *   - Episodes heading becomes "View all 5 episodes in the series:".
 *   - The `logged_in_users_only` wrappers come off the hero video and the
 *     episode carousel. That class blurs the block and floats a login box over
 *     it, which hid the episode titles — the titles are the reason to register,
 *     so [video_grid] now shows them and puts Login/Register on each card.
 *   - The hero button becomes [video_series_cta], which does the same.
 *   - A Related Resources row is added under the carousel, reusing the three
 *     assets already curated on the episode pages.
 *
 * Tools & Videos hub:
 *   - Jump link reads "Diabetes Clinical Bites", to distinguish it from the
 *     Allergic Rhinitis series that follows. The page slug is deliberately NOT
 *     touched: /clinical-bites/ is on printed QR codes.
 *   - Same episodes heading change.
 *
 * Targeted replacements rather than a content rewrite, so anything edited in
 * wp-admin since the page was seeded survives. Idempotent: each replacement is
 * a no-op once applied.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Clinical Bites review round 2: landing page copy, ungated episode cards, Related Resources row, hub jump link.',
	'up'          => function () {
		$notes = array();

		$landing_replacements = array(
			// Copy fix: the sentence now starts with the guidance itself.
			'<p class="" style="letter-spacing:.2px;line-height:1.75;">Watch for practical guidance on'
				=> '<p class="" style="letter-spacing:.2px;line-height:1.75;">Practical guidance on',

			'<h2 class="">Episodes in this series</h2>'
				=> '<h2 class="">View all 5 episodes in the series:</h2>',

			// Drop the blur/overlay treatment from the hero video and carousel.
			' lg:col-start-9 logged_in_users_only" data-pb-label="Column"'
				=> ' lg:col-start-9" data-pb-label="Column"',
			'<div class="content-block logged_in_users_only" data-pb-label="Content Block">[video_grid topic="clinical-bites-diabetes" series_total="5" layout="carousel"]'
				=> '<div class="content-block" data-pb-label="Content Block">[video_grid topic="clinical-bites-diabetes" series_total="5" layout="carousel"]',

			// Hero CTA becomes gate-aware, and opens the episode in a new tab.
			'<a class="btn cta ico i-arrow-right" href="/video/why-sick-day-planning-is-important-in-diabetes/" target="_self" rel="noopener">Watch now</a>'
				=> '[video_series_cta url="/video/why-sick-day-planning-is-important-in-diabetes/" label="Watch now"]',
		);

		$notes[] = hcp_videos_apply_content_replacements( 'clinical-bites', $landing_replacements );
		$notes[] = hcp_videos_add_landing_resources( 'clinical-bites' );

		$hub_replacements = array(
			'href="#clinicalbites">Clinical Bites</a>'
				=> 'href="#clinicalbites">Diabetes Clinical Bites</a>',
			'<h3 class="">Episodes in this series</h3>'
				=> '<h3 class="">View all 5 episodes in the series:</h3>',
		);

		$notes[] = hcp_videos_apply_content_replacements( 'tools-and-videos', $hub_replacements );

		return implode( ' ', array_filter( $notes ) );
	},
);

/**
 * Apply literal find/replace pairs to a page's content, saving only if changed.
 */
function hcp_videos_apply_content_replacements( string $slug, array $replacements ): string {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		return "Page '$slug' not found.";
	}

	$content = $page->post_content;
	$applied = 0;

	foreach ( $replacements as $from => $to ) {
		if ( false !== strpos( $content, $from ) ) {
			$content = str_replace( $from, $to, $content );
			$applied++;
		}
	}

	if ( 0 === $applied ) {
		return "Page '$slug': already up to date.";
	}

	wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );

	return "Page '$slug': applied {$applied} change(s).";
}

/**
 * Append the Related Resources row beneath the episode carousel, reusing the
 * resources already curated on episode 1 so the landing page and the video
 * pages cannot drift apart.
 */
function hcp_videos_add_landing_resources( string $slug ): string {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		return '';
	}

	if ( false !== strpos( $page->post_content, '[video_resources' ) ) {
		return 'Related Resources: already present.';
	}

	$episodes = get_posts( array(
		'post_type'      => 'video',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
		'tax_query'      => array( array(
			'taxonomy' => 'video_topic',
			'field'    => 'slug',
			'terms'    => 'clinical-bites-diabetes',
		) ),
	) );

	if ( ! $episodes ) {
		return 'Related Resources: no episodes found, skipped.';
	}

	$ids = array_filter( array_map( 'intval', (array) get_post_meta( $episodes[0], 'video_resources', true ) ) );
	if ( ! $ids ) {
		return 'Related Resources: episode 1 has no resources, skipped.';
	}

	$section = '<div class="section pt-0" data-pb-label="Section" id="clinicalbitesresources">'
		. ' <div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container">'
		. ' <div class="column" data-pb-label="Column">'
		. ' <div class="content-block" data-pb-label="Content Block"> <h2 class="">Related Resources:</h2> </div>'
		. ' <div class="content-block" data-pb-label="Content Block">[video_resources ids="' . implode( ',', $ids ) . '"]</div>'
		. ' </div> </div> </div>';

	// Sits after the carousel section and before the logged-out welcome banner.
	$anchor  = '<div class="py-0 section" data-pb-label="Section">[not_logged_in]';
	$content = $page->post_content;

	$content = false !== strpos( $content, $anchor )
		? str_replace( $anchor, $section . ' ' . $anchor, $content )
		: $content . ' ' . $section;

	wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );

	return 'Related Resources: added with ' . count( $ids ) . ' asset(s).';
}

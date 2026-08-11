<?php
/**
 * Create the Allergic Rhinitis Clinical Bites landing page
 * (/allergic-rhinitis-clinical-bites/).
 *
 * Mirrors the Diabetes series landing page in its current reviewed form: hero
 * (kicker, title, description, Watch now CTA, episode-1 tile), episode grid
 * (plain grid, not a carousel — a 3-part series fits side by side), Related
 * Resources, and the logged-out welcome CTA. Resource IDs are resolved by slug
 * at run time so the same file works on every environment.
 *
 * Idempotent: skips if the page already exists.
 * Depends on 2026-08-12-fess-clinical-bites-topic-video-1.php.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Create the Allergic Rhinitis Clinical Bites landing page.',
	'up'          => function () {
		$slug = 'allergic-rhinitis-clinical-bites';

		if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
			return 'Allergic Rhinitis Clinical Bites landing page already exists.';
		}

		$rids = array();
		foreach ( array(
			array( 'national-asthma-council-allergic-rhinitis-treatment-chart', 'resources' ),
			array( 'nasal-saline-patient-leaflet', 'resources' ),
			array( 'top-4-expert-tips-to-optimise-allergic-rhinitis-management-with-dr-jessica-tattersall', 'post' ),
		) as $pair ) {
			$r = get_page_by_path( $pair[0], OBJECT, $pair[1] );
			if ( $r ) {
				$rids[] = $r->ID;
			}
		}
		if ( 3 !== count( $rids ) ) {
			throw new RuntimeException( 'Only ' . count( $rids ) . ' of 3 resources resolved.' );
		}

		$content = <<<'HTML'
<div class="bg-secondary section hcp-cb-hero" data-pb-label="Section" id="clinicalbiteshero" style="padding-top:2.5rem"> <div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"> <div class="content-block pb-md" data-pb-label="Content Block"> <a class="no-underline text-accent font-semibold" href="/tools-and-videos/">&#8249;&nbsp;Back to Tools &amp; Videos</a> </div> <div class="grid md:grid-cols-12 gap-base"> <div class="column md:col-span-12" data-pb-label="Column"> <div class="content-block pt-lg" data-pb-label="Content Block" style="padding-bottom:0"> <p class="text-accent font-semibold" style="text-transform:uppercase;letter-spacing:.5px;font-size:.8rem;margin-bottom:.25rem">3-Part Series</p> <h1 class="">Clinical Bites Series: Allergic Rhinitis Rounds</h1> </div> </div> <div class="column md:col-span-8" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block"> <p class="" style="letter-spacing:.2px;line-height:1.75">Through three common case scenarios, Allergist/Medical Rhinologist <strong>Dr&nbsp;Jessica&nbsp;Tattersall</strong> and Specialist General Paediatrician <strong>Dr&nbsp;Jonny&nbsp;Taitz</strong> share their expert insights to support primary care providers with allergic rhinitis patient&nbsp;care.</p> </div> </div> <div class="column md:col-span-4 md:col-start-9 flex items-start md:justify-end hcp-hero-cta" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block" style="padding:0">[video_series_cta url="/video/not-another-sinus-infection/" label="Watch now"]</div> </div> </div> </div> </div> <div class="section pt-0" data-pb-label="Section" id="episodes" style="padding-top:.5rem"> <div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"> <div class="column" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block"> <h3 class="">View all 3 episodes in the series:</h3> </div> <div class="content-block" data-pb-label="Content Block">[video_grid topic="clinical-bites-allergic-rhinitis" series_total="3"]</div> </div> </div> </div> <div class="section pt-0" data-pb-label="Section" id="clinicalbitesresources"> <div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"> <div class="column" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block"> <h3 class="">Related Resources:</h3> </div> <div class="content-block" data-pb-label="Content Block">[video_resources ids="RESOURCE_IDS"]</div> </div> </div> </div> <div class="py-0 section" data-pb-label="Section">[not_logged_in] <div class="mx-auto max-w-7xl w-full px-4 md:px-6 grid mt-xl" data-pb-label="Container"> <div class="bg-center bg-cover col-span-full column justify-between items-center md:flex md:p-md md:p-xl p-lg rounded-md theme-dark gap-y-0" data-pb-label="Column" style="background-image: url('SITE_BASE_URL/wp-content/uploads/2025/02/CTA-background-small.png');"> <div class="content-block items-center justify-between md:flex" data-pb-label="Content Block"> <div class=""> <h2 class="">Welcome to Care Connect</h2> <p class="">The online portal for healthcare professional resources from Care Pharmaceuticals.</p> </div> </div> <div class="content-block flex items-center gap-xl" data-pb-label="Content Block"><a class="btn cta mt-md ml-auto" href="/register" target="_self" rel="noopener">Register</a><a class="btn cta mt-md ml-auto" href="/login" target="_self" rel="noopener">Login</a> </div> </div> </div>[/not_logged_in] </div>
HTML;

		$content = str_replace(
			array( 'SITE_BASE_URL', 'RESOURCE_IDS' ),
			array( home_url( '' ), implode( ',', $rids ) ),
			$content
		);

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Clinical Bites Series: Allergic Rhinitis Rounds',
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
				'post_author'  => 1,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 'Failed to create Allergic Rhinitis Clinical Bites landing page.';
		}

		return 'Allergic Rhinitis Clinical Bites landing page created (page ' . $post_id . ').';
	},
);

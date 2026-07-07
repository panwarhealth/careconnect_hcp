<?php
/**
 * Create the Clinical Bites landing page (/clinical-bites/).
 *
 * A Spinnr page-builder page (same markup grammar as the CAPH0111 article):
 * a staggered hero (series copy left, featured opener episode right) plus a
 * grid of all episodes via [video_grid]. The grid grows automatically as
 * episodes 2-4 are added under the `clinical-bites-diabetes` video_topic —
 * no page edit needed. HCP-only blocks carry `logged_in_users_only`; a
 * [not_logged_in] CTA covers logged-out visitors.
 *
 * Idempotent: skips if a page with slug `clinical-bites` already exists.
 * Depends on 2026-07-06-clinical-bites-video-1.php (topic term + episode 1).
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Create the Clinical Bites landing page (/clinical-bites/).',
	'up'          => function () {
		$slug = 'clinical-bites';

		if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
			return 'Clinical Bites landing page already exists.';
		}

		$content = <<<'HTML'
<div class="bg-secondary section" data-pb-label="Section" id="clinicalbiteshero"> <div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"> <div class="content-block pb-md" data-pb-label="Content Block"> <a class="no-underline text-accent font-semibold" href="/tools-and-videos/">&#8249;&nbsp;Back to Tools &amp; Videos</a> </div> <div class="grid md:grid-cols-12 gap-base items-center"> <div class="column md:col-span-7 flex items-center" data-pb-label="Column"> <div class="content-block py-lg" data-pb-label="Content Block"> <p class="text-accent font-semibold" style="text-transform:uppercase;letter-spacing:.5px;font-size:.8rem;margin-bottom:.25rem;">4-Part Series</p> <h1 class="">Clinical Bites Series: Diabetes Sick Day Management</h1> <p class="" style="letter-spacing:.2px;line-height:1.75;">Watch for practical guidance on improving the uptake and implementation of sick day action plans for patients with diabetes. Featuring Deborah Hawthorne, Consultant Pharmacist and Credentialled Diabetes Educator.</p> <a class="btn cta ico i-arrow-right" href="/video/why-sick-day-planning-is-important-in-diabetes/" target="_self" rel="noopener">Watch now</a> </div> </div> <div class="column md:col-span-4 md:col-start-9 logged_in_users_only" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block">[video_grid topic="clinical-bites-diabetes" columns="1" limit="1"]</div> </div> </div> </div> </div> <div class="section pt-0" data-pb-label="Section" id="episodes"> <div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"> <div class="column" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block"> <h2 class="">Episodes in this series</h2> </div> <div class="content-block logged_in_users_only" data-pb-label="Content Block">[video_grid topic="clinical-bites-diabetes" series_total="4" layout="carousel"]</div> </div> </div> </div> <div class="py-0 section" data-pb-label="Section">[not_logged_in] <div class="mx-auto max-w-7xl w-full px-4 md:px-6 grid mt-xl" data-pb-label="Container"> <div class="bg-center bg-cover col-span-full column justify-between items-center md:flex md:p-md md:p-xl p-lg rounded-md theme-dark gap-y-0" data-pb-label="Column" style="background-image: url('SITE_BASE_URL/wp-content/uploads/2025/02/CTA-background-small.png');"> <div class="content-block items-center justify-between md:flex" data-pb-label="Content Block"> <div class=""> <h2 class="">Welcome to Care Connect</h2> <p class="">The online portal for healthcare professional resources from Care Pharmaceuticals.</p> </div> </div> <div class="content-block flex items-center gap-xl" data-pb-label="Content Block"><a class="btn cta mt-md ml-auto" href="/register" target="_self" rel="noopener">Register</a><a class="btn cta mt-md ml-auto" href="/login" target="_self" rel="noopener">Login</a> </div> </div> </div>[/not_logged_in] </div>
HTML;

		$content = str_replace( 'SITE_BASE_URL', home_url( '' ), $content );

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Clinical Bites Series: Diabetes Sick Day Management',
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
				'post_author'  => 1,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 'Failed to create Clinical Bites landing page.';
		}

		return 'Clinical Bites landing page created (page ' . $post_id . ').';
	},
);

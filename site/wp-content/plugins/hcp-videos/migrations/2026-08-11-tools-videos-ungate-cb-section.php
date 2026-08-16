<?php
/**
 * Give the hub's Clinical Bites section the landing page's logged-out look.
 *
 * The section carried `logged_in_users_only`, which blurs it and floats a
 * login box on top — hiding the episode titles that are the reason to
 * register. The landing page dropped that in review round 2; this brings the
 * hub section in line: blur off, titles readable, each card and the hero
 * button carrying Login/Register.
 *
 * Only the two Clinical Bites sections change. Interactive Tools and the
 * general video library keep their blur.
 *
 * Idempotent: each replacement no-ops once applied.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Tools & Videos: Clinical Bites section visible when logged out, with Login/Register cards.',
	'up'          => function () {
		$page = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $page ) {
			return "Page 'tools-and-videos' not found.";
		}

		$replacements = array(
			'<div class="bg-secondary section hcp-cb-hero logged_in_users_only" data-pb-label="Section" id="clinicalbites">'
				=> '<div class="bg-secondary section hcp-cb-hero" data-pb-label="Section" id="clinicalbites">',
			'<div class="bg-secondary section pt-0 logged_in_users_only" data-pb-label="Section" id="clinicalbitesepisodes">'
				=> '<div class="bg-secondary section pt-0" data-pb-label="Section" id="clinicalbitesepisodes">',
			'<a class="btn cta ico i-arrow-right" href="/video/why-sick-day-planning-is-important-in-diabetes/" target="_blank" rel="noopener">Watch now</a>'
				=> '[video_series_cta url="/video/why-sick-day-planning-is-important-in-diabetes/" label="Watch now"]',
		);

		$content = $page->post_content;
		$applied = 0;
		foreach ( $replacements as $from => $to ) {
			if ( false !== strpos( $content, $from ) ) {
				$content = str_replace( $from, $to, $content );
				$applied++;
			}
		}

		if ( 0 === $applied ) {
			return 'Already applied.';
		}

		wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );

		return "Applied {$applied} change(s).";
	},
);

<?php
/**
 * Tools & Videos: add the Allergic Rhinitis Clinical Bites section.
 *
 * Per the CAPH0123 copy doc and mock-up: new jump link first in the nav, a
 * section above the Diabetes series with kicker/title/description and a
 * Watch now CTA, and the three episodes side by side — no carousel (three
 * tiles fit a row) and no standalone hero tile. The general Videos grid
 * excludes both series so episodes don't appear twice.
 *
 * Idempotent: each replacement no-ops once applied.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Tools & Videos: Allergic Rhinitis Clinical Bites section + nav link.',
	'up'          => function () {
		$page = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $page ) {
			return "Page 'tools-and-videos' not found.";
		}

		$section = <<<'HTML'
<div class="section hcp-cb-hero" data-pb-label="Section" id="arclinicalbites"> <div class="container" data-pb-label="Container"> <div class="grid md:grid-cols-12 gap-base items-center"> <div class="column md:col-span-8 flex items-center" data-pb-label="Column"> <div class="content-block py-lg" data-pb-label="Content Block"> <p class="text-accent font-semibold" style="text-transform:uppercase;letter-spacing:.5px;font-size:.8rem;margin-bottom:.25rem">3-Part Series</p> <h2 class="">Clinical Bites Series: Allergic Rhinitis Rounds</h2> <p class="" style="letter-spacing:.2px;line-height:1.75">Through three common case scenarios, Allergist/Medical Rhinologist <strong>Dr Jessica Tattersall</strong> and Specialist General Paediatrician <strong>Dr Jonny Taitz</strong> share their expert insights to support primary care providers with allergic rhinitis patient care.</p> </div> </div> <div class="column md:col-span-3 md:col-start-10 flex items-center md:justify-end" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block">[video_series_cta url="/video/not-another-sinus-infection/" label="Watch now"]</div> </div> </div> <div class="content-block" data-pb-label="Content Block"><h3 class="">View all 3 episodes in the series:</h3></div> <div class="content-block" data-pb-label="Content Block">[video_grid topic="clinical-bites-allergic-rhinitis" series_total="3"]</div> </div> </div>
HTML;

		$replacements = array(
			// Jump nav: AR link first (active), Diabetes link demoted to plain.
			'<a class="active border-b-2 no-underline text-heading" href="#clinicalbites">Diabetes Clinical Bites</a>'
				=> '<a class="active border-b-2 no-underline text-heading" href="#arclinicalbites">Allergic Rhinitis Clinical Bites</a><a href="#clinicalbites" class="no-underline text-paragraph hover:text-heading">Diabetes Clinical Bites</a>',
			// New section immediately above the Diabetes hero.
			'<div class="bg-secondary section hcp-cb-hero" data-pb-label="Section" id="clinicalbites">'
				=> $section . '<div class="bg-secondary section hcp-cb-hero" data-pb-label="Section" id="clinicalbites">',
			// Keep both series out of the general Videos grid.
			'[video_grid exclude_topic="clinical-bites-diabetes"]'
				=> '[video_grid exclude_topic="clinical-bites-diabetes,clinical-bites-allergic-rhinitis"]',
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

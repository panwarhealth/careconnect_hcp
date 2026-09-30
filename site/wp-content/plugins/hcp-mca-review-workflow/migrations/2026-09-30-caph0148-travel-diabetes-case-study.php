<?php
/**
 * Create CAPH0148 "Travelling with diabetes" interactive case study article.
 *
 * A scroll-down page of sections (intro, Meet Jess, Investigate, the factors question, Discuss,
 * Prepare, closing explainer). Each section appears when the one above is finished, and finished
 * sections stay on the page so the reader can scroll back to review them. Markup and answers live here; behaviour and styling
 * live in wp-spinnr-child/case-study/, loaded on posts carrying the `_hcp_case_study` meta.
 *
 * Images expected at wp-content/uploads/2026/10/caph0148/ on each environment BEFORE running:
 *   hero.jpg (1200x630, also the /blog/ card image), jess.jpg (800x800),
 *   clue-vietnam.jpg, clue-activities.jpg, clue-street-food.jpg, clue-fluids.jpg (600x600).
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Create CAPH0148 "Travelling with diabetes" interactive case study article.',

	'up' => function (): string {
		$slug = 'travelling-with-diabetes';

		$existing = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $existing ) {
			return "Post '$slug' already exists (ID {$existing->ID}), skipping.";
		}

		$base = home_url( '' );
		$img  = $base . '/wp-content/uploads/2026/10/caph0148/';

		/* ---- content data ---- */
		$clues = [
			[ 'clue-vietnam.jpg', '10 days travelling around Vietnam' ],
			[ 'clue-activities.jpg', 'Itinerary includes guided nature hikes, city walking tours and a kayaking day trip' ],
			[ 'clue-street-food.jpg', 'Excited to explore markets and try different street foods' ],
			[ 'clue-fluids.jpg', 'Dislikes public bathrooms, so limits fluid intake during flights and while out to avoid toileting' ],
		];

		$factors = [
			[ 'Heat and humidity', false ],
			[ 'Physical activity', false ],
			[ 'Reduced fluid intake', false ],
			[ 'Medications', false ],
			[ 'Food- or water-borne infections', false ],
			[ 'All the above', true ],
		];

		$columns = [
			'top'   => 'Top of the agenda',
			'time'  => 'Address if time permits',
			'defer' => 'Defer for now',
		];

		// [ label, correct column, left for the user to place when the hint fills the rest ]
		$sort_cards = [
			[ 'Food and water precautions', 'time', false ],
			[ 'Medication readiness', 'top', false ],
			[ 'Skin check', 'defer', false ],
			[ 'Hydration strategies', 'top', true ],
			[ 'Travel insurance and access to care', 'time', true ],
			[ 'Diabetes sick day plan', 'top', false ],
			[ "Mammo\u{AD}gram", 'defer', false ], // soft hyphen: breaks only in the narrow phone column
			[ 'Mosquito-bite prevention', 'time', false ],
		];

		// [ component, prompt, [ [ option, correct ], ... ] ]
		$plan_rows = [
			[ 'Checking glucose<sup>2,3</sup>', 'Jess should check her blood glucose levels…', [
				[ 'Hourly (if they are above 15 mmol/L for 8 hours or more)', false ],
				[ 'Every 2 to 4 hours (if they are above 15 mmol/L for 8 hours or more)', true ],
			] ],
			[ 'Medication management<sup>2,3</sup>', 'If Jess experiences vomiting or diarrhoea, she should…', [
				[ 'Pause her metformin and empagliflozin, then resume once she has been eating and drinking normally for at least 24 hours', true ],
				[ 'Increase the dose of her metformin, then return to normal dosage after at least 48 hours', false ],
			] ],
			[ 'Adequate hydration<sup>2,3</sup>', 'To stay hydrated, Jess should aim to drink…', [
				[ '125&#160;mL to 250&#160;mL of fluid every hour', true ],
				[ '250&#160;mL to 500&#160;mL of fluid every hour', false ],
			] ],
			[ 'Appropriate fluids<sup>2,3</sup>', 'If Jess’ blood glucose levels are above 10&#160;mmol/L, she should consume…', [
				[ 'Carbohydrate-containing fluids', false ],
				[ 'Carbohydrate-free fluids', true ],
			] ],
			[ 'Oral rehydration solutions (ORS)<sup>2,3</sup>', 'Advise Jess that these are suitable for hydration and…', [
				[ 'Depending on the formulation, some ORS can be considered carbohydrate free', true ],
				[ 'All ORS are considered carbohydrate-containing', false ],
			] ],
		];

		// [ title, description, url, button, thumbnail ]
		$resources = [
			[ 'Diabetes Sick Day Care Plan', 'A simple 2-page template to personalise for your patients', '#', 'Download', $img . 'placeholder-resource.jpg' ],
			[ 'Traveller’s diarrhoea: quick management guide', 'Read the latest on prevention and management strategies', '#', 'Read article', $img . 'placeholder-resource.jpg' ],
			[ 'KOL Clinical Bites', 'Bite-sized videos offering practical sick day management advice, featuring CDE Deb Hawthorne', $base . '/tools-and-videos/', 'Watch videos', $base . '/wp-content/uploads/2026/07/caph0105-clinical-bites-video-1-thumbnail.png' ],
			[ 'Using Oral Rehydration Solutions in diabetes', 'A helpful factsheet summarising key considerations and guideline recommendations', '#', 'Download', $img . 'placeholder-resource.jpg' ],
		];

		/* ---- builders ---- */
		$msg = function ( $key, $tone, $label, $html ) {
			return '<div class="cs-msg" data-cs-msg="' . esc_attr( $key ) . '" data-tone="' . esc_attr( $tone ) . '" data-label="' . esc_attr( $label ) . '" hidden>' . $html . '</div>';
		};

		$option = function ( $html, $correct ) {
			return '<button type="button" class="cs-opt" aria-pressed="false"' . ( $correct ? ' data-correct' : '' ) . '><span class="cs-opt__dot" aria-hidden="true"></span><span>' . $html . '</span></button>';
		};

		$flip_html = '';
		foreach ( $clues as $i => $c ) {
			$flip_html .= '<button type="button" class="cs-flip" aria-pressed="false" aria-label="Clue ' . ( $i + 1 ) . '">'
				. '<span class="cs-flip__inner">'
				. '<span class="cs-flip__face cs-flip__front"><img src="' . esc_url( $img . $c[0] ) . '" alt="" loading="lazy" /><span class="cs-flip__cue">Tap to reveal</span></span>'
				. '<span class="cs-flip__face cs-flip__back">' . esc_html( $c[1] ) . '</span>'
				. '</span></button>';
		}

		$factor_html = '';
		foreach ( $factors as $f ) {
			$factor_html .= $option( esc_html( $f[0] ), $f[1] );
		}

		$card_html = '';
		foreach ( $sort_cards as $c ) {
			$card_html .= '<button type="button" class="cs-card" data-cs-card data-answer="' . esc_attr( $c[1] ) . '"' . ( $c[2] ? ' data-hint-leave' : '' ) . '>' . esc_html( $c[0] ) . '</button>';
		}

		$col_html = '';
		foreach ( $columns as $key => $label ) {
			$col_html .= '<div class="cs-col" data-cs-col="' . esc_attr( $key ) . '">'
				. '<button type="button" class="cs-col__head" data-cs-drop><span class="cs-col__label">' . esc_html( $label ) . '</span><span class="cs-col__count" data-cs-count>0/3</span><span class="cs-col__here">Place here</span></button>'
				. '<div class="cs-col__list" data-cs-list></div>'
				. '</div>';
		}

		$row_html = '';
		foreach ( $plan_rows as $r ) {
			$opts = '';
			foreach ( $r[2] as $o ) {
				$opts .= $option( $o[0], $o[1] );
			}
			$row_html .= '<div class="cs-row" data-cs-row>'
				. '<div class="cs-row__q"><p class="cs-row__title">' . $r[0] . '</p><p class="cs-row__prompt">' . $r[1] . '</p></div>'
				. '<div class="cs-row__opts">' . $opts . '</div>'
				. '</div>';
		}

		$res_html = '';
		foreach ( $resources as $r ) {
			$target = ( substr( $r[2], -4 ) === '.pdf' ) ? '_blank' : '_self';
			$res_html .= '<div class="cs-res">'
				. '<a class="cs-res__thumb" href="' . esc_url( $r[2] ) . '" target="' . $target . '" aria-label="' . esc_attr( $r[0] ) . '"><img src="' . esc_url( $r[4] ) . '" alt="" loading="lazy" /></a>'
				. '<div class="cs-res__body"><h3>' . esc_html( $r[0] ) . '</h3><p>' . esc_html( $r[1] ) . '</p><a class="cs-btn" href="' . esc_url( $r[2] ) . '" target="' . $target . '">' . esc_html( $r[3] ) . '</a></div>'
				. '</div>';
		}

		/* ---- sections: each appears when the one above is finished. data-step drives the progress
		   bar; [data-cs-continue] reveals the next section and is held back until the section is done ---- */
		$section = function ( $step, $html, $continue = '', $continue_attrs = '' ) {
			$actions = $continue
				? '<div class="cs-actions" data-cs-actions><button type="button" class="cs-btn" data-cs-continue' . $continue_attrs . '>' . $continue . '</button></div>'
				: '';
			return '<section class="cs-sec" data-cs-sec data-step="' . $step . '">' . $html . $actions . '</section>';
		};

		$check = '<button type="button" class="cs-btn" data-cs-check>Check your answer</button>';

		$sections = ''
			. $section( 0,
				'<p>Staying healthy when travelling is important for everyone, but for your patients with diabetes, travelling can come with extra considerations and risks.</p>'
				. '<p>Put your pre-travel health check skills to the test with this mini case study.</p>',
				'Start your consultation', ' data-size="lg"' )
			. $section( 1,
				'<h2 class="cs-h">Meet your patient</h2>'
				. '<div class="cs-patient">'
				. '<img class="cs-patient__img" src="' . esc_url( $img . 'jess.jpg' ) . '" alt="Jess" />'
				. '<ul class="cs-patient__facts"><li><strong>Jess, 54 years old, female</strong></li><li>Well-controlled type 2 diabetes</li><li>Medications: metformin, empagliflozin</li></ul>'
				. '</div>'
				. '<p>Jess is booked in for a pre-travel consultation to discuss travel vaccines.</p>',
				'Learn more' )
			. $section( 2,
				'<p><strong>Investigate:</strong> Click on each clue to discover more details about Jess’s travel plans to help tailor your advice.</p>'
				. '<div class="cs-flips" data-cs-flips>' . $flip_html . '</div>',
				'Continue', ' disabled' )
			. $section( 2,
				'<p>Now that you’ve learned more about Jess’s travel plans, which of the following factors may affect her health while she is travelling?</p>'
				. '<div class="cs-quiz" data-cs-quiz data-right="factor-right" data-wrong="factor-wrong"><div class="cs-opts" data-cs-row role="group" aria-label="Factors">' . $factor_html . '</div>'
				. '<div class="cs-actions">' . $check . '</div></div>',
				'Continue', ' hidden' )
			. $section( 3,
				'<p><strong>Discuss:</strong> After discussing travel vaccines with Jess, there is limited time left in today’s consultation. You’ll need to choose which of the following points you will prioritise for further discussion.</p>'
				. '<p>Move each discussion point into the most appropriate column. Each column can hold a maximum of three cards, so choose carefully!</p>'
				. '<div class="cs-sort" data-cs-sort data-max="3" data-right="sort-right" data-wrong="sort-wrong">'
				. '<div class="cs-pool" data-cs-pool><p class="cs-pool__tip">Drag each card into a column, or tap a card and then tap a column.</p><div class="cs-pool__list" data-cs-list>' . $card_html . '</div><button type="button" class="cs-pool__drop" data-cs-drop>Return card here</button></div>'
				. '<div class="cs-cols">' . $col_html . '</div>'
				. '<div class="cs-actions">' . $check . '</div>'
				. '</div>',
				'Continue', ' hidden' )
			. $section( 4,
				'<p><strong>Prepare:</strong> Jess confirms that she doesn’t have a sick day plan to take with her to Vietnam, so it’s time to create one together.</p>'
				. '<p>For each of the sick day plan components below, select the correct advice to give Jess if she were to become unwell or dehydrated during her trip.</p>'
				. '<div class="cs-quiz" data-cs-quiz data-right="plan-right" data-wrong="plan-wrong">'
				. '<div class="cs-rows">' . $row_html . '</div>'
				. '<div class="cs-actions">' . str_replace( 'data-cs-check>', 'data-cs-check disabled>', $check ) . '</div>'
				. '</div>',
				'Continue', ' hidden' );

		$progress = '<ol class="cs-progress" data-cs-progress aria-label="Case study progress">'
			. '<li data-cs-dot="1"><span class="cs-progress__n">1</span><span class="cs-progress__label">Meet Jess</span></li>'
			. '<li data-cs-dot="2"><span class="cs-progress__n">2</span><span class="cs-progress__label">Investigate</span></li>'
			. '<li data-cs-dot="3"><span class="cs-progress__n">3</span><span class="cs-progress__label">Discuss</span></li>'
			. '<li data-cs-dot="4"><span class="cs-progress__n">4</span><span class="cs-progress__label">Prepare</span></li>'
			. '</ol>';

		$messages = ''
			. $msg( 'factor-wrong', 'wrong', 'Try again', '<p>Perhaps there’s another factor you could consider?</p>' )
			. $msg( 'factor-right', 'right', 'Continue', '<p class="cs-msg__title">Great choice!</p><p>The heat and humidity and large amounts of walking can increase Jess’ risk for dehydration, which may already be elevated following reduced fluid intake. She may also be putting herself at risk for food- or water-borne infections that could result in vomiting and diarrhoea, further exacerbating any dehydration. Importantly, dehydration and acute illness compromise diabetes control, and her medications (particularly the SGLT2 inhibitor) can increase the risk of developing diabetic ketoacidosis during illness.<sup>1</sup></p>' )
			. $msg( 'sort-full', 'info', 'Close', '<p>This column is full. Move one card to another column first to continue.</p>' )
			. $msg( 'sort-incomplete', 'info', 'Close', '<p>Place all cards into a column before checking your answer.</p>' )
			. $msg( 'sort-wrong', 'wrong', 'Try again', '<p>Perhaps you could consider different prioritisation?</p>' )
			. $msg( 'sort-hint', 'wrong', 'Show me a hint', '<p>Not quite. Would you like a hint?</p>' )
			. $msg( 'sort-right', 'right', 'Continue', '<p class="cs-msg__title">Nicely done!</p><p>Jess’ immediate travel risks are dehydration and acute illness, particularly if she develops vomiting or diarrhoea while on her regular medicines. Sick day planning, medication readiness and practical hydration strategies should therefore be prioritised, as they are key to reducing her risk of acute harm while travelling.<sup>2</sup></p><p>If time permits, discussion on food and water safety, mosquito-bite prevention and travel insurance/access to care would also be beneficial based on Jess’ itinerary.<sup>2</sup> Her routine skin check and mammogram, while important, can be considered a lower priority for today.</p>' )
			. $msg( 'plan-wrong', 'wrong', 'Try again', '<p>That’s not quite right.</p>' )
			. $msg( 'plan-right', 'right', 'Continue', '<p class="cs-msg__title">Perfect!</p><p>Providing Jess with a simple, personalised Sick Day Care Plan that provides clear guidance on glucose monitoring, medication management, and appropriate hydration is a practical and highly impactful step to support her health while travelling.</p><p>With that, you have successfully navigated your pre-travel health consultation with Jess. Congratulations!</p>' );

		$closing = '<div class="cs-closing" data-cs-closing>'
			. '<div class="cs-closing__box">'
			. '<h2>Support travel health in your patients with diabetes</h2>'
			. '<p>For patients with diabetes and other chronic conditions, pre-travel health checks are a valuable opportunity to provide important travel health and safety education that these patients may not have previously considered.</p>'
			. '<p>Keep these key questions in mind for your next pre-travel consultation:</p>'
			. '<ul class="cs-keys">'
			. '<li><strong>What potential risks could your patient be exposed to?</strong> Consider the location and climate, and your patient’s age, comorbidities, and medications.</li>'
			. '<li><strong>Is your patient’s treatment travel-ready?</strong> Review their access to medications and medical supplies, and whether any management adjustments are required.</li>'
			. '<li><strong>Does your patient know what to do if illness develops?</strong> Create an individualised sick day plan and ensure they understand appropriate strategies for managing dehydration.</li>'
			. '<li><strong>What hydration strategy do you recommend for your patient?</strong> Consider that oral rehydration solutions, such as Hydralyte, are formulated to rehydrate faster than water alone<sup>4-7</sup> and are suitable for use by people with diabetes.<sup>2,3</sup></li>'
			. '<li><strong>When and where should your patient seek help?</strong> Provide clear and practical instructions to follow if they do require medical attention.</li>'
			. '</ul>'
			. '</div>'
			. '<h2 class="cs-res-title">Related resources</h2>'
			. '<div class="cs-res-grid">' . $res_html . '</div>'
			. '<div class="grid" style="margin-top:2.75rem"><div class="bg-center bg-cover col-span-full column justify-between items-center md:flex md:p-md md:p-xl p-lg rounded-md theme-dark gap-y-0" style="background-image: url(\'' . $base . '/wp-content/uploads/2025/02/CTA-background-small.png\');"><div class="content-block items-center justify-between md:flex"><div><h2>Order Hydralyte samples</h2><p>Delivered directly to your practice, free of charge</p></div></div><div class="content-block grid items-center ml-auto"><a class="btn cta mt-md ml-auto" href="/order-samples/" target="_self">Order samples</a></div></div></div>'
			. '</div>';

		$open  = function ( $extra, $width ) {
			return '<div class="section ' . $extra . '" data-pb-label="Section"><div class="mx-auto ' . $width . ' w-full px-4 md:px-6" data-pb-label="Container"><div class="column" data-pb-label="Column"><div class="content-block" data-pb-label="Content Block">';
		};
		$close = '</div></div></div></div>';

		/* ---- assemble post_content ---- */
		$content = ''
			. $open( 'pb-lg', 'max-w-7xl' )
			. '<div class="flex gap-md items-center justify-center mb-0"><p class="bg-accent-secondary mb-0 px-6 py-2 rounded-full text-heading">Case study</p><p class="mb-0">' . esc_html( wp_date( 'd.m.y' ) ) . '</p></div>'
			. $close
			. $open( 'pt-0 pb-0', 'max-w-4xl' )
			. '<img src="' . esc_url( $img . 'hero.jpg' ) . '" class="cs-hero h-auto rounded-xl w-full" alt="" />'
			. '<h1 class="cs-title">Travelling with diabetes: A 3-step check before take&#8209;off</h1>'
			. $close
			. $open( 'pt-0 pb-0 logged_in_users_only', 'max-w-4xl' )
			. '<div id="hcp-cs" class="cs" data-cs>'
			. $progress . $sections
			. $closing
			. $messages
			. '</div>'
			. $close
			. '<div class="py-0 section" data-pb-label="Section">[not_logged_in]<div class="mx-auto max-w-7xl w-full px-4 md:px-6 grid mt-xl" data-pb-label="Container"><div class="bg-center bg-cover col-span-full column justify-between items-center md:flex md:p-md md:p-xl p-lg rounded-md theme-dark gap-y-0" data-pb-label="Column" style="background-image: url(\'' . $base . '/wp-content/uploads/2025/02/CTA-background-small.png\');"><div class="content-block items-center justify-between md:flex" data-pb-label="Content Block"><div><h2>Welcome to Care Connect</h2><p><span style="font-size:1rem">The online portal for healthcare professional resources from Care Pharmaceuticals. Register to start the case study.</span></p></div></div><div class="content-block flex items-center gap-xl" data-pb-label="Content Block"><a class="btn cta mt-md ml-auto" href="/register">Register</a><a class="btn cta mt-md ml-auto" href="/login">Login</a></div></div></div>[/not_logged_in]</div>'
			. '<div class="section pt-0" data-pb-label="Section"><div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"><div class="column md:col-span-full py-xl" data-pb-label="Column"><div class="content-block" data-pb-label="Content Block">'
			. '<p class="text-sm"><b>References:</b></p>'
			. '<ol class="text-sm">'
			. '<li>Mohan V, et al. <i>J Assoc Physicians India</i>. 2024;72(6S):16&#8211;24.</li>'
			. '<li>Australian Diabetes Educators Association. Clinical guiding principles for sick day management of adults with type 1 diabetes or type 2 diabetes: A guide for health professionals, Version 4.1.0 [updated June 2025]. Available from: https://www.ndss.com.au/wp-content/uploads/clinical-guide-sick-day-mngt.pdf (accessed September 2026).</li>'
			. '<li>National Diabetes Services Scheme. Diabetes Sick Day Action Plan: Type 2 diabetes not using insulin. Version 2.1, February 2025. NDSSQCKG001. Available from: https://www.ndss.com.au/wp-content/uploads/ADEA-Sick-Day-Action-Plan-Type-2-not-using-insulin-fillable.pdf (accessed September 2026).</li>'
			. '<li>World Health Organization (WHO). Oral rehydration salts. Production of the new ORS. 2006. Available from: https://www.who.int/publications/i/item/WHO-FCH-CAH-06.1 (accessed September 2026).</li>'
			. '<li>Mishra AB. <i>Int J Life Sci Biotechnol Pharm Res</i>. 2025;14(7):516&#8211;517.</li>'
			. '<li>Jeukendrup AE, et al. <i>Nutr Metabl (Lond)</i>. 2009;6:9.</li>'
			. '<li>Wright EM, et al. <i>J Intern Med</i>. 2007;261(1):32&#8211;43.</li>'
			. '</ol>'
			. '<p class="text-sm">&#169;Care Pharmaceuticals 2026. Hydralyte&#174; is a registered trademark of Care Pharmaceuticals. All rights reserved. October 2026.</p>'
			. '<p class="text-sm font-bold">This information is intended for use by healthcare professionals only.</p>'
			. '</div></div></div></div>';

		// Buttons, data-* and hidden attributes must survive a non-admin run.
		kses_remove_filters();
		$post_id = wp_insert_post(
			[
				'post_title'   => 'Travelling with diabetes: A 3-step check before take-off',
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_content' => $content,
				'post_excerpt' => 'Put your pre-travel health check skills to the test with this mini case study on travelling with diabetes.',
				'post_date'    => current_time( 'mysql' ),
				'post_author'  => 1,
			],
			true
		);
		kses_init_filters();

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( 'wp_insert_post failed: ' . $post_id->get_error_message() );
		}

		wp_set_post_categories( $post_id, [ 1 ] );
		update_post_meta( $post_id, '_hcp_case_study', 1 );
		update_post_meta( $post_id, '_hcp_card_cta', 'Start the case study' );

		$hero_rel = '2026/10/caph0148/hero.jpg';
		$hero_abs = wp_upload_dir()['basedir'] . '/' . $hero_rel;
		if ( ! file_exists( $hero_abs ) ) {
			return "Created post '$slug' (ID $post_id). WARNING: $hero_rel not found, the /blog/ card has no image until it is uploaded.";
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$thumb_id = wp_insert_attachment(
			[ 'post_title' => 'Travelling with diabetes case study hero', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ],
			$hero_abs,
			$post_id
		);
		update_post_meta( $thumb_id, '_wp_attached_file', $hero_rel );
		wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $hero_abs ) );
		set_post_thumbnail( $post_id, $thumb_id );

		return "Created post '$slug' (ID $post_id) + featured image (attachment $thumb_id).";
	},
];

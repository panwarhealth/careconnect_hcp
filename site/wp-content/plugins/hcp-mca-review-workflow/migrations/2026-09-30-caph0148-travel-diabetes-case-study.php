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
 *   hero.webp (1200x630, also the /blog/ card image), jess.webp (800x800),
 *   clue-vietnam.webp, clue-activities.webp, clue-street-food.webp, clue-fluids.webp (600x800 portrait).
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
			[ 'clue-vietnam.webp', '10 days travelling around Vietnam' ],
			[ 'clue-activities.webp', 'Itinerary includes guided nature hikes, city walking tours and a kayaking day trip' ],
			[ 'clue-street-food.webp', 'Excited to explore markets and try different street foods' ],
			[ 'clue-fluids.webp', 'Dislikes public bathrooms, so limits fluid intake during flights and while out to avoid toileting' ],
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

		// [ label, correct column ]
		$sort_cards = [
			[ 'Food and water precautions', 'time' ],
			[ 'Medication readiness', 'top' ],
			[ 'Skin check', 'defer' ],
			[ 'Hydration strategies', 'top' ],
			[ 'Travel insurance and access to care', 'time' ],
			[ 'Diabetes sick day plan', 'top' ],
			[ "Mammo\u{AD}gram", 'defer' ], // soft hyphen: breaks only in the narrow phone column
			[ 'Mosquito-bite prevention', 'time' ],
		];

		// [ component, prompt, [ [ option, correct ], ... ] ]
		$plan_rows = [
			[ 'Checking glucose<sup>2,3</sup>', 'Jess should check her blood glucose levels more regularly, generally…', [
				[ 'Hourly (if they are above 15&#160;mmol/L for 8&#160;hours or more)', false ],
				[ 'Every 2&#160;to&#160;4&#160;hours (if they are above 15&#160;mmol/L for 8&#160;hours or more)', true ],
			] ],
			[ 'Medication management<sup>2,3</sup>', 'If Jess experiences vomiting or diarrhoea, she should…', [
				[ 'Pause her metformin and empagliflozin, then resume once she has been eating and drinking normally for at least 24&#160;hours', true ],
				[ 'Increase the dose of her metformin, then return to normal dosage after at least 48&#160;hours', false ],
			] ],
			[ 'Adequate hydration<sup>2,3</sup>', 'To stay hydrated, Jess should aim to drink…', [
				[ '125mL to 250mL of fluid every hour', true ],
				[ '250mL to 500mL of fluid every hour', false ],
			] ],
			[ 'Appropriate fluids<sup>2,3</sup>', 'If Jess’ blood glucose levels are above 10&#160;mmol/L, she should consume…', [
				[ 'Carbohydrate-containing fluids', false ],
				[ 'Carbohydrate-free fluids', true ],
			] ],
			[ 'Oral rehydration solutions (ORS)<sup>2,3</sup>', 'Advise Jess that ORS are suitable for hydration and…', [
				[ 'Depending on the formulation, some ORS can be considered carbohydrate free', true ],
				[ 'All ORS are considered carbohydrate-containing', false ],
			] ],
		];

		// [ title, description, url, link label, thumbnail, opens in a new tab, video (play icon) ]
		// PDFs go through their tracked redirect pages so the click registers in GA4.
		$uploads   = $base . '/wp-content/uploads/';
		$resources = [
			[ 'Diabetes Sick Day Care Plan', 'A simple 2-page template to personalise for your patients', $base . '/hydralyte-sick-days/', 'Download', $uploads . '2026/03/hydra-sick-days.png', true, false ],
			[ 'Traveller’s diarrhoea: quick management guide', 'Read the latest on prevention and management strategies', $base . '/blog/travellers-diarrhoea-quick-management-guide-for-the-holiday-season/', 'Read article', $uploads . '2025/11/shutterstock_141564331_orange-suitcase-scaled.jpg', false, false ],
			[ 'KOL Clinical Bites', 'Bite-sized videos offering practical sick day management advice, featuring CDE Deb Hawthorne', $base . '/clinical-bites/', 'Watch videos', $uploads . '2026/07/caph0105-clinical-bites-video-1-thumbnail.png', false, true ],
			[ 'Using Oral Rehydration Solutions in diabetes', 'A helpful factsheet summarising key considerations and guideline recommendations', $base . '/oral-rehydration-in-diabetes/', 'Download', $uploads . '2026/08/caph0124-hydralyte-ors-diabetes-thumb.jpg', true, false ],
		];

		/* ---- builders ---- */
		// Keeps the last two words of a plain-text string on one line.
		$tie = function ( $text ) {
			return preg_replace( '/ (\S+)$/u', "\u{A0}$1", $text );
		};

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
				. '<span class="cs-flip__face cs-flip__front"><img src="' . esc_url( $img . $c[0] ) . '" alt="" loading="lazy" /></span>'
				. '<span class="cs-flip__face cs-flip__back">' . esc_html( $tie( $c[1] ) ) . '</span>'
				. '</span></button>';
		}

		$factor_html = '';
		foreach ( $factors as $f ) {
			$factor_html .= $option( esc_html( $f[0] ), $f[1] );
		}

		$card_html = '';
		foreach ( $sort_cards as $c ) {
			$card_html .= '<button type="button" class="cs-card" data-cs-card data-answer="' . esc_attr( $c[1] ) . '">' . esc_html( $c[0] ) . '</button>';
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

		// Same card markup as the site's resource and video listings.
		$play = '<span class="cs-play" aria-hidden="true"><svg viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="25" cy="25" r="25" fill="white" fill-opacity="0.92"/><path d="M37.4331 24.1265C38.1172 24.5079 38.1172 25.4921 37.4331 25.8735L18.7369 36.2955C18.0703 36.6671 17.25 36.1852 17.25 35.422L17.25 14.578C17.25 13.8148 18.0703 13.3329 18.7369 13.7045L37.4331 24.1265Z" fill="#35B1C9"/></svg></span>';
		$res_html = '';
		foreach ( $resources as $r ) {
			$target = $r[5] ? '_blank' : '_self';
			$res_html .= '<a class="cs-res no-underline" href="' . esc_url( $r[2] ) . '" target="' . $target . '">'
				. '<div class="card h-full overflow-hidden">'
				. '<div class="bg-secondary p-md h-48 rounded-t relative">' . ( $r[6] ? $play : '' ) . '<img src="' . esc_url( $r[4] ) . '" class="h-full object-contain mx-auto" alt="" loading="lazy" /></div>'
				. '<div class="card-body"><div><h3>' . esc_html( $tie( $r[0] ) ) . '</h3><p>' . esc_html( $tie( $r[1] ) ) . '</p></div>'
				. '<span class="underline text-accent font-semibold">' . esc_html( $r[3] ) . '</span></div>'
				. '</div></a>';
		}

		/* ---- sections: each appears when the one above is finished. data-step drives the progress
		   bar; [data-cs-continue] reveals the next section and is held back until the section is done ---- */
		// $name labels the section in analytics events (case-study.js).
		$section = function ( $step, $name, $html, $continue = '', $continue_attrs = '' ) {
			$actions = $continue
				? '<div class="cs-actions" data-cs-actions><button type="button" class="cs-btn" data-cs-continue' . $continue_attrs . '>' . $continue . '</button></div>'
				: '';
			return '<section class="cs-sec" data-cs-sec data-step="' . $step . '" data-name="' . esc_attr( $name ) . '">' . $html . $actions . '</section>';
		};

		$check = '<button type="button" class="cs-btn" data-cs-check>Check your answer</button>';

		$sections = ''
			. $section( 0, 'intro',
				'<p>Staying healthy when travelling is important for everyone, but for your patients with diabetes, travelling can come with extra considerations and risks.</p>'
				. '<p><strong>Put your pre-travel health check skills to the test with this mini case study.</strong></p>',
				'Start your consultation', ' data-size="lg"' )
			. $section( 1, 'meet_jess',
				'<h2 class="cs-h">Meet your patient</h2>'
				. '<div class="cs-patient">'
				. '<img class="cs-patient__img" src="' . esc_url( $img . 'jess.webp' ) . '" alt="Jess" />'
				. '<ul class="cs-patient__facts"><li><strong>Jess, 54 years old, female</strong></li><li>Well-controlled type 2 diabetes</li><li>Medications: metformin, empagliflozin</li></ul>'
				. '</div>'
				. '<p>Jess is booked in for a pre-travel consultation to discuss travel vaccines.</p>',
				'Learn more' )
			. $section( 2, 'investigate',
				'<p><strong>Identify the risks:</strong> Click on each clue to discover more details about Jess’s travel plans to help tailor your advice.</p>'
				. '<div class="cs-flips" data-cs-flips>' . $flip_html . '</div>',
				'Continue', ' disabled' )
			. $section( 2, 'factors_question',
				'<p>Now that you’ve learned more about Jess’s travel plans, which of the following factors may affect her health while she is travelling?</p>'
				. '<div class="cs-quiz" data-cs-quiz data-right="factor-right" data-wrong="factor-wrong"><div class="cs-opts" data-cs-row role="group" aria-label="Factors">' . $factor_html . '</div>'
				. '<div class="cs-actions">' . $check . '</div></div>',
				'Continue', ' hidden' )
			. $section( 3, 'discuss',
				'<p><strong>Decide the discussion:</strong> After discussing travel vaccines with Jess, there is limited time left in today’s consultation. You’ll need to choose which of the following points you will prioritise for further discussion.</p>'
				. '<p>Move each discussion point into the most appropriate column. Each column can hold a maximum of three cards – choose carefully!</p>'
				. '<div class="cs-sort" data-cs-sort data-max="3" data-right="sort-right">'
				. '<div class="cs-pool" data-cs-pool><p class="cs-pool__tip">Drag each card into a column, or tap a card and then tap a column.</p><div class="cs-pool__list" data-cs-list>' . $card_html . '</div><button type="button" class="cs-pool__drop" data-cs-drop>Return card here</button></div>'
				. '<div class="cs-cols">' . $col_html . '</div>'
				. '</div>',
				'Continue', ' hidden' )
			. $section( 4, 'prepare',
				'<p><strong>Prepare the plan:</strong> Jess confirms that she doesn’t have a sick day plan to take with her to Vietnam, so it’s time to create one together.</p>'
				. '<p>For each of the sick day plan components below, select the correct guideline-based advice to give Jess if she were to become unwell or dehydrated during her trip.</p>'
				. '<div class="cs-quiz" data-cs-quiz data-right="plan-right" data-wrong="plan-wrong">'
				. '<div class="cs-rows">' . $row_html . '</div>'
				. '<div class="cs-actions">' . str_replace( 'data-cs-check>', 'data-cs-check disabled>', $check ) . '</div>'
				. '</div>',
				'Continue', ' hidden' );

		$progress = '<ol class="cs-progress" data-cs-progress aria-label="Case study progress">'
			. '<li data-cs-dot="1"><span class="cs-progress__n">1</span><span class="cs-progress__label">Meet Jess</span></li>'
			. '<li data-cs-dot="2"><span class="cs-progress__n">2</span><span class="cs-progress__label">Identify the risks</span></li>'
			. '<li data-cs-dot="3"><span class="cs-progress__n">3</span><span class="cs-progress__label">Decide the discussion</span></li>'
			. '<li data-cs-dot="4"><span class="cs-progress__n">4</span><span class="cs-progress__label">Prepare the plan</span></li>'
			. '</ol>';

		$messages = ''
			. $msg( 'factor-wrong', 'wrong', 'Try again', '<p>Perhaps there’s another factor you could consider?</p>' )
			. $msg( 'factor-right', 'right', 'Continue', '<p class="cs-msg__title">Great choice!</p><p>The heat and humidity and large amounts of walking can increase Jess’ <strong>risk for dehydration</strong>, which may already be elevated following reduced fluid intake. She may also be putting herself at <strong>risk for food- or water-borne infections</strong> that could result in vomiting and diarrhoea, further exacerbating any dehydration. Importantly, <strong>dehydration and acute illness compromise diabetes control</strong>, and her medications (particularly the SGLT2 inhibitor) can increase the risk of developing diabetic ketoacidosis during illness.<sup>1</sup></p>' )
			. $msg( 'sort-right', 'right', 'Continue', '<p class="cs-msg__title">Nicely done!</p><p>Jess’ immediate travel risks are <strong>dehydration and acute illness</strong>, particularly if she develops vomiting or diarrhoea while on her regular medicines. Sick day planning, medication readiness and practical hydration strategies should therefore be prioritised, as they are key to <strong>reducing her risk of acute harm while travelling.</strong><sup>2</sup></p><p>If time permits, discussion on food and water safety, mosquito-bite prevention and travel insurance/access to care would also be beneficial based on Jess’ itinerary.<sup>2</sup> Her routine skin check and mammogram, while important, can be considered a lower priority for today.</p>' )
			. $msg( 'plan-wrong', 'wrong', 'Try again', '<p>Some answers aren’t quite right – which options better match the guidelines?</p>' )
			. $msg( 'plan-right', 'right', 'Continue', '<p class="cs-msg__title">Perfect!</p><p>Providing Jess with a simple, personalised Sick Day Care Plan that provides <strong>clear guidance on glucose monitoring, medication management, and appropriate hydration</strong> is a practical and highly impactful step to support her health while travelling.</p><p><strong>With that, you have successfully navigated your pre-travel health consultation with Jess – congratulations!</strong></p>' );

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
			. '<img src="' . esc_url( $img . 'hero.webp' ) . '" class="cs-hero h-auto rounded-xl w-full" alt="" />'
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

		// No line in a paragraph or list item ends on a lone word; headings too, unless the tied pair
		// would be too wide for a phone at heading size. The lookahead skips a trailing tag attribute.
		$content = preg_replace_callback(
			'/(\S+) (?![^\s]*=)(\S+)(<\/(p|li|h[1-3])>)/u',
			function ( $m ) {
				$pair = wp_strip_all_tags( $m[1] . ' ' . $m[2] );
				if ( 'h' === $m[4][0] && mb_strlen( $pair ) > 16 ) {
					return $m[0];
				}
				return $m[1] . '&#160;' . $m[2] . $m[3];
			},
			$content
		);

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

		$hero_rel = '2026/10/caph0148/hero.webp';
		$hero_abs = wp_upload_dir()['basedir'] . '/' . $hero_rel;
		if ( ! file_exists( $hero_abs ) ) {
			return "Created post '$slug' (ID $post_id). WARNING: $hero_rel not found, the /blog/ card has no image until it is uploaded.";
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$thumb_id = wp_insert_attachment(
			[ 'post_title' => 'Travelling with diabetes case study hero', 'post_mime_type' => 'image/webp', 'post_status' => 'inherit' ],
			$hero_abs,
			$post_id
		);
		update_post_meta( $thumb_id, '_wp_attached_file', $hero_rel );
		wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $hero_abs ) );
		set_post_thumbnail( $post_id, $thumb_id );

		return "Created post '$slug' (ID $post_id) + featured image (attachment $thumb_id).";
	},
];

<?php
/**
 * Apply the signed-off SEO titles and meta descriptions
 * (HCP-SEO-meta-review-07-2026, approved 2026-07-23).
 *
 * Homepage title/description live in rank-math-options-titles (set by the
 * go-live migration). Terms of Use and Privacy Policy intentionally keep
 * auto-generated output.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'SEO: signed-off Rank Math titles + meta descriptions for public pages and posts.',
	'up'          => function (): string {
		$pages = [
			'nasal-and-sinus-health' => [
				'Nasal and Sinus Health Hub | Care Connect',
				'Clinical resources for nasal and sinus care: saline irrigation guidance, patient education materials and practical tools for Australian GPs and pharmacists.',
			],
			'tools-and-videos' => [
				'Tools and Videos for Patient Consultations | Care Connect',
				'In-consultation tools and educational videos, including the Clinical Bites series on diabetes sick day management, built to support patient discussions.',
			],
			'clinical-bites' => [
				'Clinical Bites Series: Diabetes Sick Day Management | Care Connect',
				'Short expert videos on sick day management for patients with diabetes: practical guidance on hydration, medication adjustment and when to escalate care.',
			],
			'allergy-analyser-tool' => [
				'Allergy Analyser: Personalised Allergy Treatment Plans | Care Connect',
				'Create personalised ocular and nasal allergy treatment plans based on patient triggers, symptoms and ASCIA guidelines. Free for registered HCPs.',
			],
			'fess-demonstration' => [
				'FESS How-To Videos for Patients | Care Connect',
				'Demonstration videos developed with the National Asthma Council showing correct use of FESS nasal saline sprays and washes. Share them with patients.',
			],
			'order-samples' => [
				'Order Free Product Samples | Care Connect',
				'Registered Australian healthcare professionals can order samples from the Care Pharmaceuticals portfolio for their practice, delivered free of charge.',
			],
			'anal-fissures-breaking-the-cycle-and-the-stigma-landing' => [
				'Anal Fissures: Breaking the Cycle and the Stigma | Care Connect',
				'A two-part CPD activity for GPs on diagnosing and managing anal fissures, and on helping patients past the stigma that delays care.',
			],
			'anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage' => [
				'Anal Fissures CPD Activity Homepage | Care Connect',
				'Activity homepage for the anal fissures education series: complete the learning module and evaluation to earn CPD hours.',
			],
			'blog' => [
				'Blog: Clinical Insights for Healthcare Professionals | Care Connect',
				'Practical clinical articles for Australian GPs, pharmacists and nurses: allergy, hydration, paediatric and GI topics, written for everyday practice.',
			],
			'contact' => [
				'Contact Us | Care Connect',
				'Get in touch with the Care Pharmaceuticals team by form, email or phone. Freecall 1800 788 870 within Australia. Adverse event reporting details included.',
			],
		];

		// Posts keep their own titles; only descriptions are set.
		$posts = [
			'how-you-can-best-ass-ist-your-patients-with-anal-fissures' => 'Up to one in five people develop an anal fissure, yet stigma keeps many from raising it. Practical guidance for GPs on assessment and management.',
			'eye-care-for-children-with-conjunctivitis-a-guide-to-educating-parents' => 'Bacterial, viral or allergic? A guide for educating parents on childhood conjunctivitis: distinguishing the causes, treatment and when to return.',
			'prepare-for-a-big-cold-and-flu-season' => 'Cold and flu cases are up and flu vaccination rates are down. How to prepare your practice to offer symptomatic relief for nasal congestion this winter.',
			'from-chocolate-to-clinical-clues' => 'How the viral Bristol Stool Chart cake is opening up conversations about bowel habits, and how GPs can use the moment to talk rectal health.',
			'can-you-spot-the-allergy-eye' => 'Not all red eyes are alike. Test yourself on distinguishing allergic conjunctivitis from viral and bacterial causes, with practical tips for GPs.',
			'keeping-up-with-clinical-trials-arise' => 'The ARISE trial in allergic rhinitis: what it tested, what it found and what it means for the six million Australians living with hay fever.',
			'top-4-expert-tips-to-optimise-allergic-rhinitis-management-with-dr-jessica-tattersall' => 'Allergist and medical rhinologist Dr Jessica Tattersall shares four practical, guideline-based tips for GPs managing allergic rhinitis.',
			'how-gps-can-support-gi-tolerability-for-patients-taking-anti-obesity-medications' => 'GLP-1 prescriptions are rising fast. Practical strategies for GPs to help patients manage the common gastrointestinal side effects.',
			'sip-to-stand-why-hydration-is-essential-in-pots' => 'POTS diagnoses are climbing post-COVID. Why fluid and electrolyte management is a first-line intervention, and how to guide patients on getting it right.',
			'travellers-diarrhoea-quick-management-guide-for-the-holiday-season' => "A quick management guide to traveller's diarrhoea for the holiday season: prevention advice, rehydration and red flags that warrant escalation.",
			'what-gps-need-to-know-how-the-mist-trial-is-changing-osdb-management-in-children' => 'The MIST+ trial supports saline as first-line management in childhood obstructive sleep-disordered breathing. What changes for GP practice.',
			'rethink-your-approach-to-paediatric-urtis' => 'Paediatrician Dr Jonny Taitz on handling recurrent childhood URTIs: managing parent expectations, reassurance and when to investigate further.',
			'guess-the-glucose-challenge' => 'Think oral rehydration solutions are too sugary for patients with diabetes? Take the interactive challenge and see how ORS stack up against everyday drinks.',
		];

		$applied = [];
		$missing = [];

		// Static front page: Rank Math's homepage_title option only applies to
		// a latest-posts front page, so the meta must live on the page itself.
		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			update_post_meta( $front, 'rank_math_title', 'HCP Care Connect Portal | Clinical Resources for Healthcare Professionals' );
			update_post_meta( $front, 'rank_math_description', 'Free clinical education, in-consultation tools and product samples for Australian GPs, pharmacists and nurses. Register with your AHPRA number for access.' );
			$applied[] = 'front-page';
		}

		foreach ( $pages as $slug => [ $title, $description ] ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				$missing[] = "page:{$slug}";
				continue;
			}
			update_post_meta( $page->ID, 'rank_math_title', $title );
			update_post_meta( $page->ID, 'rank_math_description', $description );
			$applied[] = $slug;
		}

		foreach ( $posts as $slug => $description ) {
			$found = get_posts( [ 'name' => $slug, 'post_type' => 'post', 'numberposts' => 1, 'fields' => 'ids' ] );
			if ( ! $found ) {
				$missing[] = "post:{$slug}";
				continue;
			}
			update_post_meta( $found[0], 'rank_math_description', $description );
			$applied[] = $slug;
		}

		if ( $missing ) {
			throw new \RuntimeException( 'Applied ' . count( $applied ) . ' but missing: ' . implode( ', ', $missing ) );
		}

		return 'Meta applied to ' . count( $applied ) . ' pages/posts.';
	},
];

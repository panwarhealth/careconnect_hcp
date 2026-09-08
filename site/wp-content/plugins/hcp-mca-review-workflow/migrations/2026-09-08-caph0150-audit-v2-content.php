<?php
/**
 * CAPH0150: apply the reviewer's amendments to the 2026 Clinical Audit.
 *
 * Source: "Full Course Capture v2_KM 20.08.2026" mark-up and the
 * "Audit Copy_AMENDS_v1.1" copy doc.
 *
 *  - Course page copy and CPD hours (3.0 MO + 2.0 RP).
 *  - Activity homepage / landing page audit card and hours table.
 *  - Step 1A: criteria reordered (timeframe, demographics, diagnosis).
 *  - Step 1B: copy, dynamic counts and auto-generated prevalence, age and
 *    sex statements (JS in assets/js/audit-v2.js; values kept in hidden fields).
 *  - Step 2A/2B: prospective analysis replaced by three case study
 *    assessments with "check" feedback and a self-rating review.
 *  - Step 3: protocol questions reworded.
 *  - Evaluation form header and learning outcome 2.
 *
 * Runs against the 2026 variant only, resolved by form key. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hcp_v2_field_id' ) ) {
	/**
	 * @param string $key Field key on the 2026 form (already prefixed for copies).
	 */
	function hcp_v2_field_id( string $key ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT f.id FROM {$wpdb->prefix}frm_fields f
			 JOIN {$wpdb->prefix}frm_forms fr ON fr.id = f.form_id
			 WHERE f.field_key = %s LIMIT 1",
			$key
		) );
	}
}

if ( ! function_exists( 'hcp_v2_k' ) ) {
	/** Key of the 2026 copy of a legacy field. */
	function hcp_v2_k( string $legacy_key ): string {
		return HCP_MCA_V2_FIELD_KEY_PREFIX . $legacy_key;
	}
}

if ( ! function_exists( 'hcp_v2_update_field' ) ) {
	/**
	 * Update columns and/or merge field_options on a field addressed by key.
	 */
	function hcp_v2_update_field( string $key, array $values, array $options_merge = [], array $options_unset = [] ): void {
		$id = hcp_v2_field_id( $key );
		if ( ! $id ) {
			throw new \RuntimeException( "field {$key} not found" );
		}
		if ( $options_merge || $options_unset ) {
			$field = FrmField::getOne( $id );
			$opts  = (array) $field->field_options;
			foreach ( $options_unset as $k ) {
				unset( $opts[ $k ] );
			}
			$values['field_options'] = array_merge( $opts, $options_merge );
		}
		FrmField::update( $id, $values );
	}
}

if ( ! function_exists( 'hcp_v2_delete_fields' ) ) {
	/** Delete the 2026 copies of the given legacy keys, if present. */
	function hcp_v2_delete_fields( array $legacy_keys ): int {
		$n = 0;
		foreach ( $legacy_keys as $legacy ) {
			$id = hcp_v2_field_id( hcp_v2_k( $legacy ) );
			if ( $id ) {
				FrmField::destroy( $id );
				$n++;
			}
		}
		return $n;
	}
}

if ( ! function_exists( 'hcp_v2_add_field' ) ) {
	/**
	 * Create (or update, when the key exists) a field on the 2026 form.
	 *
	 * @return int Field id.
	 */
	function hcp_v2_add_field( int $form_id, string $type, string $key, int $order, array $args ): int {
		$values = FrmFieldsHelper::setup_new_vars( $type, $form_id );

		$values['field_key']   = $key;
		$values['field_order'] = $order;
		$values['name']        = $args['name'] ?? '';
		$values['description'] = $args['description'] ?? '';
		$values['required']    = ! empty( $args['required'] ) ? 1 : 0;
		$values['options']     = $args['options'] ?? $values['options'];

		$values['field_options'] = array_merge(
			(array) $values['field_options'],
			[
				'align'                    => $args['align'] ?? 'block',
				'label'                    => $args['label'] ?? 'top',
				'classes'                  => $args['classes'] ?? '',
				'enable_conditional_logic' => '',
				'hide_field'               => [],
				'hide_field_cond'          => [],
				'hide_opt'                 => [],
				'show_hide'                => 'show',
				'any_all'                  => 'any',
				'blank'                    => 'This field cannot be blank.',
			],
			$args['field_options'] ?? []
		);
		if ( ! empty( $args['in_section'] ) ) {
			$values['field_options']['in_section'] = (int) $args['in_section'];
		}
		if ( ! empty( $args['show_when'] ) ) {
			[ $parent_id, $option ] = $args['show_when'];
			$values['field_options']['enable_conditional_logic'] = '1';
			$values['field_options']['hide_field']               = [ (int) $parent_id ];
			$values['field_options']['hide_field_cond']          = [ '==' ];
			$values['field_options']['hide_opt']                 = [ $option ];
		}

		$existing = hcp_v2_field_id( $key );
		if ( $existing ) {
			unset( $values['field_key'], $values['form_id'] );
			FrmField::update( $existing, $values );
			return $existing;
		}

		$id = FrmField::create( $values );
		if ( ! $id ) {
			throw new \RuntimeException( "could not create field {$key}" );
		}
		return (int) $id;
	}
}

if ( ! function_exists( 'hcp_v2_choices' ) ) {
	/**
	 * Formidable choice options. Strings prefixed with "other:" become an
	 * "Other – please specify" input.
	 */
	function hcp_v2_choices( array $labels ): array {
		$opts = [];
		foreach ( $labels as $i => $label ) {
			if ( 0 === strpos( $label, 'other:' ) ) {
				$opts[ 'other_' . $i ] = substr( $label, 6 );
			} else {
				$opts[ $i ] = [ 'label' => $label, 'value' => $label, 'image' => '0', 'limit' => '' ];
			}
		}
		return $opts;
	}
}

if ( ! function_exists( 'hcp_v2_has_other' ) ) {
	function hcp_v2_has_other( array $options ): bool {
		foreach ( array_keys( $options ) as $k ) {
			if ( 0 === strpos( (string) $k, 'other_' ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'hcp_v2_case_table' ) ) {
	/**
	 * Patient information block: a titled table of label / content rows.
	 *
	 * @param array<string, string|string[]> $rows Label => paragraph or list of bullets.
	 */
	function hcp_v2_case_table( string $title, array $rows ): string {
		$html = '<div class="hcp-case"><div class="hcp-case__title">' . esc_html( $title ) . '</div>';
		foreach ( $rows as $label => $content ) {
			$html .= '<div class="hcp-case__row"><div class="hcp-case__label">' . esc_html( $label ) . '</div><div class="hcp-case__body">';
			if ( is_array( $content ) ) {
				$html .= '<ul>';
				foreach ( $content as $item ) {
					$html .= '<li>' . esc_html( $item ) . '</li>';
				}
				$html .= '</ul>';
			} else {
				$html .= '<p>' . esc_html( $content ) . '</p>';
			}
			$html .= '</div></div>';
		}
		return $html . '</div>';
	}
}

if ( ! function_exists( 'hcp_v2_check_html' ) ) {
	/**
	 * A "Check" button with the feedback copy revealed once the question is
	 * answered. $field_keys are the question fields the button validates.
	 */
	function hcp_v2_check_html( array $field_keys, string $feedback ): string {
		// Question keys travel as classes: data-* attributes are stripped when
		// Formidable saves a field description.
		$classes = 'hcp-check';
		foreach ( $field_keys as $key ) {
			$classes .= ' hcp-check--' . $key;
		}
		return '<div class="' . esc_attr( $classes ) . '">'
			. '<button type="button" class="btn cta hcp-check__btn">Check</button>'
			. '<p class="hcp-check__prompt">Please answer the question above before checking.</p>'
			. '<div class="hcp-check__feedback"><p>' . esc_html( $feedback ) . '</p></div>'
			. '</div>';
	}
}

if ( ! function_exists( 'hcp_v2_cpd_table' ) ) {
	function hcp_v2_cpd_table( string $category, string $timing ): string {
		return '<div class="grid md:grid-cols-2 border border-stroke md:divide-x divide-stroke">'
			. '<div class="p-4"><strong>CPD category:</strong> ' . esc_html( $category ) . '</div>'
			. '<div class="p-4"><strong>Timing:</strong> ' . esc_html( $timing ) . '</div>'
			. '</div>';
	}
}

if ( ! function_exists( 'hcp_v2_replace_in_post' ) ) {
	/**
	 * Ordered string replacements in a post's content. Each pair must match
	 * exactly once unless the replacement is already present.
	 */
	function hcp_v2_replace_in_post( int $post_id, array $pairs, array &$notes ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \RuntimeException( "post {$post_id} not found" );
		}
		$content = $post->post_content;
		$hits    = 0;
		foreach ( $pairs as $old => $new ) {
			if ( false !== strpos( $content, $new ) ) {
				continue;
			}
			$count = substr_count( $content, $old );
			if ( 1 !== $count ) {
				throw new \RuntimeException( "post {$post_id}: expected 1 match for '" . mb_substr( $old, 0, 50 ) . "', found {$count}" );
			}
			$content = str_replace( $old, $new, $content );
			$hits++;
		}
		if ( ! $hits ) {
			return;
		}
		kses_remove_filters();
		$result = wp_update_post( [ 'ID' => $post_id, 'post_content' => $content ], true );
		kses_init_filters();
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( "post {$post_id}: " . $result->get_error_message() );
		}
		$notes[] = "post {$post_id}: {$hits} replacements";
	}
}

if ( ! function_exists( 'hcp_v2_case_study_options' ) ) {
	function hcp_v2_case_study_options(): array {
		return [
			'risk'          => hcp_v2_choices( [
				'Constipation',
				'Prolonged diarrhoea',
				'Vaginal delivery',
				'Anal penetration/injury',
				'Obesity',
				'Hypothyroidism',
				'Crohn’s disease',
				'Sexually transmitted infection',
				'Granulomatous disease',
				'Previous anal surgery',
				'Anal or colon cancer',
				'Use of nicorandil',
				'other:Other – please specify:',
			] ),
			'investigation' => hcp_v2_choices( [
				'Bowel habit history',
				'Visual anal examination',
				'Digital rectal examination',
				'Anoscopy',
				'Endoanal ultrasound',
				'other:Other – please specify:',
			] ),
			'diagnosis'     => hcp_v2_choices( [
				'Acute primary anal fissure',
				'Chronic primary anal fissure',
				'Secondary anal fissure',
				'other:Other – please specify:',
			] ),
			'intervention'  => hcp_v2_choices( [
				'Conservative therapy to relieve constipation and/or pain',
				'Pharmacological treatment',
				'Referral for further investigations and/or surgical management',
				'Education on the condition and/or recommended management plan',
				'Other',
			] ),
			'follow_up'     => hcp_v2_choices( [
				'No further treatment required – preventive measures only (i.e., diet/lifestyle modifications)',
				'Conservative therapy',
				'Pharmacological management',
				'Referral for further investigations and/or surgical management',
				'Other',
			] ),
		];
	}
}

if ( ! function_exists( 'hcp_v2_detail_prompts' ) ) {
	/** Free-text prompt shown under each intervention / follow-up choice. */
	function hcp_v2_detail_prompts(): array {
		return [
			'Conservative therapy to relieve constipation and/or pain'                                 => 'Provide details about which conservative therapies you would recommend:',
			'Pharmacological treatment'                                                                => 'Provide details about which medications you would recommend, including dosage and duration:',
			'Referral for further investigations and/or surgical management'                          => 'Specify which specialist(s) you would refer to and why:',
			'Education on the condition and/or recommended management plan'                           => 'Provide critical education and counselling points you would cover with this patient:',
			'Other'                                                                                    => 'Other – please specify and provide your rationale:',
			'No further treatment required – preventive measures only (i.e., diet/lifestyle modifications)' => 'Provide details about what preventative measures you would recommend:',
			'Conservative therapy'                                                                     => 'Provide details about which conservative therapies you would recommend:',
			'Pharmacological management'                                                               => 'Provide details about which medications you would recommend, including dosage and duration:',
		];
	}
}

if ( ! function_exists( 'hcp_v2_case_studies' ) ) {
	/**
	 * The three case studies from the copy doc. Each step is a list of
	 * [type, args] entries consumed by hcp_v2_build_case_study().
	 */
	function hcp_v2_case_studies(): array {
		$assess_fb = 'Appropriate investigations to conduct during this consultation include taking a bowel habit history and performing a visual anal examination. Note that digital rectal examination is contraindicated for suspected anal fissure, and imaging is usually only considered if the diagnosis is unclear after examination.';
		$review_fb = 'An appropriate follow-up interval for this patient is 4 weeks.';

		return [
			1 => [
				'presentation' => hcp_v2_case_table( 'Patient 1 – Presentation', [
					'Presentation'     => 'A 31-year-old woman (BMI 28.5 kg/m2) presents with a 6-day history of severe anal pain described as ‘like passing broken glass’ during defecation, persisting for up to 2 hours after defecation, with a small amount of bright red blood on wiping.',
					'Clinical history' => [
						'Recent constipation after travelling – straining to pass hard stools',
						'Has been using topical lidocaine (recommended by a pharmacist) for 2 days with limited pain relief',
						'No recent weight loss, diarrhoea, abdominal pain, or fever',
						'No personal or family history of colorectal cancer or bowel disease',
					],
				] ),
				'risk_fb'      => 'Risk factors for this patient include constipation and obesity.',
				'assess_fb'    => $assess_fb,
				'assessment'   => hcp_v2_case_table( 'Patient 1 – Assessment', [
					'Visual anal examination' => [
						'Fresh, superficial laceration with clean edges in anal posterior midline',
						'No sentinel skin tag, haemorrhoids, or bleeding/discharge',
					],
				] ),
				'diagnosis_fb' => 'Based on her symptoms of constipation, pain, and bleeding, and the presence of a fresh posterior midline laceration on examination, this patient likely has an acute primary anal fissure.',
				'intervene_fb' => 'The most appropriate interventions for this patient at this consultation are incorporating conservative therapies, initiating pharmacological treatment with topical glyceryl trinitrate, and providing patient education.',
				'review_fb'    => $review_fb,
				'management'   => hcp_v2_case_table( 'Patient 1 – Management and follow-up', [
					'Recommended interventions' => 'Conservative management with increased dietary fibre and fluid intake, stool softeners, and sitz baths, and initiating topical glyceryl trinitrate 0.2% (1.0–1.5 cm strip, 3 times daily) for 4 weeks.',
					'Follow-up call'            => 'Two days after starting the glyceryl nitrate, the patient calls to discuss the headaches and dizziness she experiences after applying the ointment.',
				] ),
				'side_effects' => [
					'question' => '(F) Based on your patient’s description of side effects with glyceryl trinitrate treatment, what would you advise?',
					'options'  => hcp_v2_choices( [
						'Continue with application as initially prescribed',
						'Dose reduction with gradual titration to full dose',
						'Discontinue treatment',
						'other:Other – please specify:',
					] ),
					'fb'       => 'To help mitigate headaches and dizziness after application of glyceryl trinitrate and support continuation on the medication, it would be appropriate to recommend that this patient reduces the dose and gradually titrates back up to the full dose over 1 to 2 weeks as tolerated.',
				],
				'management2'  => hcp_v2_case_table( 'Patient 1 – Management and follow-up', [
					'Recommended interventions' => 'Continue topical glyceryl trinitrate 0.2% at a reduced dose after pausing use for 8 hours. Follow a gradual titration, beginning with 0.5 cm strip for 3–4 days, increasing to 1.0 cm for 3–4 days, then to 1.5 cm once tolerated. Continue conservative management interventions as previously described.',
					'Follow-up call'            => 'After 1 week of glyceryl trinitrate titration, the patient reports resolution of application-related headaches and a reduction in dizziness.',
					'Follow-up review'          => 'After 4 weeks of conservative management and glyceryl trinitrate, the patient reports a return to normal bowel movements and minimal pain.',
				] ),
				'outcome_q'    => '(G) Based on your patient’s outcomes at their follow-up review, what would you now recommend?',
				'outcome_fb'   => 'Based on the patient’s resolution of symptoms with conservative therapies and 4 weeks of glyceryl trinitrate, it would be appropriate to recommend no further treatment at this point but advise on preventive measures and lifestyle modifications to help reduce the likelihood of constipation.',
			],
			2 => [
				'presentation' => hcp_v2_case_table( 'Patient 2 – Presentation', [
					'Presentation'     => 'A 47-year-old man (BMI 24.9 kg/m2) presents with a regular anal pain during and after defecation and intermittent rectal bleeding over the past 6 weeks.',
					'Clinical history' => [
						'Persistent symptoms despite increased fibre intake',
						'Fear of pain with defecation resulting in bowel movement avoidance and worsening constipation',
						'Also reports avoidance of intimacy with his male partner due to his ongoing symptoms',
						'No symptoms suggestive of inflammatory bowel disease or malignancy',
						'Surgical haemorrhoid removal 3 years prior',
						'No personal or family history of colorectal cancer or bowel disease',
					],
				] ),
				'risk_fb'      => 'Risk factors for this patient include constipation, previous anal surgery, and anal penetration.',
				'assess_fb'    => $assess_fb,
				'assessment'   => hcp_v2_case_table( 'Patient 2 – Assessment', [
					'Visual anal examination' => [
						'Posterior midline fissure with mildly raised edges',
						'Sentinel skin tag present',
						'Exposed internal anal sphincter fibres visible',
					],
				] ),
				'diagnosis_fb' => 'Based on his symptoms of constipation, pain, and bleeding that have persisted for >6 weeks, in the presence of a posterior midline fissure with raised edges, a sentinel skin tag, and exposed internal anal sphincter fibres on examination, this patient likely has a chronic primary anal fissure.',
				'intervene_fb' => 'The most appropriate interventions for this patient at this consultation are incorporating conservative therapies, initiating pharmacological treatment with topical glyceryl trinitrate, and providing patient education.',
				'review_fb'    => $review_fb,
				'management'   => hcp_v2_case_table( 'Patient 2 – Management', [
					'Recommended interventions' => 'Continued conservative management with increased dietary fibre and fluid intake, stool softeners, and sitz baths, plus initiating pharmacological treatment with glyceryl trinitrate 0.2% (1.0–1.5 cm strip, 3 times daily) for 4 weeks.',
					'Follow-up review'          => 'After 4 weeks, the patient reports improved pain and reduced constipation. Visual anal examination shows persistent fissure with moderate healing.',
				] ),
				'outcome_q'    => '(F) Based on your patient’s outcomes at their follow-up review, what would you now recommend?',
				'outcome_fb'   => 'Based on the patient’s improvement in symptoms and moderate tissue healing with conservative therapies and glyceryl trinitrate, it would be appropriate to recommend continuation of conservative therapy plus glyceryl trinitrate treatment for a further 4 weeks and then return for further review. Reinforcing education on diet and lifestyle measures to help reduce the likelihood of constipation would also be appropriate.',
			],
			3 => [
				'presentation' => hcp_v2_case_table( 'Patient 3 – Presentation', [
					'Presentation'     => 'A 57-year-old woman presents with a 2-month history of severe anal pain during defecation despite treatment.',
					'Clinical history' => [
						'Persistent pain and rectal bleeding despite adherence to fibre supplements, stool softeners, sitz baths, and 4 weeks of topical glyceryl trinitrate',
						'Mild constipation but no diarrhoea or symptoms suggestive of inflammatory bowel disease or colorectal cancer',
						'Medications include levothyroxine 100 µg/day',
						'Ongoing symptoms are causing distress and the patient wonders if she may need to have surgery to fix it',
					],
				] ),
				'risk_fb'      => 'Risk factors for this patient include constipation and hypothyroidism.',
				'assess_fb'    => $assess_fb,
				'assessment'   => hcp_v2_case_table( 'Patient 3 – Assessment', [
					'Visual anal examination' => 'Posterior midline fissure with thickened, raised edges and mild ulceration and sentinel skin tag present.',
				] ),
				'diagnosis_fb' => 'Based on her symptoms of constipation, pain, and bleeding that have persisted for >6 weeks, and combined with the presence of a posterior midline fissure with thickened raised edges, ulceration, and a sentinel skin tag on examination, this patient likely has a chronic primary anal fissure.',
				'intervene_fb' => 'The most appropriate interventions for this patient at this consultation are continuing conservative therapies and pharmacological treatment with topical glyceryl trinitrate, and providing patient education. Referral to a colorectal surgeon for further investigations and review for procedural management may be considered at this point.',
				'review_fb'    => $review_fb,
				'management'   => hcp_v2_case_table( 'Patient 3 – Management', [
					'Recommended interventions' => [
						'Continued conservative management with increased dietary fibre and fluid intake, stool softeners, and sitz baths, and continue pharmacological treatment with glyceryl trinitrate 0.2% (1.0–1.5 cm strip, 3 times daily) for a further 4 weeks.',
						'Based on the patient’s concerns and potential lead time required to get an appointment, referral to a colorectal surgeon for further investigations and review for potential procedural management are also provided at this point.',
					],
					'Follow-up review'          => 'After 4 weeks, the patient reports persistent rectal bleeding with only a mild improvement in pain during defecation. Visual anal examination shows persistent fissure with little improvement in ulceration. She has a consultation with the colorectal surgeon within the next week.',
				] ),
				'outcome_q'    => '(F) Based on your patient’s outcomes at their follow-up review, what would you now recommend?',
				'outcome_fb'   => 'Based on the patient’s continued symptoms and persistent fissure with conservative therapies and 8 weeks of glyceryl trinitrate, a consultation with a colorectal surgeon for further investigations and consideration for possible procedural management is most appropriate at this time.',
			],
		];
	}
}

if ( ! function_exists( 'hcp_v2_build_case_study' ) ) {
	/**
	 * Create the fields for one case study. $order is advanced in place.
	 */
	function hcp_v2_build_case_study( int $form_id, int $n, array $cs, int &$order ): void {
		$opts    = hcp_v2_case_study_options();
		$prompts = hcp_v2_detail_prompts();
		$k       = "v2-cs{$n}-";
		$add     = function ( string $type, string $suffix, array $args ) use ( $form_id, $k, &$order ) {
			return hcp_v2_add_field( $form_id, $type, $k . $suffix, $order++, $args );
		};
		$choice_group = function ( string $suffix, string $question, array $options, string $feedback, string $type = 'checkbox' ) use ( $add, $k, $prompts ) {
			$args = [
				'name'     => $question,
				'options'  => $options,
				'required' => true,
				'classes'  => 'indent-choices',
			];
			if ( hcp_v2_has_other( $options ) ) {
				$args['field_options'] = [ 'other' => '1' ];
			}
			$id = $add( $type, $suffix, $args );
			return $id;
		};

		$section = $add( 'divider', 'section', [ 'name' => "Patient {$n}", 'classes' => 'hcp-cs' ] );

		$add( 'html', 'presentation', [ 'description' => $cs['presentation'], 'in_section' => $section ] );

		// (A) Risk factors.
		$risk = $choice_group( 'risk', '(A) What risk factors for anal fissures are present? Select all that apply.', $opts['risk'], $cs['risk_fb'] );
		$add( 'html', 'risk-check', [ 'description' => hcp_v2_check_html( [ $k . 'risk' ], $cs['risk_fb'] ), 'in_section' => $section ] );

		// (B) Investigations.
		$choice_group( 'assess', '(B) What assessments or investigations would you conduct during your initial consultation? Select all that apply.', $opts['investigation'], $cs['assess_fb'] );
		$add( 'html', 'assess-check', [ 'description' => hcp_v2_check_html( [ $k . 'assess' ], $cs['assess_fb'] ), 'in_section' => $section ] );

		$add( 'html', 'assessment', [ 'description' => $cs['assessment'], 'in_section' => $section ] );

		// (C) Diagnosis.
		$choice_group( 'diagnosis', '(C) What is your diagnosis?', $opts['diagnosis'], $cs['diagnosis_fb'], 'radio' );
		$add( 'textarea', 'diagnosis-why', [ 'name' => 'What clinical findings support your diagnosis?', 'required' => true, 'classes' => 'hcp-cs__detail', 'in_section' => $section ] );
		$add( 'html', 'diagnosis-check', [ 'description' => hcp_v2_check_html( [ $k . 'diagnosis', $k . 'diagnosis-why' ], $cs['diagnosis_fb'] ), 'in_section' => $section ] );

		// (D) Interventions, each with a detail prompt.
		$intervene = $choice_group( 'intervene', '(D) What interventions would you recommend or initiate at this point? Select all that apply.', $opts['intervention'], $cs['intervene_fb'] );
		$i = 0;
		foreach ( $opts['intervention'] as $opt ) {
			$add( 'textarea', 'intervene-' . ++$i, [
				'name'       => $prompts[ $opt['value'] ],
				'required'   => true,
				'classes'    => 'hcp-cs__detail',
				'in_section' => $section,
				'show_when'  => [ $intervene, $opt['value'] ],
			] );
		}
		$add( 'html', 'intervene-check', [ 'description' => hcp_v2_check_html( [ $k . 'intervene' ], $cs['intervene_fb'] ), 'in_section' => $section ] );

		// (E) Review interval.
		$add( 'textarea', 'review', [ 'name' => '(E) When would you ask the patient to return for review?', 'required' => true, 'in_section' => $section ] );
		$add( 'html', 'review-check', [ 'description' => hcp_v2_check_html( [ $k . 'review' ], $cs['review_fb'] ), 'in_section' => $section ] );

		$add( 'html', 'management', [ 'description' => $cs['management'], 'in_section' => $section ] );

		// Patient 1 only: side effects question and second management block.
		if ( ! empty( $cs['side_effects'] ) ) {
			$se = $cs['side_effects'];
			$choice_group( 'side-effects', $se['question'], $se['options'], $se['fb'], 'radio' );
			$add( 'textarea', 'side-effects-why', [ 'name' => 'What is your rationale for your recommendation?', 'required' => true, 'classes' => 'hcp-cs__detail', 'in_section' => $section ] );
			$add( 'html', 'side-effects-check', [ 'description' => hcp_v2_check_html( [ $k . 'side-effects', $k . 'side-effects-why' ], $se['fb'] ), 'in_section' => $section ] );
			$add( 'html', 'management2', [ 'description' => $cs['management2'], 'in_section' => $section ] );
		}

		// Outcome at follow-up, each with a detail prompt.
		$outcome = $choice_group( 'outcome', $cs['outcome_q'], $opts['follow_up'], $cs['outcome_fb'] );
		$i = 0;
		foreach ( $opts['follow_up'] as $opt ) {
			$add( 'textarea', 'outcome-' . ++$i, [
				'name'       => $prompts[ $opt['value'] ],
				'required'   => true,
				'classes'    => 'hcp-cs__detail',
				'in_section' => $section,
				'show_when'  => [ $outcome, $opt['value'] ],
			] );
		}
		$add( 'html', 'outcome-check', [ 'description' => hcp_v2_check_html( [ $k . 'outcome' ], $cs['outcome_fb'] ), 'in_section' => $section ] );

		$add( 'end_divider', 'section-end', [ 'name' => 'Section Buttons' ] );
	}
}

return [
	'description' => 'CAPH0150: reviewer amendments to the 2026 Clinical Audit (Step 1A/1B/2A/2B/3 content, course and homepage copy, evaluation form).',

	'up' => function (): string {
		global $wpdb;

		$variant = hcp_mca_variants( true )[ HCP_MCA_VARIANT_V2 ] ?? null;
		if ( ! $variant ) {
			throw new \RuntimeException( 'the 2026 variant is not provisioned; run the provisioning migration first' );
		}
		$form_id = (int) $variant['audit_form'];
		$notes   = [];

		// ------------------------------------------------------------------
		// Step 1A: criteria reordered.
		// ------------------------------------------------------------------
		hcp_v2_update_field( hcp_v2_k( 'due9j' ), [ 'name' => '(A) Timeframe – diagnosis within the past (select one option):' ] );
		hcp_v2_update_field( hcp_v2_k( 'w1fj3' ), [ 'name' => '(B) Demographic or clinical parameters (input as many as desired):' ] );
		hcp_v2_update_field( hcp_v2_k( 'kkxa8' ), [ 'name' => '(C) Diagnosis:', 'field_order' => 24 ] );
		$notes[] = 'step 1A';

		// ------------------------------------------------------------------
		// Step 1B: copy, dynamic counts, automated statements.
		// ------------------------------------------------------------------
		hcp_v2_update_field( hcp_v2_k( 'p9mkv' ), [
			'description' => '<em>Find your total patient cohort by using the timeframe and other demographic/clinical criteria you selected in Step 1A:</em>'
				. '<ul class="hcp-criteria" aria-live="polite"></ul>',
		] );
		hcp_v2_update_field( hcp_v2_k( 'khh7w' ), [ 'name' => 'Record the total number of patients identified' ] );
		hcp_v2_update_field( hcp_v2_k( '8eger' ), [ 'description' => '<em>From this total patient cohort, now identify those who were diagnosed with anal fissure.</em>' ] );
		hcp_v2_update_field( hcp_v2_k( '9962s' ), [ 'name' => 'Record the number' ] );
		hcp_v2_update_field( hcp_v2_k( '3zl70' ), [ 'name' => 'The prevalence of anal fissure in your patient cohort (i.e., proportion of the total number who were diagnosed with anal fissure) is:' ] );

		$count_copy = static fn( string $what ): string =>
			'<p><em>Out of the <span class="hcp-diag-count">number of</span> patients diagnosed with anal fissure, enter the number of patients relevant for each ' . $what . ' listed below. <br />If no patients are relevant, enter the number zero (0).</em></p>';
		hcp_v2_update_field( hcp_v2_k( 'zl5bk' ), [ 'description' => $count_copy( 'characteristic' ) ] );
		hcp_v2_update_field( hcp_v2_k( 'z5gyk' ), [ 'description' => $count_copy( 'risk factor' ) ] );
		hcp_v2_update_field( hcp_v2_k( 'vpcpm' ), [ 'description' => $count_copy( 'management step' ) ] );
		hcp_v2_update_field( hcp_v2_k( 'a68dp' ), [ 'description' => $count_copy( 'outcome' ) ] );

		// Copied quirk: one count field sat in a section that no longer exists.
		hcp_v2_update_field( hcp_v2_k( 'nnv4f' ), [], [ 'in_section' => hcp_v2_field_id( hcp_v2_k( '5el69' ) ) ] );

		// (D) Trends: the three questions become generated statements.
		$reflect  = hcp_v2_field_id( hcp_v2_k( '2kqbe' ) );
		$no_logic = [ 'enable_conditional_logic' => '', 'hide_field' => [], 'hide_field_cond' => [], 'hide_opt' => [] ];

		hcp_v2_update_field( hcp_v2_k( '77l5c' ), [
			'description' => '<p class="hcp-auto-statement hcp-auto-prevalence">The prevalence of anal fissure in your patient cohort is <strong>…</strong> than general estimates of 10% to 15%.</p>',
		], [ 'classes' => 'frm12 frm_first' ] );
		hcp_v2_delete_fields( [ 'lcuyw' ] );
		hcp_v2_update_field( hcp_v2_k( '1qkix' ), [ 'description' => '<strong>If it differs, what do you believe drives the difference?</strong>' ], [ 'classes' => 'frm12 frm_first' ] );
		hcp_v2_update_field( hcp_v2_k( '5kvqb' ), [], [ 'classes' => 'frm12' ] );

		hcp_v2_update_field( hcp_v2_k( 'ct48d' ), [
			'description' => '<p class="hcp-auto-statement hcp-auto-age">Among your patient cohort, anal fissures <strong>…</strong>.</p>',
		], [ 'classes' => 'frm12 frm_first' ] );
		hcp_v2_delete_fields( [ '9x66l', 'r8mxf', '3eocr', 'hep9a' ] );
		hcp_v2_update_field( hcp_v2_k( 'c77y6' ), [ 'description' => '<strong>If there is an age-related predominance, what do you believe drives this?</strong>' ], [ 'classes' => 'frm12 frm_first' ] + $no_logic );
		hcp_v2_update_field( hcp_v2_k( '35byr' ), [ 'required' => 0 ], [ 'classes' => 'frm12' ] + $no_logic );

		hcp_v2_update_field( hcp_v2_k( 'ny2zl' ), [
			'description' => '<p class="hcp-auto-statement hcp-auto-sex">Among your patient cohort, anal fissures <strong>…</strong>.</p>',
		], [ 'classes' => 'frm12 frm_first' ] );
		hcp_v2_delete_fields( [ 'kgla', 'v991u', 'y9uno', 'ywfp' ] );
		hcp_v2_update_field( hcp_v2_k( '62pcc' ), [ 'description' => '<strong>If there is a sex-related predominance, what do you believe drives this?</strong>' ], [ 'classes' => 'frm12 frm_first' ] + $no_logic );
		hcp_v2_update_field( hcp_v2_k( 'le2za' ), [ 'required' => 0 ], [ 'classes' => 'frm12' ] + $no_logic );

		// Generated statements are kept on the entry for the reviewer.
		hcp_v2_add_field( $form_id, 'hidden', 'v2-auto-prevalence', 212, [ 'name' => 'Prevalence compared with general estimates (generated)', 'in_section' => $reflect ] );
		hcp_v2_add_field( $form_id, 'hidden', 'v2-auto-age', 218, [ 'name' => 'Age-related predominance (generated)', 'in_section' => $reflect ] );
		hcp_v2_add_field( $form_id, 'hidden', 'v2-auto-sex', 229, [ 'name' => 'Sex-related predominance (generated)', 'in_section' => $reflect ] );
		$notes[] = 'step 1B';

		// ------------------------------------------------------------------
		// Step 2A: case study assessments replace the prospective analysis.
		// ------------------------------------------------------------------
		$legacy_2a_2b = $wpdb->get_col( $wpdb->prepare(
			"SELECT field_key FROM {$wpdb->prefix}frm_fields WHERE form_id = %d AND ((field_order BETWEEN 389 AND 535) OR (field_order BETWEEN 539 AND 589))",
			HCP_MCA_AUDIT_FORM_ID
		) );
		$deleted = hcp_v2_delete_fields( $legacy_2a_2b );

		$order = 389;
		hcp_v2_add_field( $form_id, 'html', 'v2-2a-header', $order++, [
			'description' => '<h2>STEP 2A<br />CASE STUDY ASSESSMENT</h2>'
				. '<p>In Step 2, you will assess your approach to managing patients with suspected anal fissure by reviewing three hypothetical patient cases. You will then reflect on your responses relative to best clinical practice standards, and your historical management practices.</p>'
				. hcp_v2_cpd_table( 'Reviewing Performance', 'Approximately 1.5 hours' ),
		] );
		hcp_v2_add_field( $form_id, 'html', 'v2-2a-intro', $order++, [
			'description' => '<h4>Consider evidence-based assessment and management practices to complete each of the case studies below. Review the patient information and answer the questions.</h4>',
			'classes'     => 'border-t pt-md border-dark',
		] );
		foreach ( hcp_v2_case_studies() as $n => $cs ) {
			hcp_v2_build_case_study( $form_id, $n, $cs, $order );
		}
		$notes[] = "step 2A ({$deleted} legacy fields removed, next order {$order})";

		// ------------------------------------------------------------------
		// Step 2B: self-rating review. Existing (B) and (C) questions at
		// orders 591+ are kept.
		// ------------------------------------------------------------------
		$order = 539;
		hcp_v2_add_field( $form_id, 'html', 'v2-2b-header', $order++, [
			'description' => '<h3>STEP 2B<br />CASE STUDY ASSESSMENT: REVIEW YOUR MANAGEMENT</h3>'
				. hcp_v2_cpd_table( 'Reviewing Performance', 'Approximately 0.5 hour' ),
		] );
		hcp_v2_add_field( $form_id, 'html', 'v2-2b-q1', $order++, [
			'description' => '<h4>1. Reflect on your overall responses to the case study questions. For each of the management areas listed below, rate your performance and identify areas where you may need to improve.</h4>',
			'classes'     => 'border-t pt-md border-dark',
		] );
		$rate = hcp_v2_add_field( $form_id, 'divider', 'v2-2b-rate', $order++, [ 'name' => '' ] );
		$ratings = [
			'a' => [ '(A) Did you identify most or all the anal fissure risk factors in the case studies?', 'Describe why you may or may not have recognised certain risk factors for anal fissures.' ],
			'b' => [ '(B) Did you select the appropriate assessments to perform in the case studies?', 'Describe why you may or may not have selected appropriate assessments for patients with suspected anal fissure.' ],
			'c' => [ '(C) Did you select appropriate treatment and/or referral options in the case studies?', 'Describe why you may or may not have considered different management options.' ],
			'd' => [ '(D) When recommending patient education in the case studies, did you consider information about causes of anal fissures, lifestyle and bowel habits to improve constipation, and management options?', 'Describe why you may or may not have included certain patient education points.' ],
			'e' => [ '(E) Did you select appropriate review intervals and next steps at follow-up for the case studies?', 'Describe why you may or may not have selected appropriate follow-up intervals and actions.' ],
		];
		foreach ( $ratings as $letter => [ $question, $why ] ) {
			hcp_v2_add_field( $form_id, 'radio', "v2-2b-rate-{$letter}", $order++, [
				'name'       => $question,
				'options'    => hcp_v2_choices( [ 'Yes', 'No' ] ),
				'required'   => true,
				'align'      => 'inline',
				'classes'    => 'indent-choices',
				'in_section' => $rate,
			] );
			hcp_v2_add_field( $form_id, 'textarea', "v2-2b-rate-{$letter}-why", $order++, [
				'name'       => $why,
				'required'   => true,
				'classes'    => 'hcp-cs__detail',
				'in_section' => $rate,
			] );
		}
		hcp_v2_add_field( $form_id, 'end_divider', 'v2-2b-rate-end', $order++, [ 'name' => 'Section Buttons' ] );

		hcp_v2_add_field( $form_id, 'html', 'v2-2b-q2', $order++, [
			'description' => '<h4>2. Compare your performance managing the patients in the case study assessment with the outcomes of your retrospective analysis.</h4>',
			'classes'     => 'border-t pt-md border-dark',
		] );
		$compare = hcp_v2_add_field( $form_id, 'divider', 'v2-2b-compare', $order++, [
			'name' => '(A) In your retrospective analysis, you identified these three areas as needing the most improvement:',
		] );
		hcp_v2_add_field( $form_id, 'html', 'v2-2b-compare-areas', $order++, [
			'description' => '<ol class="hcp-auto-areas" aria-live="polite"><li class="hcp-auto-areas__empty">Complete Step 1B, Question 3 to see your selected areas here.</li></ol>',
			'in_section'  => $compare,
		] );
		hcp_v2_add_field( $form_id, 'radio', 'v2-2b-compare-align', $order++, [
			'name'       => 'Do these areas align with your self-ratings from the case study assessment completed above?',
			'options'    => hcp_v2_choices( [ 'Yes', 'No' ] ),
			'required'   => true,
			'align'      => 'inline',
			'classes'    => 'indent-choices',
			'in_section' => $compare,
		] );
		hcp_v2_add_field( $form_id, 'textarea', 'v2-2b-compare-why', $order++, [
			'name'       => 'Reflect on any similarities or differences between your historical and hypothetical clinical practice behaviours.',
			'required'   => true,
			'in_section' => $compare,
		] );
		hcp_v2_add_field( $form_id, 'end_divider', 'v2-2b-compare-end', $order++, [ 'name' => 'Section Buttons' ] );
		$notes[] = "step 2B (next order {$order})";

		// ------------------------------------------------------------------
		// Step 3: protocol questions.
		// ------------------------------------------------------------------
		hcp_v2_update_field( hcp_v2_k( 'c7b0f' ), [
			'description' => '<h4>Answer the questions below to identify key steps and instructions to include in your protocol to cover assessment, management, and follow-up of patients with anal fissures.</h4>',
		] );
		$protocol = [
			'g0qmd' => [ 'n7420', '(A) List screening practices and assessments that you will routinely perform to improve identification of anal fissure.' ],
			'gaxi0' => [ 'h2oro', '(B) List key interventions for management with a brief description of when you would recommend each intervention. Consider conservative therapy, pharmacological therapy, patient education, and referral options.' ],
			'fdj9m' => [ '33zhq', '(C) Describe how you will ensure timely follow-up to review patient outcomes.' ],
		];
		foreach ( $protocol as $divider => [ $textarea, $question ] ) {
			hcp_v2_update_field( hcp_v2_k( $divider ), [ 'name' => $question ] );
			hcp_v2_update_field( hcp_v2_k( $textarea ), [ 'name' => $question ], [ 'label' => 'none' ] );
		}
		$notes[] = 'step 3';

		FrmField::delete_form_transient( $form_id );
		FrmForm::clear_form_cache();

		// ------------------------------------------------------------------
		// Evaluation form: header and learning outcome 2.
		// ------------------------------------------------------------------
		hcp_v2_update_field( hcp_v2_k( '4ix3v2' ), [
			'description' => '<h1>ACTIVITY EVALUATION</h1>'
				. '<h2>Anal Fissures: Breaking the Cycle and the Stigma – Clinical Audit (Activity ID ' . esc_html( $variant['activity_id'] ) . ')</h2>'
				. '<img src="' . esc_url( content_url( '/uploads/2025/12/RACGP-logo-for-Clinical-Audit-Only.png' ) ) . '" alt="" class="mb-lg block" />'
				. 'Congratulations on completing your clinical audit on anal fissure management. Please take some time to complete the evaluation questions and provide feedback on your learning experience.',
		] );
		hcp_v2_update_field( hcp_v2_k( 'wyvsq2' ), [
			'name' => 'Guide the screening, diagnosis, and management of three hypothetical patients who present with possible anal fissure, according to the screening criteria outlined in the learning module (case study assessments)',
		] );
		FrmField::delete_form_transient( (int) $variant['eval_form'] );
		$notes[] = 'evaluation form';

		// ------------------------------------------------------------------
		// Course page.
		// ------------------------------------------------------------------
		hcp_v2_replace_in_post( (int) $variant['course'], [
			'You will systematically complete a retrospective analysis and a prospective analysis of your own management of adults with anal fissure in comparison with best clinical practice.'
				=> 'You will systematically complete a retrospective analysis comparing your own management of adults with anal fissure with best clinical practice followed by three case study assessments to help guide improvement in your future clinical practice.',
			'Identify three adult patients who present with a risk of anal fissure according to the screening criteria outlined in the learning module, and record the outcomes of screening, diagnosis, and management (prospective analysis)'
				=> 'Guide the screening, diagnosis, and management of three hypothetical patients who present with possible anal fissure, according to the screening criteria outlined in the learning module (case study assessments)',
			'you will earn 6.5 hours of Measuring Outcomes (MO) CPD and 1.0 hour of Reviewing Performance (RP) CPD'
				=> 'you will earn 3.0 hours of Measuring Outcomes (MO) CPD and 2.0 hours of Reviewing Performance (RP) CPD',
		], $notes );

		// The 6-month recommendation paragraph is removed.
		$course      = get_post( (int) $variant['course'] );
		$without_rec = preg_replace(
			'~<!-- wp:paragraph -->\s*<p><strong>We recommend that you complete this clinical audit within 6 months\.</strong></p>\s*<!-- /wp:paragraph -->\s*~',
			'',
			$course->post_content,
			1,
			$removed
		);
		if ( $removed ) {
			kses_remove_filters();
			wp_update_post( [ 'ID' => $course->ID, 'post_content' => $without_rec ] );
			kses_init_filters();
			$notes[] = 'course: 6-month recommendation removed';
		}

		$course_settings = get_post_meta( (int) $variant['course'], '_sfwd-courses', true );
		$short           = 'This activity supports GPs through a review of their historical clinical practices for anal fissure, as well as case study assessments that support improved approaches to management. GPs will progress through the saveable online form in their own time, then submit to the Education Provider for review upon completion.'
			. "\n\nApproved RACGP CPD hours: 3.0 hours (Measuring Outcomes) + 2.0 hours (Reviewing Performance)";
		if ( ( $course_settings['sfwd-courses_course_short_description'] ?? '' ) !== $short ) {
			$course_settings['sfwd-courses_course_short_description'] = $short;
			update_post_meta( (int) $variant['course'], '_sfwd-courses', $course_settings );
			update_post_meta( (int) $variant['course'], '_learndash_course_grid_short_description', $short );
			$notes[] = 'course short description';
		}

		// ------------------------------------------------------------------
		// Activity homepage and public landing page: audit card + hours.
		// ------------------------------------------------------------------
		$card_copy = [
			'This activity will support GPs through a review of their historical clinical practices (retrospective analysis) as well as implementation of improved clinical practices (prospective analysis) for anal fissure. Over the course of the audit, GPs will input their findings in a saveable online form, which will be submitted to the Education Provider for review upon completion.'
				=> 'This activity supports GPs through a review of their historical clinical practices for anal fissure, as well as case study assessments that support improved approaches to management. GPs will progress through the saveable online form in their own time, then submit to the Education Provider for review upon completion.',
			'<br />6.5 hours Measuring Outcomes  <br />+ 1.0 hour Reviewing Performance  </p>'
				=> '<br />3.0 hours Measuring Outcomes  <br />+ 2.0 hours Reviewing Performance  </p>',
			'accredited for 8.5 hours of CPD'
				=> 'accredited for 6.0 hours of CPD',
		];
		foreach ( [ 'anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage', 'anal-fissures-breaking-the-cycle-and-the-stigma-landing' ] as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page ) {
				continue;
			}
			$content = $page->post_content;
			$pairs   = $card_copy;
			// The hours table cell differs slightly between the two pages.
			foreach ( [
				'<br />6.5 hours Measuring Outcomes  <br />+ 1.0 hour Reviewing Performance  </td>' => '<br />3.0 hours Measuring Outcomes  <br />+ 2.0 hours Reviewing Performance  </td>',
				'<br />6.5 hours Measuring Outcomes +  <br />1.0 hour Reviewing Performance  </td>' => '<br />3.0 hours Measuring Outcomes +  <br />2.0 hours Reviewing Performance  </td>',
			] as $old => $new ) {
				if ( false !== strpos( $content, $old ) ) {
					$pairs[ $old ] = $new;
				}
			}
			$pairs = array_filter( $pairs, fn( $old ) => false !== strpos( $content, $old ), ARRAY_FILTER_USE_KEY );
			hcp_v2_replace_in_post( (int) $page->ID, $pairs, $notes );
		}

		return implode( '; ', $notes );
	},
];

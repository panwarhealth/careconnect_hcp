<?php
/**
 * Add the required Terms + Privacy tick box to Form 2, the one registration
 * form, just above the Register button. Skips if the field exists.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Form 2: required "I have read and agree to the Terms of Use and Privacy Policy" tick box.',
	'up'          => function () {
		if ( ! class_exists( 'FrmField' ) || ! class_exists( 'FrmFieldsHelper' ) ) {
			throw new RuntimeException( 'Formidable is not active.' );
		}
		if ( hcp_consent_terms_field_id() ) {
			return 'Already exists (field ' . hcp_consent_terms_field_id() . ').';
		}

		$label = 'I have read and agree to the <a href="/terms-of-use/" target="_blank" rel="noopener">Terms of Use</a> and <a href="/privacy-policy/" target="_blank" rel="noopener">Privacy Policy</a>';

		$vars = FrmFieldsHelper::setup_new_vars( 'checkbox', HCP_CONSENT_REG_FORM_ID );

		$vars['field_key']   = HCP_CONSENT_TERMS_FIELD_KEY;
		$vars['name']        = 'Terms and Privacy Policy';
		$vars['required']    = 1;
		$vars['field_order'] = 35; // after the hidden fields, before Register (36)
		$vars['options']     = array( array( 'label' => $label, 'value' => 'Accepted' ) );

		$vars['field_options']['label']          = 'none';
		$vars['field_options']['separate_value'] = 1;
		$vars['field_options']['blank']          = 'Please agree to the Terms of Use and Privacy Policy to register.';

		$field_id = FrmField::create( $vars );
		if ( ! $field_id ) {
			throw new RuntimeException( 'Field not created.' );
		}

		return 'Created field ' . $field_id . ' on Form ' . HCP_CONSENT_REG_FORM_ID . '.';
	},
);

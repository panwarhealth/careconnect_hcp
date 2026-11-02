<?php
/**
 * CAPH0150: walkthrough v5 mark-up. Step 2B's "Do these areas align" question
 * lines up with the "Reflect on" question below it. Its choices were indented
 * on their own; the whole field now takes the same indent as that question.
 *
 * Idempotent. To roll back, set the field's classes back to indent-choices.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: Step 2B align question lines up with the reflect question',
	'up'          => function (): string {
		$field = FrmField::getOne( 'v2-2b-compare-align' );

		if ( ! $field ) {
			throw new RuntimeException( 'Field v2-2b-compare-align not found.' );
		}

		$opts = (array) $field->field_options;

		if ( 'pl-6' === ( $opts['classes'] ?? '' ) ) {
			return 'Already indented.';
		}

		$opts['classes'] = 'pl-6';
		FrmField::update( (int) $field->id, [ 'field_options' => $opts ] );
		FrmField::delete_form_transient( (int) $field->form_id );
		FrmForm::clear_form_cache();

		return 'Align question indented.';
	},
];

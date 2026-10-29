<?php
/**
 * CAPH0150: walkthrough v4 mark-up on the 2026 audit form.
 *
 *  - Step 1B prevalence statement: the generated word carries its own
 *    comparison ("higher than" / "lower than" / "similar to").
 *  - Step 1B question 3: the error shown when the areas are incomplete said
 *    "select 3", which read as a cap. The rule is three or more, each with a reason.
 *  - Step 2B question 1: no bottom rule on the section (it doubled the rule
 *    above question 2) and a divider between its questions.
 *  - Step 2B question 2: the reflection box lines up with the boxes below.
 *
 * Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: walkthrough v4 mark-up (1B prevalence wording, areas error message, 2B dividers and indent).',

	'up' => function (): string {
		$prefix = HCP_MCA_V2_FIELD_KEY_PREFIX;
		$notes  = [];
		$forms  = [];

		$field = function ( string $suffix ) use ( $prefix ) {
			$f = FrmField::getOne( $prefix . $suffix );
			if ( ! $f ) {
				throw new \RuntimeException( "field {$prefix}{$suffix} not found" );
			}
			return $f;
		};

		// Copy: [ key suffix, column, exact old text, new text ].
		$copy = [
			[ '77l5c', 'description',
				'is <strong>…</strong> than general estimates',
				'is <strong>…</strong> general estimates' ],
		];
		foreach ( $copy as [ $suffix, $column, $old, $new ] ) {
			$f       = $field( $suffix );
			$current = (string) $f->{$column};
			if ( false !== strpos( $current, $new ) ) {
				$notes[] = "{$suffix} already updated";
				continue;
			}
			if ( 1 !== substr_count( $current, $old ) ) {
				throw new \RuntimeException( "{$suffix}: expected text not found exactly once" );
			}
			FrmField::update( (int) $f->id, [ $column => str_replace( $old, $new, $current ) ] );
			$forms[ (int) $f->form_id ] = true;
			$notes[] = "{$suffix} {$column} updated";
		}

		// Areas gatekeeper: error message.
		$gate      = $field( 'f5cgt' );
		$gate_opts = (array) $gate->field_options;
		$gate_msg  = 'Please select at least 3 areas and provide a reason for each area you select.';
		if ( ( $gate_opts['blank'] ?? '' ) === $gate_msg ) {
			$notes[] = 'areas error message already updated';
		} else {
			$gate_opts['blank'] = $gate_msg;
			FrmField::update( (int) $gate->id, [ 'field_options' => $gate_opts ] );
			$forms[ (int) $gate->form_id ] = true;
			$notes[] = 'areas error message updated';
		}

		// CSS classes to add: key suffix => class.
		$classes = [
			'2b-rate'        => 'bnone',
			'2b-rate-b'      => 'hcp-q-divider',
			'2b-rate-c'      => 'hcp-q-divider',
			'2b-rate-d'      => 'hcp-q-divider',
			'2b-rate-e'      => 'hcp-q-divider',
			'2b-compare-why' => 'pl-6',
		];
		foreach ( $classes as $suffix => $class ) {
			$f       = $field( $suffix );
			$opts    = (array) $f->field_options;
			$current = preg_split( '/\s+/', trim( (string) ( $opts['classes'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
			if ( in_array( $class, $current, true ) ) {
				$notes[] = "{$suffix} already has {$class}";
				continue;
			}
			$current[]       = $class;
			$opts['classes'] = implode( ' ', $current );
			FrmField::update( (int) $f->id, [ 'field_options' => $opts ] );
			$forms[ (int) $f->form_id ] = true;
			$notes[] = "{$suffix} +{$class}";
		}

		foreach ( array_keys( $forms ) as $form_id ) {
			FrmField::delete_form_transient( $form_id );
		}
		FrmForm::clear_form_cache();
		return implode( '; ', $notes );
	},
];

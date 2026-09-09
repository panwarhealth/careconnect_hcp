<?php
/**
 * CAPH0150: Step 1B improvement areas are a minimum of three, not exactly
 * three. Relaxes the inline gatekeeper and the copy that says "three".
 * Must run as a user with unfiltered_html or the inline script is stripped.
 * Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: Step 1B improvement areas accept three or more (gatekeeper and copy).',

	'up' => function (): string {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			throw new \RuntimeException( 'run as an administrator: the inline script would be stripped' );
		}
		$notes  = [];
		$prefix = HCP_MCA_V2_FIELD_KEY_PREFIX;

		// 1. Gatekeeper: three or more selected areas, each with a reason.
		$divider = FrmField::getOne( $prefix . 'w71ro' );
		if ( ! $divider ) {
			throw new \RuntimeException( 'improvement-areas divider not found' );
		}
		$opts = (array) $divider->field_options;
		$html = (string) ( $opts['custom_html'] ?? '' );
		if ( false !== strpos( $html, 'selectedCount >= 3' ) ) {
			$notes[] = 'gatekeeper already relaxed';
		} elseif ( 1 === substr_count( $html, 'selectedCount === 3' ) ) {
			$opts['custom_html'] = str_replace( 'selectedCount === 3', 'selectedCount >= 3', $html );
			FrmField::update( (int) $divider->id, [ 'field_options' => $opts ] );
			$notes[] = 'gatekeeper relaxed';
		} else {
			throw new \RuntimeException( 'gatekeeper script not in the expected form' );
		}

		// 2. Copy.
		$copy = [
			'w71ro'      => [
				'(A) Based on your analysis above, identify your top three areas that require the most improvement when managing future patients with anal fissure. Provide a brief reason for the underperformance, and why you believe it is important to improve. You must select 3 of the below areas and input a response.',
				'(A) Based on your analysis above, identify at least three areas that require the most improvement when managing future patients with anal fissure. Provide a brief reason for the underperformance, and why you believe it is important to improve. You must select at least 3 of the below areas and input a response.',
			],
			'2b-compare' => [
				'(A) In your retrospective analysis, you identified these three areas as needing the most improvement:',
				'(A) In your retrospective analysis, you identified these areas as needing the most improvement:',
			],
		];
		foreach ( $copy as $suffix => [ $old, $new ] ) {
			$field = FrmField::getOne( $prefix . $suffix );
			if ( ! $field ) {
				throw new \RuntimeException( "field {$prefix}{$suffix} not found" );
			}
			if ( $field->name === $new ) {
				$notes[] = "{$suffix} copy already updated";
			} elseif ( $field->name === $old ) {
				FrmField::update( (int) $field->id, [ 'name' => $new ] );
				$notes[] = "{$suffix} copy updated";
			} else {
				throw new \RuntimeException( "{$suffix}: unexpected current copy" );
			}
		}

		FrmField::delete_form_transient( (int) $divider->form_id );
		FrmForm::clear_form_cache();
		return implode( '; ', $notes );
	},
];

<?php
/**
 * CAPH0150: the new audit is named as in the approved copy, "Clinical Audit:
 * Anal Fissure Management"; the original is "Mini Clinical Audit (legacy)".
 * Code-side labels live in the variant registry; this renames the stored
 * completion email title. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: completion email title uses the audit name from the approved copy.',

	'up' => function (): string {
		$variant = hcp_mca_variants( true )[ HCP_MCA_VARIANT_V2 ] ?? null;
		if ( ! $variant ) {
			throw new \RuntimeException( '2026 variant not provisioned; run the provision migration first.' );
		}
		$title = 'Congratulations! ' . $variant['short_label'] . ' complete';
		$post  = get_page_by_path( 'clinical-audit-2026-completion-email', OBJECT, 'ld-notification' );
		if ( ! $post ) {
			throw new \RuntimeException( 'completion email notification not found' );
		}
		$id = (int) $post->ID;
		if ( $post->post_title === $title ) {
			return 'completion email title already updated';
		}
		wp_update_post( [ 'ID' => $id, 'post_title' => $title ] );
		return "completion email {$id} title updated";
	},
];

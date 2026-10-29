<?php
/**
 * CAPH0150: automated activity emails updated for the 2026 audit (Maria's mark-up).
 *
 *  - Registration (111729) and reminder (112673): 2026 total-activity CPD logo,
 *    6.0 hours, audit hours 3.0 MO + 2.0 RP, activity ID 1735802, "current and"
 *    dropped (registration), main body centred.
 *  - Learning module complete (112545): audit description and second bullet
 *    describe the case studies, audit hours 3.0 + 2.0, audit section centred,
 *    the audit button goes to the chooser (which routes to the right version).
 *  - 2026 audit complete: 2026 audit-only CPD logo (text already said 3.0 + 2.0).
 *
 * The legacy audit-complete email (112561) is untouched: legacy completers still
 * earn 6.5 MO + 1.0 RP. Idempotent.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hcp_mca_v4_centre_section' ) ) {
	/**
	 * Centre one email body: every es-content section from the one holding $from up to
	 * (not including) the Contact us section. Lists and the CPD details table keep their
	 * left alignment and sit as centred blocks.
	 */
	function hcp_mca_v4_centre_section( string $html, string $from ): string {
		$marker = strpos( $html, $from );
		$contact = strpos( $html, 'Contact us' );
		if ( false === $marker || false === $contact ) {
			throw new \RuntimeException( "centre: marker not found ({$from})" );
		}
		$start = strrpos( substr( $html, 0, $marker ), '<table class="es-content"' );
		$end   = strrpos( substr( $html, 0, $contact ), '<table class="es-content"' );
		if ( false === $start || false === $end || $end <= $start ) {
			throw new \RuntimeException( "centre: section bounds not found ({$from})" );
		}

		$keep  = '~(<ul\b.*?</ul>|<table[^>]*>(?:(?!<table).)*?RACGP Activity ID.*?</table>)~s';
		$parts = preg_split( $keep, substr( $html, $start, $end - $start ), -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				// Kept block: centre the block itself, leave its contents left-aligned.
				$parts[ $i ] = 0 === strpos( $part, '<ul' )
					? preg_replace( '~^<ul style="~', '<ul style="display: inline-block; text-align: left; ', $part, 1 )
					: preg_replace( '~^<table~', '<table align="center"', $part, 1 );
				continue;
			}
			$part = str_replace( [ 'align="left"', 'text-align: left' ], [ 'align="center"', 'text-align: center' ], $part );
			$part = preg_replace_callback(
				'~<p\b[^>]*>~',
				function ( array $m ): string {
					if ( false !== strpos( $m[0], 'text-align' ) ) {
						return $m[0];
					}
					return false !== strpos( $m[0], 'style="' )
						? str_replace( 'style="', 'style="text-align: center; ', $m[0] )
						: str_replace( '<p', '<p style="text-align: center;"', $m[0] );
				},
				$part
			);
			$parts[ $i ] = $part;
		}

		return substr( $html, 0, $start ) . implode( '', $parts ) . substr( $html, $end );
	}
}

return [
	'description' => 'CAPH0150: activity emails updated for the 2026 audit (logos, hours, activity ID, copy, centred layout).',

	'up' => function (): string {
		$v2 = hcp_mca_variant( HCP_MCA_VARIANT_V2 );
		if ( ! $v2 ) {
			throw new \RuntimeException( '2026 audit variant not provisioned' );
		}
		$logo_total = hcp_mca_cpd_logo_url( $v2, 'total' );
		$logo_audit = hcp_mca_cpd_logo_url( $v2, 'audit' );
		if ( ! $logo_total || ! $logo_audit ) {
			throw new \RuntimeException( '2026 CPD logos not available' );
		}
		$old_total = home_url( '/wp-content/uploads/2025/12/RACGP-logo-for-Total-Activity.png' );
		$old_audit = home_url( '/wp-content/uploads/2025/12/RACGP-logo-for-Clinical-Audit-Only.png' );

		$v2_email = get_page_by_path( 'clinical-audit-2026-completion-email', OBJECT, 'ld-notification' );
		if ( ! $v2_email ) {
			throw new \RuntimeException( '2026 audit completion email not found' );
		}

		// Post => [ [ old, new ], ... ] exact replacements, then the section to centre (or null).
		$emails = [
			111729 => [
				'edits'  => [
					[ $old_total, $logo_total ],
					[ 'accredited for 8.5 hours of CPD', 'accredited for 6.0 hours of CPD' ],
					[ '<span class="comment_message--boldStyle--MQOWZ">1460044</span>', '<span class="comment_message--boldStyle--MQOWZ">' . $v2['activity_id'] . '</span>' ],
					[ 'improved management of current and future patients', 'improved management of future patients' ],
					[ "6.5 hours Measuring Outcomes\r\n1.0 hour Reviewing Performance", "3.0 hours Measuring Outcomes\r\n2.0 hours Reviewing Performance" ],
				],
				'centre' => 'Why is this activity important',
			],
			112673 => [
				'edits'  => [
					[ $old_total, $logo_total ],
					[ 'RACGP-accredited for 8.5 hours of CPD', 'RACGP-accredited for 6.0 hours of CPD' ],
					[ '6.5 hours Measuring Outcomes + 1.0 hour Reviewing Performance', '3.0 hours Measuring Outcomes + 2.0 hours Reviewing Performance' ],
				],
				'centre' => 'We encourage you',
			],
			112545 => [
				'edits'  => [
					[ 'historical clinical practices to develop and implement improved management of patients with anal fissures in your own practice.', 'historical clinical practices, as well as case study assessments that support improved approaches to management.' ],
					[ 'Prospective analysis of your current anal fissure management practices', 'Case study assessment of three hypothetical patients presenting with possible anal fissure' ],
					[ '6.5 hours Measuring Outcomes + 1.0 hour Reviewing Performance', '3.0 hours Measuring Outcomes + 2.0 hours Reviewing Performance' ],
					[ 'href="' . home_url( '/courses/mini-clinical-audit/' ) . '">Start your activity', 'href="' . home_url( '/' . HCP_MCA_CHOOSER_PAGE_SLUG . '/' ) . '">Start your activity' ],
				],
				'centre' => 'You are now ready',
			],
			(int) $v2_email->ID => [
				'edits'  => [
					[ $old_audit, $logo_audit ],
				],
				'centre' => null,
			],
		];

		$notes = [];
		foreach ( $emails as $post_id => $spec ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				throw new \RuntimeException( "email {$post_id} not found" );
			}
			if ( get_post_meta( $post_id, '_hcp_caph0150_emails_v4', true ) ) {
				$notes[] = "{$post_id} already updated";
				continue;
			}
			$html = $post->post_content;
			foreach ( $spec['edits'] as [ $old, $new ] ) {
				if ( 1 !== substr_count( $html, $old ) ) {
					throw new \RuntimeException( "{$post_id}: expected text not found exactly once: " . substr( wp_strip_all_tags( $old ), 0, 60 ) );
				}
				$html = str_replace( $old, $new, $html );
			}
			if ( $spec['centre'] ) {
				$html = hcp_mca_v4_centre_section( $html, $spec['centre'] );
			}
			// Original kept for rollback.
			update_post_meta( $post_id, '_hcp_caph0150_emails_v4_before', wp_slash( $post->post_content ) );
			kses_remove_filters();
			wp_update_post( [ 'ID' => $post_id, 'post_content' => wp_slash( $html ) ] );
			kses_init_filters();
			update_post_meta( $post_id, '_hcp_caph0150_emails_v4', 1 );
			$notes[] = "{$post_id} updated";
		}

		return implode( '; ', $notes );
	},
];

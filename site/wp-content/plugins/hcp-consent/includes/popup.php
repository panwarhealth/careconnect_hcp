<?php
/**
 * The consent pop-up: asked of every logged-in HCP with no answer on file.
 *
 * Registered with hcp-popups above everything else and blocking, so promotional
 * pop-ups stand down until it is answered and it cannot be closed without an
 * answer. Shows straight away rather than after the usual dwell, and holds
 * other pop-ups back for a day after it last appeared, so answering it isn't
 * followed by another one on the next page.
 */

defined( 'ABSPATH' ) || exit;

const HCP_CONSENT_POPUP_ID = 'caph0085-marketing-consent';

add_action( 'hcp_popups_register', 'hcp_consent_register_popup' );
function hcp_consent_register_popup(): void {
	if ( ! function_exists( 'hcp_popups_add' ) ) {
		return;
	}

	hcp_popups_add(
		array(
			'id'       => HCP_CONSENT_POPUP_ID,
			'priority' => 10,
			'blocking' => true,
			'delay_ms' => 0,
			'cooldown' => DAY_IN_SECONDS,
			'eligible' => 'hcp_consent_popup_eligible',
			'render'   => 'hcp_consent_popup_render',
		)
	);
}

function hcp_consent_popup_eligible(): bool {
	$user_id = get_current_user_id();

	return $user_id && null === hcp_consent_marketing_status( $user_id );
}

// The manager renders in wp_footer; assets have to be queued before that.
add_action( 'wp_enqueue_scripts', 'hcp_consent_popup_enqueue', 21 );
function hcp_consent_popup_enqueue(): void {
	if ( ! function_exists( 'hcp_popups_pick' ) ) {
		return;
	}

	$popup = hcp_popups_pick();
	if ( $popup && HCP_CONSENT_POPUP_ID === $popup['id'] ) {
		hcp_consent_enqueue_choice();
	}
}

function hcp_consent_popup_render(): void {
	?>
	<h2 class="hcp-popup__title" id="hcp-popup-title">Communication preferences</h2>
	<?php
	echo hcp_consent_choice_html( 'in-portal-modal' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the builder.
}

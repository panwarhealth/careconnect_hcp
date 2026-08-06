<?php
/**
 * Pop-up registry and queue.
 *
 * A pop-up registers an id, a priority, an eligibility test and a renderer.
 * On each page load the manager walks the queue in priority order, renders the
 * first eligible pop-up and stops. One pop-up per page load, never two.
 *
 * Deferral needs no queue state: a higher-priority pop-up simply wins today and
 * a lower-priority one becomes eligible on a later load once the first is
 * resolved. A consent modal would register above everything with 'blocking'
 * set, and the pop-ups below it stand down without changing their own code.
 */

defined( 'ABSPATH' ) || exit;

const HCP_POPUPS_SEEN_COOKIE    = 'hcp_popup_seen';
const HCP_POPUPS_SESSION_COOKIE = 'hcp_popup_sid';

/**
 * @param array $popup {
 *     @type string   $id       Stable slug, also the GA4 popup_id.
 *     @type int      $priority Lower wins. 10 = blocking/compliance, 50 = promotional.
 *     @type callable $eligible Returns bool for the current request.
 *     @type callable $render   Echoes the dialog markup.
 *     @type bool     $blocking Page cannot be used until answered. Default false.
 * }
 */
function hcp_popups_add( array $popup ): void {
	$GLOBALS['hcp_popups'][ $popup['id'] ] = wp_parse_args(
		$popup,
		array(
			'priority' => 50,
			'blocking' => false,
		)
	);
}

function hcp_popups_all(): array {
	$popups = $GLOBALS['hcp_popups'] ?? array();

	uasort(
		$popups,
		static function ( $a, $b ) {
			return $a['priority'] <=> $b['priority'];
		}
	);

	return $popups;
}

/**
 * The pop-up to show on this request, or null.
 *
 * Resolved once: eligibility hits the database, and both the asset enqueue and
 * the footer render ask for it.
 */
function hcp_popups_pick(): ?array {
	static $picked = false;

	if ( false !== $picked ) {
		return $picked;
	}

	$picked = hcp_popups_resolve();

	return $picked;
}

function hcp_popups_resolve(): ?array {
	if ( ! hcp_popups_context_allows() ) {
		return null;
	}

	$seen = array_filter( explode( ',', (string) ( $_COOKIE[ HCP_POPUPS_SEEN_COOKIE ] ?? '' ) ) );

	foreach ( hcp_popups_all() as $popup ) {
		if ( in_array( $popup['id'], $seen, true ) ) {
			continue;
		}
		if ( is_callable( $popup['eligible'] ) && call_user_func( $popup['eligible'] ) ) {
			return $popup;
		}
	}

	return null;
}

add_action( 'init', 'hcp_popups_boot' );
function hcp_popups_boot(): void {
	do_action( 'hcp_popups_register' );
}

// Assets must be enqueued before wp_print_footer_scripts, which also runs on
// wp_footer at priority 20 — enqueueing from the render callback is a race.
add_action( 'wp_enqueue_scripts', 'hcp_popups_enqueue', 20 );
function hcp_popups_enqueue(): void {
	$popup = hcp_popups_pick();
	if ( ! $popup ) {
		return;
	}

	$gtag = hcp_popups_enqueue_ga4();

	wp_enqueue_style( 'hcp-popups', HCP_POPUPS_URL . 'assets/css/popup.css', array(), '0.1.0' );
	wp_enqueue_script( 'hcp-popups', HCP_POPUPS_URL . 'assets/js/popup.js', array_filter( array( $gtag ) ), '0.1.0', true );
	// wp_localize_script casts everything to string; the JS wants real numbers
	// and booleans, so hand it JSON instead.
	wp_add_inline_script(
		'hcp-popups',
		'window.hcpPopup = ' . wp_json_encode(
			array(
				'id'            => $popup['id'],
				'blocking'      => (bool) $popup['blocking'],
				'measurementId' => hcp_popups_ga4_measurement_id(),
				'delayMs'    => 3000,
				'scrollPct'  => 25,
				'seenCookie' => HCP_POPUPS_SEEN_COOKIE,
				'endpoint'   => rest_url( 'hcp-popups/v1/event' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'sessionId'  => hcp_popups_session_id(),
				'pagePath'   => hcp_popups_current_path(),
			)
		) . ';',
		'before'
	);

}

// Priority 5, before core prints footer scripts at 20: the dialog has to exist
// in the DOM by the time popup.js looks for it.
add_action( 'wp_footer', 'hcp_popups_render', 5 );
function hcp_popups_render(): void {
	$popup = hcp_popups_pick();
	if ( ! $popup ) {
		return;
	}

	printf(
		'<div class="hcp-popup" id="hcp-popup-%s" data-popup-id="%s" hidden>',
		esc_attr( $popup['id'] ),
		esc_attr( $popup['id'] )
	);
	echo '<div class="hcp-popup__backdrop" data-popup-dismiss></div>';
	echo '<div class="hcp-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hcp-popup-title">';
	echo '<button type="button" class="hcp-popup__close" data-popup-dismiss aria-label="Close">&times;</button>';
	call_user_func( $popup['render'] );
	echo '</div></div>';
}

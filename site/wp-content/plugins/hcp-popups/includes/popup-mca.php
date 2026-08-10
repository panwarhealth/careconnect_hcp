<?php
/**
 * CAPH0150 — MCA activity pop-up.
 *
 * Promotes the two-part Rectogesic educational activity (learning module 95553
 * + mini clinical audit 111793) to people who have not started it.
 *
 * Logged-in users are filtered on MCA activity. Logged-out visitors always see
 * it: there is no way to tell what a stranger has already done, and the eDM
 * traffic this is meant to catch arrives logged-out on ungated campaign pages.
 */

defined( 'ABSPATH' ) || exit;

const HCP_POPUPS_MCA_ID          = 'caph0150-mca-activity';
const HCP_POPUPS_MCA_INELIGIBLE  = 'hcp_popup_mca_ineligible';
const HCP_POPUPS_MCA_COURSE_IDS  = array( 95553, 111793 );
const HCP_POPUPS_MCA_AUDIT_FORM  = 161;
const HCP_POPUPS_MCA_CTA_URL     = '/anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage/';

add_action( 'hcp_popups_register', 'hcp_popups_register_mca' );
function hcp_popups_register_mca(): void {
	hcp_popups_add(
		array(
			'id'       => HCP_POPUPS_MCA_ID,
			'priority' => 50,
			'eligible' => 'hcp_popups_mca_eligible',
			'render'   => 'hcp_popups_mca_render',
		)
	);
}

function hcp_popups_mca_eligible(): bool {
	$user_id = get_current_user_id();

	return $user_id ? ! hcp_popups_mca_user_has_activity( $user_id ) : true;
}

/**
 * Any trace of the MCA flow means leave them alone: enrolment or progress on
 * either course, a submitted audit awaiting review, or a completion.
 *
 * Ineligibility is permanent, so it is cached in user meta after the first
 * positive result rather than re-queried on every page load.
 */
function hcp_popups_mca_user_has_activity( int $user_id ): bool {
	if ( get_user_meta( $user_id, HCP_POPUPS_MCA_INELIGIBLE, true ) ) {
		return true;
	}

	global $wpdb;

	$found = (bool) get_user_meta( $user_id, 'course_completed_' . HCP_POPUPS_MCA_COURSE_IDS[1], true );

	if ( ! $found ) {
		$ids   = implode( ',', array_map( 'intval', HCP_POPUPS_MCA_COURSE_IDS ) );
		$found = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT activity_id FROM {$wpdb->prefix}learndash_user_activity
				 WHERE user_id = %d AND course_id IN ({$ids}) LIMIT 1",
				$user_id
			)
		);
	}

	if ( ! $found ) {
		$found = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}frm_items WHERE user_id = %d AND form_id = %d LIMIT 1",
				$user_id,
				HCP_POPUPS_MCA_AUDIT_FORM
			)
		);
	}

	if ( $found ) {
		update_user_meta( $user_id, HCP_POPUPS_MCA_INELIGIBLE, 1 );
	}

	return $found;
}

function hcp_popups_mca_render(): void {
	$img = HCP_POPUPS_URL . 'assets/img/';
	?>
	<h2 class="hcp-popup__title" id="hcp-popup-title">GPs: EARN UP TO 8.5 CPD&nbsp;HOURS</h2>

	<p class="hcp-popup__lede"><strong class="hcp-popup__live">Live Now:</strong> A free, RACGP-accredited, two-part educational activity to improve the identification and <strong>management of anal fissures in general&nbsp;practice</strong>:</p>

	<ul class="hcp-popup__parts">
		<li><img src="<?php echo esc_url( $img . 'icon-monitor.png' ); ?>" alt="" width="22" height="20" /><span>Online Learning Module</span></li>
		<li class="hcp-popup__plus" aria-hidden="true">+</li>
		<li><img src="<?php echo esc_url( $img . 'icon-checklist.png' ); ?>" alt="" width="20" height="23" /><span>Mini Clinical Audit</span></li>
	</ul>

	<p class="hcp-popup__lede">Progress at your own pace and maximise hours across all CPD&nbsp;categories</p>

	<img class="hcp-popup__accreditation" src="<?php echo esc_url( $img . 'racgp-logo.png' ); ?>" width="400" height="267"
		alt="RACGP CPD Approved Activity: 1.0 hours Educational Activities, 1.0 hours Reviewing Performance, 6.5 hours Measuring Outcomes" />

	<a class="hcp-popup__cta" href="<?php echo esc_url( home_url( HCP_POPUPS_MCA_CTA_URL ) ); ?>" data-popup-cta>SEE FULL ACTIVITY</a>

	<p class="hcp-popup__footnote">This educational activity is provided by Panwar&nbsp;Health, who are an authorised Provider of education under the RACGP CPD&nbsp;program, and sponsored by Care&nbsp;Pharmaceuticals.</p>
	<?php
}

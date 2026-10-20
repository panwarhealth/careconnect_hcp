<?php
/**
 * Plugin Name: HCP Consent
 * Description: Spam Act marketing consent. A blocking Yes/No pop-up for logged-in HCPs, the same choice on Edit Your Profile and on the /consent-preferences/ page, a required Terms + Privacy tick box on the registration form, and an audit log of every answer. Also owns the Terms of Use and Privacy Policy page content.
 * Version:     0.1.0
 * Author:      Panwar Health
 * License:     GPL v2 or later
 * Text Domain: hcp-consent
 */

defined( 'ABSPATH' ) || exit;

define( 'HCP_CONSENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HCP_CONSENT_URL', plugin_dir_url( __FILE__ ) );

require_once HCP_CONSENT_DIR . 'includes/log.php';
require_once HCP_CONSENT_DIR . 'includes/legal.php';
require_once HCP_CONSENT_DIR . 'includes/choice.php';
require_once HCP_CONSENT_DIR . 'includes/rest.php';
require_once HCP_CONSENT_DIR . 'includes/popup.php';
require_once HCP_CONSENT_DIR . 'includes/profile.php';
require_once HCP_CONSENT_DIR . 'includes/landing.php';
require_once HCP_CONSENT_DIR . 'includes/registration.php';
require_once HCP_CONSENT_DIR . 'includes/migrations.php';
if ( is_admin() ) {
	require_once HCP_CONSENT_DIR . 'includes/admin-migrations-page.php';
	require_once HCP_CONSENT_DIR . 'includes/admin-export.php';
}

register_activation_hook(
	__FILE__,
	function () {
		hcp_consent_install();
		hcp_consent_migrations_run_pending();
	}
);

<?php
/**
 * Plugin Name: HCP Pop-ups
 * Description: Site pop-up manager. Pop-ups register with a priority; one shows per page load, the rest wait for a later load. Ships the CAPH0150 MCA activity pop-up and records impressions both server-side and to GA4.
 * Version:     0.1.0
 * Author:      Panwar Health
 * License:     GPL v2 or later
 * Text Domain: hcp-popups
 */

defined( 'ABSPATH' ) || exit;

define( 'HCP_POPUPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'HCP_POPUPS_URL', plugin_dir_url( __FILE__ ) );

require_once HCP_POPUPS_DIR . 'includes/manager.php';
require_once HCP_POPUPS_DIR . 'includes/context.php';
require_once HCP_POPUPS_DIR . 'includes/tracking.php';
require_once HCP_POPUPS_DIR . 'includes/popup-mca.php';

register_activation_hook( __FILE__, 'hcp_popups_install' );

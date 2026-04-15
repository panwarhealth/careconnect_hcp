<?php
/**
 * Plugin Name: HCP MCA Review Workflow
 * Description: Admin review + approval workflow for the Mini Clinical Audit (course 111793). Detects submission state, notifies the CPD rep when both audit + activity-evaluation forms are in, and wraps Maria's manual approval into a single action that fires LearnDash course completion + cert issuance.
 * Version:     0.1.0
 * Author:      Panwar Health
 * License:     GPL v2 or later
 * Text Domain: hcp-mca-review
 */

defined( 'ABSPATH' ) || exit;

define( 'HCP_MCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once HCP_MCA_PLUGIN_DIR . 'includes/constants.php';
require_once HCP_MCA_PLUGIN_DIR . 'includes/state.php';
require_once HCP_MCA_PLUGIN_DIR . 'includes/shortcodes.php';
require_once HCP_MCA_PLUGIN_DIR . 'includes/migrations.php';
if ( is_admin() ) {
	require_once HCP_MCA_PLUGIN_DIR . 'includes/admin-migrations-page.php';
}

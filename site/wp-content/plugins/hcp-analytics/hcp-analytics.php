<?php
/**
 * Plugin Name: HCP Analytics
 * Description: Site analytics (CAPH0118). Loads GA4 once for the whole site with the page's context (logged in, profession, page type, section), sends the standard events, loads Microsoft Clarity, remembers which campaign brought each visit and keeps a first-party record of what each logged-in HCP engages with.
 * Version:     0.1.0
 * Author:      Panwar Health
 * License:     GPL v2 or later
 * Text Domain: hcp-analytics
 */

defined( 'ABSPATH' ) || exit;

define( 'HCP_ANALYTICS_FILE', __FILE__ );
define( 'HCP_ANALYTICS_DIR', plugin_dir_path( __FILE__ ) );

require_once HCP_ANALYTICS_DIR . 'includes/config.php';
require_once HCP_ANALYTICS_DIR . 'includes/context.php';
require_once HCP_ANALYTICS_DIR . 'includes/source.php';
require_once HCP_ANALYTICS_DIR . 'includes/journey.php';
require_once HCP_ANALYTICS_DIR . 'includes/engagement.php';
require_once HCP_ANALYTICS_DIR . 'includes/tracking.php';

register_activation_hook(
	__FILE__,
	function () {
		add_option( HCP_ANALYTICS_MODE_OPTION, HCP_ANALYTICS_MODE_OFF );
		hcp_analytics_engagement_install();
	}
);

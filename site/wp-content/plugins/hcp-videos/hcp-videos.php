<?php
/**
 * Plugin Name: HCP Videos
 * Description: Dedicated "Video" content type powering YouTube-style video pages (player + description + 300x250 ad + related videos) for the Tools & Videos section. Registers the CPT, ACF fields, the [video_grid] listing shortcode, and a migration that seeds the existing videos. Single-video template lives in the wp-spinnr-child theme.
 * Version:     0.1.0
 * Author:      Panwar Health
 * License:     GPL v2 or later
 * Text Domain: hcp-videos
 */

defined( 'ABSPATH' ) || exit;

define( 'HCP_VIDEOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'HCP_VIDEOS_FILE', __FILE__ );

require_once HCP_VIDEOS_DIR . 'includes/cpt.php';
require_once HCP_VIDEOS_DIR . 'includes/fields.php';
require_once HCP_VIDEOS_DIR . 'includes/helpers.php';
require_once HCP_VIDEOS_DIR . 'includes/related.php';
require_once HCP_VIDEOS_DIR . 'includes/shortcode.php';
require_once HCP_VIDEOS_DIR . 'includes/migrations.php';
if ( is_admin() ) {
	require_once HCP_VIDEOS_DIR . 'includes/admin-migrations-page.php';
}

/**
 * On activation: register the CPT/taxonomies, flush rewrite rules so the
 * /video/{slug}/ permalinks resolve, then run pending migrations.
 */
register_activation_hook( __FILE__, function () {
	hcp_videos_register_cpt();
	flush_rewrite_rules();
	hcp_videos_migrations_run_pending();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

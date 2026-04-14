<?php

/**
 * WP SPINNR functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WP_SPINNR
 */

// Load config
require get_template_directory() . '/inc/config.php';

// Authentication Plugin
if (get_option('use_basic_auth')) require get_template_directory() . '/inc/plugins/basic-auth/basic-auth.php';
else require get_template_directory() . '/inc/plugins/jwt/jwt-auth.php';

// Load SPINNR theme libraries
require get_template_directory() . '/inc/frontend.php';
require get_template_directory() . '/inc/encryption.php';
require get_template_directory() . '/inc/api-key-check.php';
require get_template_directory() . '/inc/setup.php';
require get_template_directory() . '/inc/layout.php';
require get_template_directory() . '/inc/enqueue.php';
require get_template_directory() . '/inc/custom-rest-api.php';
require get_template_directory() . '/inc/custom-shortcodes.php';
require get_template_directory() . '/inc/spinnr-links.php';
require get_template_directory() . '/inc/theme-settings.php';
require get_template_directory() . '/inc/template-tags.php';

require get_template_directory() . '/inc/woocommerce.php';

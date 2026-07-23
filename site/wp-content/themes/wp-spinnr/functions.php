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

// Theme libraries
require get_template_directory() . '/inc/frontend.php';
require get_template_directory() . '/inc/setup.php';
require get_template_directory() . '/inc/layout.php';
require get_template_directory() . '/inc/enqueue.php';
require get_template_directory() . '/inc/rest-fields.php';
require get_template_directory() . '/inc/custom-shortcodes.php';
require get_template_directory() . '/inc/editor-lock.php';
require get_template_directory() . '/inc/template-tags.php';

require get_template_directory() . '/inc/woocommerce.php';

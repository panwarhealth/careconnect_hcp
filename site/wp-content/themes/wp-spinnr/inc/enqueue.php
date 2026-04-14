<?php

/**
 * Enqueue scripts and styles.
 *
 * @package WP_SPINNR
 */

/**
 * Enqueue front-end scripts and styles
 */
function wp_spinnr_scripts()
{

    // Jquery
    wp_enqueue_script('jquery');

    // Internet Explorer HTML5 support
    wp_enqueue_script('html5hiv', get_template_directory_uri() . '/inc/assets/js/html5.js', array(), '3.7.0', false);
    wp_script_add_data('html5hiv', 'conditional', 'lt IE 9');

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }

    if (file_exists(get_stylesheet_directory() . "/spinnr.min.css")) {
        wp_enqueue_style('wp-spinnr-custom-css', get_stylesheet_directory_uri() . '/spinnr.min.css', array(), filemtime(get_stylesheet_directory() . "/spinnr.min.css"));
    } else {
        wp_enqueue_style('wp-spinnr-custom-css', get_template_directory_uri() . '/inc/assets/css/default.min.css');
    }

    if (file_exists(get_stylesheet_directory() . "/spinnr_custom.js")) {
        wp_enqueue_script('wp-spinnr-custom-js', get_stylesheet_directory_uri() . '/spinnr_custom.js', array(), filemtime(get_stylesheet_directory() . "/spinnr_custom.js"), true);
    }
    if (file_exists(get_stylesheet_directory() . "/spinnr_custom_head.js")) {
        wp_enqueue_script('wp-spinnr-custom-js-head', get_stylesheet_directory_uri() . '/spinnr_custom_head.js', array(), filemtime(get_stylesheet_directory() . "/spinnr_custom_head.js"));
    }
    if (file_exists(get_stylesheet_directory() . "/spinnr_custom_body.js")) {
        wp_enqueue_script('wp-spinnr-custom-js-body', get_stylesheet_directory_uri() . '/spinnr_custom_body.js', array(), filemtime(get_stylesheet_directory() . "/spinnr_custom_body.js"), true);
    }
}
add_action('wp_enqueue_scripts', 'wp_spinnr_scripts');

/**
 * Enqueue back-end scripts and styles
 */
function wp_spinnr_custom_admin_js()
{
    wp_enqueue_script('wp_spinnr_custom_script', get_template_directory_uri() . '/inc/assets/js/theme.js', ['jquery'], '1.0.0', true);
}
add_action('admin_enqueue_scripts', 'wp_spinnr_custom_admin_js');

/**
 * Require SPINNR related assets
 */
if (file_exists(get_stylesheet_directory() . "/extend_spinnr.php")) {
    require get_stylesheet_directory() . "/extend_spinnr.php";
}

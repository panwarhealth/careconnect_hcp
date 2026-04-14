<?php

/**
 * Frontend functions
 *
 * @package WP_SPINNR
 */

if(!is_admin()) {
    add_action('template_redirect', 'wp_spinnr_disable_frontend');
    add_action('template_redirect', 'wp_spinnr_redirect_unauthenticated_users');
}

/**
 * Function to redirect unauthenticated users
 */
function wp_spinnr_redirect_unauthenticated_users() {
    if(get_option('password_protect') == 'yes'){
        if (!is_user_logged_in()) {
            nocache_headers();
            if ( wp_safe_redirect( esc_url( wp_login_url() . '?redirect_to=' . urlencode($_SERVER['REQUEST_URI'] ) ) ) ) {
                exit;
            };
        }
    }
}

/**
 * Function to redirect unauthenticated users
 */
function wp_spinnr_disable_frontend() {
    if(get_option('disable_frontend') == 'yes'){
        if ( wp_safe_redirect( esc_url( admin_url() ) ) ) {
            exit;
        };
    }
}
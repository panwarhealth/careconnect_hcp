<?php

/**
 * API Key checking and notification
 *
 * @package WP_SPINNR
 */

/**
 * API Key status check
 */
if (is_admin()) {
    if (get_option('wp_api_key')) {
        $last_check = get_option('spinnr_last_check');
        if(!$last_check || ($last_check + 60*60*8) < strtotime('now')){
            $key_status = json_decode(@file_get_contents(TBST_SPINNR_URL . '/wp-editor/check_wp_api_key/' . get_option('wp_api_key')));
            if($key_status){
                if(property_exists($key_status, 'status') && $key_status->status == 'success' ){
                    if (property_exists($key_status, 'version') && $key_status->version !== wp_get_theme('wp-spinnr')->get('Version')) {
                        require_once(trailingslashit(get_template_directory()) . 'inc/updater.php');
                    }
                } else {
                    add_action('admin_notices', 'wp_spinnr_admin_notice__invalid');
                }
            } else {
                //add_action('admin_notices', 'wp_spinnr_admin_connection__problem');
            }
        }
    }
}

/**
 * Admin notice if no API Key is set
 */
function wp_spinnr_admin_notice__invalid()
{
    if (is_admin()) {
?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Invalid WordPress API key.', 'wp-spinnr'); ?> <a href="<?php echo admin_url('themes.php?page=spinnr-theme-settings'); ?>"><?php _e('Please check your theme settings.', 'wp-spinnr'); ?></a></p>
        </div>
<?php
    }
}

/**
 * Admin notice if no API Key is set
 */
function wp_spinnr_admin_notice__error()
{
    if (is_admin()) {
        if (!get_option('wp_api_key')) {
?>
            <div class="notice notice-warning is-dismissible">
                <p><b><?php _e('Your SPINNR theme is almost ready.', 'wp-spinnr'); ?></b> <?php _e('You need to register an account to access the full features of SPINNR.', 'wp-spinnr'); ?> <a href="<?php echo admin_url('themes.php?page=spinnr-theme-settings'); ?>"><?php _e('Click here and start the setup process.', 'wp-spinnr'); ?></a></p>
            </div>
<?php
        }
    }
}
add_action('admin_notices', 'wp_spinnr_admin_notice__error');

/**
 * Problem with spinnr connection
 */
function wp_spinnr_admin_connection__problem()
{
    if (is_admin()) {
?>
        <div class="notice notice-error">
            <p>There is a connection error to SPINNR. You might not be able to connect to SPINNR temporarily until this is fixed.</p>
        </div>
<?php
    }
}

<?php

/**
 * WP_SPINNR_Theme_Setting_Class for connecting to SPINNR app
 *
 * @package WP_SPINNR
 */

defined('ABSPATH') or exit;

if (!class_exists('WP_SPINNR_Theme_Setting_Class') && is_admin()) {

    class WP_SPINNR_Theme_Setting_Class
    {

        public $theme;
        public $child_slug;

        function __construct()
        {

            $this->theme = wp_get_theme();

            add_action("admin_init", array($this, 'wp_spinnr_display_theme_panel_fields'));
            add_action('admin_menu', array($this, 'wp_spinnr_add_menu_item'));
            if (get_option('wp_api_key') && get_option('wp_api_key') !== '') {
                add_action('admin_footer', array($this, 'wp_spinnr_brandguide_open_in_new_window'));
            }

            add_action('wp_ajax_spinnr_child_theme', array($this, 'activate_child_theme'));
            add_action('wp_ajax_spinnr_check_version', array($this, 'check_wordpress_version'));
            add_action('wp_ajax_spinnr_latest_theme', array($this, 'get_latest_theme'));
            add_action('wp_ajax_create_spinnr_user', array($this, 'create_spinnr_user'));
        }

        /**
         * Add Theme Settings and Brand Style Guide and My Components option in Themes menu
         */
        function wp_spinnr_add_menu_item()
        {
            add_menu_page(
                __('SPINNR Theme Settings', 'wp-spinnr'),
                __('SPINNR', 'wp-spinnr'),
                'manage_options',
                'spinnr-theme-settings',
                array($this, 'wp_spinnr_settings_page'),
                'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMTMiIHZpZXdCb3g9IjAgMCAyMCAxMyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4NCjxwYXRoIGQ9Ik0xMS41MTk2IDEyLjM5NjhIOC4wOTk5M0wxLjIzNDAxIDIuNjE2ODVMMS4yNTYxOCAwLjAwNDQyNTA1SDQuMTY1NzdMMTAuNzIxMiA5LjMwNTM1TDExLjUxOTYgMTIuMzk2OFoiIGZpbGw9IiM2QTZGNzQiLz4NCjxwYXRoIGQ9Ik0xOS4wMjg2IDEyLjM5NjhIMTYuMDEyNkw5LjE1MTEyIDIuNjE2ODVMOS4xNjg4NiAwLjAwNDQyNTA1SDEyLjA3ODVMMTguNjMzOSA5LjMwNTM1TDE5LjAyODYgMTIuMzk2OFoiIGZpbGw9IiM2QTZGNzQiLz4NCjxwYXRoIGQ9Ik0wLjYxNzUwOCAwLjAwNDQzNTM2SDQuMTY1NzlWMTEuOTY2NkM0LjE2NTc5IDEyLjIwNjEgMy45NzA2MyAxMi40MDEyIDMuNzMxMTIgMTIuNDAxMkgwLjYxMzA3MkMwLjM3MzU2MyAxMi40MDEyIDAuMTc4NDA2IDEyLjIwNjEgMC4xNzg0MDYgMTEuOTY2NlYwLjQzNDY2NEMwLjE3ODQwNiAwLjE5NTE1NSAwLjM3MzU2MyAwIDAuNjEzMDcyIDBMMC42MTc1MDggMC4wMDQ0MzUzNloiIGZpbGw9IiM5Q0EyQTciLz4NCjxwYXRoIGQ9Ik04LjUzNDYxIDAuMDA0NDI1MDVIMTIuMDc4NVYxMS45NjIxQzEyLjA3ODUgMTIuMjAxNiAxMS44ODMzIDEyLjM5NjggMTEuNjQzOCAxMi4zOTY4SDguMDk5OTVWMC40MzkwODlDOC4wOTk5NSAwLjE5OTU4IDguMjk1MSAwLjAwNDQyNTA1IDguNTM0NjEgMC4wMDQ0MjUwNVoiIGZpbGw9IiM5Q0EyQTciLz4NCjxwYXRoIGQ9Ik0xNi40NDczIDAuMDA0NDM1MzZIMTkuNTY1M0MxOS44MDQ4IDAuMDA0NDM1MzYgMjAgMC4xOTk1OTEgMjAgMC40MzkxVjExLjk2NjZDMjAgMTIuMjA2MSAxOS44MDQ4IDEyLjQwMTIgMTkuNTY1MyAxMi40MDEySDE2LjAxN1YwLjQzNDY2NEMxNi4wMTcgMC4xOTUxNTUgMTYuMjEyMiAwIDE2LjQ1MTcgMEwxNi40NDczIDAuMDA0NDM1MzZaIiBmaWxsPSIjOUNBMkE3Ii8+DQo8cGF0aCBkPSJNNC4xNjU4IDYuNzkwNTFMNC43MTEzNSAwLjc4MDYxMUw0LjE2NTggMC4wMDQ0MjUwNVY2Ljc5MDUxWiIgZmlsbD0iIzFEMjMyNyIvPg0KPHBhdGggZD0iTTEyLjA3ODUgNi43OTA1MUwxMi42Mjg0IDAuNzgwNjExTDEyLjA3ODUgMC4wMDQ0MjUwNVY2Ljc5MDUxWiIgZmlsbD0iIzFEMjMyNyIvPg0KPC9zdmc+DQo=',
                99
            );
            if (get_option('wp_api_key') && get_option('wp_api_key') !== '') {
                global $submenu;
                $current_user = wp_get_current_user();
                $data = [
                    'username' => $current_user->user_login,
                    'host' => get_site_url()
                ];
                add_submenu_page(
                    'spinnr-theme-settings',
                    __('Theme Settings', 'wp-spinnr'),
                    __('Theme Settings', 'wp-spinnr'),
                    'manage_options',
                    'spinnr-theme-settings'
                );
                add_submenu_page(
                    'spinnr-theme-settings',
                    __('Brand Style Guide', 'wp-spinnr'),
                    __('Brand Style Guide', 'wp-spinnr'),
                    'manage_options',
                    TBST_SPINNR_URL . '/brand-style-guide?key=' . get_option('wp_api_key') . '&data=' . wp_spinnr_encrypt($data)
                );
                add_submenu_page(
                    'spinnr-theme-settings',
                    __('Headers', 'wp-spinnr'),
                    __('Headers', 'wp-spinnr'),
                    'manage_options',
                    'edit.php?post_type=spinnr_header',
                );
                add_submenu_page(
                    'spinnr-theme-settings',
                    __('Mobile Menus', 'wp-spinnr'),
                    __('Mobile Menus', 'wp-spinnr'),
                    'manage_options',
                    'edit.php?post_type=spinnr_mobile_menu',
                );
                add_submenu_page(
                    'spinnr-theme-settings',
                    __('Footers', 'wp-spinnr'),
                    __('Footers', 'wp-spinnr'),
                    'manage_options',
                    'edit.php?post_type=spinnr_footer',
                );
                add_submenu_page(
                    'spinnr-theme-settings',
                    __('My Components', 'wp-spinnr'),
                    __('My Components', 'wp-spinnr'),
                    'manage_options',
                    TBST_SPINNR_URL . '/my-components?key=' . get_option('wp_api_key') . '&data=' . wp_spinnr_encrypt($data)
                );
                add_submenu_page(
                    'spinnr-theme-settings',
                    __('Company Page', 'wp-spinnr'),
                    __('Company Page', 'wp-spinnr'),
                    'manage_options',
                    TBST_SPINNR_URL
                );

                // old links under Appearance
                $submenu['themes.php'][] = array(__('Theme Settings', 'wp-spinnr'), 'manage_options', 'admin.php?page=spinnr-theme-settings');
                $submenu['themes.php'][] = array(__('Brand Style Guide', 'wp-spinnr'), 'manage_options', TBST_SPINNR_URL . '/brand-style-guide?key=' . get_option('wp_api_key') . '&data=' . wp_spinnr_encrypt($data));
            }
        }

        /**
         * Add Theme Settings page
         */
        function wp_spinnr_settings_page()
        {
?>
            <div class="wrap">
                <h1><?php _e('SPINNR Theme', 'wp-spinnr'); ?></h1>
                <?php if (!get_option('wp_api_key') || get_option('wp_api_key') == '') : ?>
                    <h2><?php _e('Theme Setup Wizard', 'wp-spinnr'); ?></h2>
                    <p><?php _e('Click on the button below to run the setup process. You would be redirected to SPINNR after the automated process.', 'wp-spinnr'); ?></p>
                    <p class="submit">
                        <input type="button" id="wp-spinnr-run-setup" class="button button-primary" value="<?php _e('Run Setup Process', 'wp-spinnr'); ?>">
                    </p>
                    <ul id="wp-theme-setting-status">
                    </ul>
                <?php endif; ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields("section");
                    do_settings_sections("theme-options");
                    submit_button();
                    ?>
                </form>
            </div>
            <script>
                (function($) {
                    $(function() {
                        function wp_spinnr_append_status(s) {
                            $('#wp-theme-setting-status').append('<li>' + s + '</li>');
                        }
                        $(document).on('click', '#wp-spinnr-run-setup', function() {
                            $('#wp-spinnr-run-setup').prop('disabled', true);
                            wp_spinnr_append_status('<?php _e('Running SPINNR setup process. Please do not close the browser...', 'wp-spinnr'); ?>');
                            wp_spinnr_append_status('<?php _e('Updating theme to latest version...', 'wp-spinnr'); ?>');
                            $.post(ajaxurl, {
                                action: 'spinnr_latest_theme'
                            }, function(response) {
                                if (response.hasOwnProperty('message')) wp_spinnr_append_status(response.message);
                                wp_spinnr_append_status('<?php _e('Checking child theme...', 'wp-spinnr'); ?>');
                                $.post(ajaxurl, {
                                    action: 'spinnr_child_theme'
                                }, function(response) {
                                    wp_spinnr_append_status(response);
                                    wp_spinnr_append_status('<?php _e('Checking WordPress version...', 'wp-spinnr'); ?>');
                                    $.post(ajaxurl, {
                                        action: 'spinnr_check_version',
                                        theme_id: <?php echo TBST_SPINNR_TEMPLATE_ID; ?>
                                    }, function(response) {
                                        wp_spinnr_append_status(response.message);
                                        if (response.status) {
                                            wp_spinnr_append_status('<?php _e('Redirecting to SPINNR...', 'wp-spinnr'); ?>');
                                            window.location = response.redirect;
                                        } else {
                                            wp_spinnr_append_status('<?php _e('Error: Unable to create admin user.', 'wp-spinnr'); ?>');
                                        }

                                    }, 'json');
                                });
                            });
                        });
                    });
                })(jQuery);
            </script>
        <?php
        }

        /**
         * API Key field in Theme Settings page
         */
        function wp_spinnr_display_wp_api_key()
        {
        ?>
            <input type="text" class="regular-text" name="wp_api_key" id="wp_api_key" value="<?php echo get_option('wp_api_key'); ?>" />
            <?php if (!get_option('wp_api_key') || get_option('wp_api_key') == '') : ?>
                <p class="description"><?php _e('To generate an API key, click on "Run Setup Process" button above.', 'wp-spinnr'); ?></p>
            <?php endif; ?>
        <?php
        }

        /**
         * Locked editor field in Theme Settings page
         */
        function wp_spinnr_display_lock_editor()
        {
        ?>
            <input type="text" class="regular-text" name="lock_editor" id="lock_editor" value="<?php echo get_option('lock_editor', 'page,spinnr_header,spinnr_footer,spinnr_mobile_menu'); ?>" />
            <p class="description"><?php _e('WordPress default editor will be hidden on these post types. Use comma to separate multiple post types (eg. "post,page").', 'wp-spinnr'); ?></p>
        <?php
        }

        /**
         * Enable Frontend Authentication in Theme Settings page
         */
        function wp_spinnr_display_password_protect() {
            ?>
                <input type="checkbox" name="password_protect" id="password_protect" value="yes" <?php echo get_option('password_protect') == 'yes' ? 'checked="checked"' : ''; ?>/>
                <p class="description"><?php _e('If selected, only authenticated users will be able to view the front end.', 'wp-spinnr'); ?></p>
            <?php
        }

        /**
         * Disable Frontend Access in Theme Settings page
         */
        function wp_spinnr_display_disable_frontend_access() {
            ?>
                <input type="checkbox" name="disable_frontend" id="disable_frontend" value="yes" <?php echo get_option('disable_frontend') == 'yes' ? 'checked="checked"' : ''; ?>/>
                <p class="description"><?php _e('Disable access to front end. This option is used if you are using WordPress as headless CMS.', 'wp-spinnr'); ?></p>
            <?php
        }


        /**
         * Register Theme Settings variables
         */
        function wp_spinnr_display_theme_panel_fields()
        {
            add_settings_section("section", __('Settings', 'wp-spinnr'), null, "theme-options");
            add_settings_field("wp_api_key", __('SPINNR API Key', 'wp-spinnr'), array($this, "wp_spinnr_display_wp_api_key"), "theme-options", "section");
            add_settings_field("lock_editor", __('SPINNR Locked Post Types', 'wp-spinnr'), array($this, "wp_spinnr_display_lock_editor"), "theme-options", "section");
            add_settings_field("password_protect", __('Enable Frontend Authentication', 'wp-spinnr'), array( $this, "wp_spinnr_display_password_protect"), "theme-options", "section");
            add_settings_field("disable_frontend", __('Disable Frontend Access', 'wp-spinnr'), array( $this, "wp_spinnr_display_disable_frontend_access"), "theme-options", "section");
            register_setting("section", "wp_api_key");
            register_setting("section", "lock_editor");
            register_setting("section", "password_protect");
            register_setting("section", "disable_frontend");
        }

        /**
         * Open Brand Style Guide in new tab
         */
        function wp_spinnr_brandguide_open_in_new_window()
        {
        ?>
            <script>
                jQuery(document).ready(function($) {
                    $('a').each(function() {
                        if ($(this).html() == '<?php _e('Brand Style Guide', 'wp-spinnr'); ?>' || 
                            $(this).html() == '<?php _e('Company Page', 'wp-spinnr'); ?>' || 
                            $(this).html() == '<?php _e('My Components', 'wp-spinnr'); ?>') {
                            $(this).attr('target', '_blank');
                        }
                    });
                });
            </script>
<?php
        }

        /**
         * Check if child theme is present
         */
        function has_child_theme()
        {
            $themes = wp_get_themes();
            $folder_name = $this->theme->get_stylesheet();
            $this->child_slug = $folder_name . '-child';

            foreach ($themes as $theme) {
                if ($folder_name == $theme->get('Template')) {
                    $this->child_slug = $theme->get_stylesheet();
                    return true;
                }
            }

            return false;
        }

        /**
         * Activate child theme
         */
        function activate_child_theme()
        {

            if (false !== $this->theme->parent()) {
                wp_die(esc_html__('Child theme already present.', 'wp-spinnr'));
            }

            $parent_slug = $this->theme->get_stylesheet();

            // Create child theme
            if (!$this->has_child_theme()) {
                $this->create_child_theme();
            }

            switch_theme($this->child_slug);

            // Copy customizer settings, widgets, etc.
            $settings = get_option('theme_mods_' . $this->child_slug);

            if (false === $settings) {
                $parent_settings = get_option('theme_mods_' . $parent_slug);
                update_option('theme_mods_' . $this->child_slug, $parent_settings);
            }

            wp_die(esc_html__('Successfully switched to child theme!', 'wp-spinnr'));
        }

        /**
         * Create child theme
         */
        function create_child_theme()
        {
            $parent_dir = $this->theme->get_stylesheet_directory();
            $child_dir = $parent_dir . '-child';

            if (wp_mkdir_p($child_dir)) {
                $creds = request_filesystem_credentials(admin_url());
                WP_Filesystem($creds); // we already have direct access

                global $wp_filesystem;
                $wp_filesystem->put_contents($child_dir . '/style.css', $this->style_css());
                $wp_filesystem->put_contents($child_dir . '/functions.php', $this->functions_php());

                if (false !== ($img = $this->theme->get_screenshot('relative'))) {
                    $wp_filesystem->copy("$parent_dir/$img", "$child_dir/$img");
                }
            } else {
                wp_die(esc_html__('Error: theme folder not writable', 'wp-spinnr'));
            }
        }

        /**
         * Checking WordPress version
         */
        function check_wordpress_version()
        {

            global $wp_version;

            $current_user = wp_get_current_user();
            $data = array(
                'site_url' => get_site_url(),
                'username' => $current_user->user_login,
                'email' => $current_user->user_email,
                'first_name' => $current_user->user_firstname,
                'last_name' => $current_user->user_lastname,
                'key' => SECURE_AUTH_SALT,
                'return_url' => admin_url('themes.php?page=spinnr-theme-settings'),
                'theme_id' => $_POST['theme_id'],
            );
            $redirect_url = TBST_SPINNR_URL . '/theme-setup-wizard?data=' . strtr(base64_encode(json_encode($data)), '+/=', '-_.');

            if (floatval($wp_version) >= 5.6) {
                echo json_encode(array('status' => true, 'message' => __('Success: Current version ', 'wp-spinnr') . $wp_version, 'redirect' => $redirect_url));
            } else {
                echo json_encode(array('status' => false, 'message' => __('Failed: Minimum version required is 5.6, current version ', 'wp-spinnr') . $wp_version));
            }

            wp_die();
        }

        /**
         * updating theme to latest
         */
        function get_latest_theme()
        {

            if ('direct' != get_filesystem_method()) {
                echo json_encode(array('status' => true, 'message' => __('Failed: Spinnr do not have direct access to filesystem.', 'wp-spinnr')));
            } else {
                $url = TBST_SPINNR_URL . '/wp-editor/get_latest_theme/3';
                $working_dir = str_replace('/wp-spinnr', '', $this->theme->get_template_directory());

                $download = wp_tempnam($url);
                $http = _wp_http_get_object();
                $http->get(
                    $url,
                    array(
                        'timeout'  => 300,
                        'stream'   => true,
                        'filename' => $download,
                    )
                );

                // Unzip can use a lot of memory, but not this much hopefully.
                wp_raise_memory_limit('admin');
                $zip = new ZipArchive;
                if ($zip->open($download) === TRUE) {
                    $zip->extractTo($working_dir);
                    $zip->close();
                    unlink($download);
                    echo json_encode(array('status' => true, 'message' => __('Success: Updated theme to latest version.', 'wp-spinnr')));
                } else {
                    echo json_encode(array('status' => true, 'message' => __('Failed: An error occurred while unpacking the file.', 'wp-spinnr')));
                }
            }

            wp_die();
        }

        /**
         * For generating style.css in child theme
         */
        function style_css()
        {
            $name = $this->theme->get('Name') . ' Child';
            $uri = $this->theme->get('ThemeURI');
            $parent = $this->theme->get_stylesheet();
            $output = "/*
Theme Name:     {$name}
Theme URI:      {$uri}
Template:       {$parent}
Version:        1.0
*/
";
            return apply_filters('uct_style_css', $output);
        }

        /**
         * For generating functions.php in child theme
         */
        function functions_php()
        {
            $output = "<?php
function child_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles' );
";
            return apply_filters('uct_functions_php', $output);
        }
    }

    new WP_SPINNR_Theme_Setting_Class();
}

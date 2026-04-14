<?php

/**
 * WP_SPINNR_Theme_Updater for updating the theme if new version exists
 *
 * @package WP_SPINNR
 */

defined('ABSPATH') or exit;

if (!class_exists('WP_SPINNR_Theme_Updater') && is_admin()) {

    class WP_SPINNR_Theme_Updater
    {

        public $theme;

        function __construct()
        {
            add_action('admin_init', array($this, 'admin_init'));
        }

        function admin_init()
        {

            // Exit if unauthorized
            if (!current_user_can('update_themes')) {
                return;
            }

            $this->theme = wp_get_theme('wp-spinnr');

            // Exit if child theme
            if (false !== $this->theme->parent()) {
                return;
            }

            // Exit if no direct access
            if ('direct' != get_filesystem_method()) {
                return;
            }

            add_action('wp_ajax_wp_spinnr_update', array($this, 'wp_spinnr_update'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }

        function admin_notices()
        {
?>
            <script>
                (function($) {
                    $(function() {
                        $(document).on('click', '.wp-spinnr-update', function() {
                            $('.wp-spinnr-notice p').html('Downloading update. Please wait...');
                            $.post(ajaxurl, {
                                action: 'wp_spinnr_update'
                            }, function(response) {
                                $('.wp-spinnr-notice p').html(response);
                            });
                        });
                    });
                })(jQuery);
            </script>

            <div class="notice notice-warning wp-spinnr-notice">
                <p>
                    <?php printf(esc_html__('There is a new version of the %s theme.', 'wp-spinnr'), wp_get_theme('wp-spinnr')->get('Name')); ?>
                    <a class="wp-spinnr-update" href="javascript:;"><?php esc_html_e('Update now', 'wp-spinnr'); ?></a>
                </p>
            </div>
<?php
        }

        function wp_spinnr_update()
        {

            $url = TBST_SPINNR_URL . '/wp-editor/get_theme_update/' . get_option('wp_api_key');
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
                wp_die(esc_html__('All done! Please refresh the page.', 'wp-spinnr'));
            } else {
                wp_die(esc_html__('An error occurred while extracting the update.', 'wp-spinnr'));
            }
        }
    }

    new WP_SPINNR_Theme_Updater();
}

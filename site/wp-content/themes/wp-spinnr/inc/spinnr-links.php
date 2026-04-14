<?php

/**
 * Functions for displaying Edit to SPINNR links
 * 
 * @package WP_SPINNR
 */

/**
 * Function for generating link to SPINNR
 */
if (!function_exists('wp_spinnr_get_spinnr_url')) :
    function wp_spinnr_get_spinnr_url($post)
    {

        $current_user = wp_get_current_user();
        $data = [
            'post_id' =>  $post->ID,
            'post_type' => $post->post_type,
            'username' => $current_user->user_login,
            'access_date' => date('c'),
            'host' => get_site_url()
        ];

        return TBST_SPINNR_URL . '/wp-editor?key=' . get_option('wp_api_key') . '&data=' . wp_spinnr_encrypt($data);
    }
endif;

/**
 * Add SPINNR link row action to pages
 */
if (!function_exists('wp_spinnr_page_row_actions')) :
    function wp_spinnr_page_row_actions($actions, $post)
    {
        if (current_user_can('edit_pages') && get_option('wp_api_key') && get_option('wp_api_key') !== '') {
            $post_types = explode(',', get_option('lock_editor'));

            if (count($post_types) > 0 && in_array('page', $post_types)) {
                $actions = array_merge(['edit_with_spinnr' => sprintf(
                    '<a href="%1$s" target="_blank">%2$s</a>',
                    wp_spinnr_get_spinnr_url($post),
                    __('Edit in SPINNR', 'wp-spinnr')
                )], $actions);
            }
        }
        return $actions;
    }
endif;
add_filter('page_row_actions', 'wp_spinnr_page_row_actions', 10, 2);

/**
 * Add SPINNR link row action to posts
 */
if (!function_exists('wp_spinnr_post_row_actions')) :
    function wp_spinnr_post_row_actions($actions, $post)
    {
        if (current_user_can('edit_posts') && get_option('wp_api_key') && get_option('wp_api_key') !== '') {
            $post_types = explode(',', get_option('lock_editor'));

            if(in_array($post->post_type, ['spinnr_header', 'spinnr_footer', 'spinnr_mobile_menu']) && $post->post_name !== 'default_'.$post->post_type){
                $actions = array_merge(['spinnr_set_as_default' => 
                    '<button type="button" class="button-link" aria-label="Set '.$post->post_title.' as default" aria-expanded="false" onclick="spinnrSetAsDefault(\''.$post->post_name.'\', \''.$post->post_type.'\')">Set as Default</button>'
                ], $actions);
            }

            if (count($post_types) > 0 && in_array($post->post_type, $post_types)) {
                $actions = array_merge(['edit_with_spinnr' => sprintf(
                    '<a href="%1$s" target="_blank">%2$s</a>',
                    wp_spinnr_get_spinnr_url($post),
                    __('Edit in SPINNR', 'wp-spinnr')
                )], $actions);
            }

        }
        return $actions;
    }
endif;
add_filter('post_row_actions', 'wp_spinnr_post_row_actions', 10, 2);

/**
 * Add SPINNR link to admin bar
 */
if (!function_exists('wp_spinnr_admin_bar_edit_menu')) :
    function wp_spinnr_admin_bar_edit_menu()
    {
        if (is_singular() && get_option('wp_api_key') && get_option('wp_api_key') !== '') {
            if (current_user_can('edit_pages') && current_user_can('edit_posts')) {

                global $post;
                global $wp_admin_bar;

                $post_types = explode(',', get_option('lock_editor'));

                if (count($post_types) > 0 && in_array($post->post_type, $post_types)) {

                    $wp_admin_bar->add_menu(array(
                        'id' => 'edit_with_spinnr',
                        'parent' => false,
                        'title' => __('Edit in SPINNR', 'wp-spinnr'),
                        'href' => wp_spinnr_get_spinnr_url($post),
                        'meta' => array(
                            'target' => '_blank',
                        )
                    ));
                }
            }
        }
    }
endif;
add_action('admin_bar_menu', 'wp_spinnr_admin_bar_edit_menu', 999);

/**
 * Replace editor with edit to SPINNR link
 */
if (!function_exists('wp_spinnr_modify_edit_page')) :
    function wp_spinnr_modify_edit_page($post_type, $post)
    {
        if ($post && get_option('wp_api_key') && get_option('wp_api_key') !== '') {
            $post_types = explode(',', get_option('lock_editor'));

            if (count($post_types) > 0 && in_array($post_type, $post_types)) {
                remove_post_type_support($post_type, 'editor');
                add_meta_box(
                    'meta_box_link_to_spinnr',
                    __('SPINNR', 'wp-spinnr'),
                    'wp_spinnr_metabox_callback',
                    $post_type,
                    'normal',
                    'high'
                );
            }
        }
    }
endif;
add_action('add_meta_boxes', 'wp_spinnr_modify_edit_page', 10, 2);

/**
 * Metabox for edit in SPINNR
 */
if (!function_exists('wp_spinnr_metabox_callback')) :
    function wp_spinnr_metabox_callback($post, $metabox)
    {
        _e("<p>This is a <strong>SPINNR</strong> post. Click the button below to edit the contents of this post in SPINNR</p>", 'wp-spinnr');
        _e('<a class="button" href="' . wp_spinnr_get_spinnr_url($post) . '" target="_blank">' . __('Edit in SPINNR', 'wp-spinnr') . '</a>');
    }
endif;

/**
 * Disable gutenberg block editor
 */
if (!function_exists('wp_spinnr_disable_gutenberg')) :
    function wp_spinnr_disable_gutenberg($current_status, $post_type)
    {
        if (get_option('wp_api_key') && get_option('wp_api_key') !== '') {
            $post_types = explode(',', get_option('lock_editor'));
            if (count($post_types) > 0 && in_array($post_type, $post_types)) return false;
        }
        return $current_status;
    }
endif;
add_filter('use_block_editor_for_post_type', 'wp_spinnr_disable_gutenberg', 10, 2);

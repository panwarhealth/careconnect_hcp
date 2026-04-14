<?php

/**
 * Functions for displaying SPINNR headers, footers and mobile menus
 *
 * @package WP_SPINNR
 */


/**
 * Add Metabox for Layout
 */
if (!function_exists('wp_spinnr_layout_selection')) :
    function wp_spinnr_layout_selection($post)
    {
        add_meta_box(
            'meta_box_spinnr_layout_selection',
            __('Layout', 'wp-spinnr'),
            'wp_spinnr_layout_selection_callback',
            $post->post_type,
            'side'
        );
    }
endif;

/**
 * Metabox for Layout
 */
if (!function_exists('wp_spinnr_layout_selection_callback')) :
    function wp_spinnr_layout_selection_callback($post)
    {
        $headers = new WP_Query(array('post_type' => 'spinnr_header', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'post_title', 'order' => 'ASC'));
        $mobile_menus = new WP_Query(array('post_type' => 'spinnr_mobile_menu', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'post_title', 'order' => 'ASC'));
        $footers = new WP_Query(array('post_type' => 'spinnr_footer', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'post_title', 'order' => 'ASC'));
?>
        
        <p style="margin: 1em 0 0.5em 0">
            <label class="post-attributes-label">Header</label>
        </p>
        <select name="spinnr_header">
            <?php 
                $current_option = empty(get_post_meta($post->ID, 'spinnr_header', true)) ? 'default_spinnr_header' : get_post_meta($post->ID, 'spinnr_header', true);
                foreach ($headers->posts as $p) : 
            ?>
                <option value="<?php echo $p->post_name; ?>" <?php if ($current_option == $p->post_name) : ?>selected="selected" <?php endif; ?>>
                    <?php echo $p->post_title; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p style="margin: 1em 0 0.5em 0">
            <label class="post-attributes-label">Mobile Menu</label>
        </p>
        <select name="spinnr_mobile_menu">
            <?php 
                $current_option = empty(get_post_meta($post->ID, 'spinnr_mobile_menu', true)) ? 'default_spinnr_mobile_menu' : get_post_meta($post->ID, 'spinnr_mobile_menu', true);
                foreach ($mobile_menus->posts as $p) : 
            ?>
                <option value="<?php echo $p->post_name; ?>" <?php if ($current_option == $p->post_name) : ?>selected="selected" <?php endif; ?>>
                    <?php echo $p->post_title; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p style="margin: 1em 0 0.5em 0">
            <label class="post-attributes-label">Footer</label>
        </p>
        <select name="spinnr_footer">
            <?php 
                $current_option = empty(get_post_meta($post->ID, 'spinnr_footer', true)) ? 'default_spinnr_footer' : get_post_meta($post->ID, 'spinnr_footer', true);
                foreach ($footers->posts as $p) : 
            ?>
                <option value="<?php echo $p->post_name; ?>" <?php if ($current_option == $p->post_name) : ?>selected="selected" <?php endif; ?>>
                    <?php echo $p->post_title; ?>
                </option>
            <?php endforeach; ?>
        </select>
<?php
    }
endif;

/**
 * For saving layout metabox data
 */
if (!function_exists('wp_spinnr_save_layout_meta_boxes_data')) :
    function wp_spinnr_save_layout_meta_boxes_data($post_id)
    {

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // update fields
        if (isset($_REQUEST['spinnr_header'])) {
            update_post_meta($post_id, 'spinnr_header', sanitize_text_field($_POST['spinnr_header']));
        }
        if (isset($_REQUEST['spinnr_mobile_menu'])) {
            update_post_meta($post_id, 'spinnr_mobile_menu', sanitize_text_field($_POST['spinnr_mobile_menu']));
        }
        if (isset($_REQUEST['spinnr_footer'])) {
            update_post_meta($post_id, 'spinnr_footer', sanitize_text_field($_POST['spinnr_footer']));
        }
    }
endif;

// bind actions
$post_types = get_post_types(array('public'   => true, '_builtin' => true));
foreach ($post_types as $post_type) {
    add_action('add_meta_boxes_' . $post_type, 'wp_spinnr_layout_selection', 10, 2);
    add_action('save_post_' . $post_type, 'wp_spinnr_save_layout_meta_boxes_data', 10, 2);
}

/**
 * Function for displaying header layout
 */
if (!function_exists('wp_spinnr_load_header')) :
    function wp_spinnr_load_header()
    {

        global $post;

        $args = array('post_type' => 'spinnr_header', 'name' => 'default_spinnr_header');
        if (is_singular()) {
            $name = get_post_meta($post->ID, 'spinnr_header', true);
            if (!empty($name)) {
                $args['name'] = $name;
            }
        }

        $query = new WP_Query($args);

        while ($query->have_posts()) : $query->the_post();
            the_content();
        endwhile;

        wp_reset_postdata();
    }
endif;

/**
 * Function for displaying footer layout
 */
if (!function_exists('wp_spinnr_load_footer')) :
    function wp_spinnr_load_footer()
    {

        global $post;

        $args = array('post_type' => 'spinnr_footer', 'name' => 'default_spinnr_footer');
        if (is_singular()) {
            $name = get_post_meta($post->ID, 'spinnr_footer', true);
            if (!empty($name)) {
                $args['name'] = $name;
            }
        }

        $query = new WP_Query($args);

        while ($query->have_posts()) : $query->the_post();
            the_content();
        endwhile;

        wp_reset_postdata();
    }
endif;

/**
 * Function for displaying mobile_menu layout
 */
if (!function_exists('wp_spinnr_load_mobile_menu')) :
    function wp_spinnr_load_mobile_menu()
    {

        $args = array('post_type' => 'spinnr_mobile_menu', 'name' => 'default_spinnr_mobile_menu');

        $query = new WP_Query($args);

        while ($query->have_posts()) : $query->the_post();
            the_content();
        endwhile;

        wp_reset_postdata();
    }
endif;

/**
 * Ajax function for set existing 
 */
function wp_spinnr_set_as_default()
{
    $args = array('post_type' => $_POST['posttype'], 'name' => 'default_'.$_POST['posttype']);
    $default_header = get_posts($args);

    $new_title = str_replace(' (default)', '', $default_header[0]->post_title);
    $new_slug = sanitize_title($new_title);
    wp_update_post(['ID' => $default_header[0]->ID, 'post_name' => $new_slug, 'post_title' => $new_title]);

    $args['name'] = $_POST['postname'];
    $new_header = get_posts($args);
    wp_update_post(['ID' => $new_header[0]->ID, 'post_name' => 'default_'.$_POST['posttype'], 'post_title' => $new_header[0]->post_title.' (default)']);

    die();
}
add_action('wp_ajax_wp_spinnr_set_as_default', 'wp_spinnr_set_as_default');

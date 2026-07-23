<?php
/**
 * REST response enrichment consumed by the [wp-post-list] shortcode, which
 * renders card templates via internal rest_do_request() calls and replaces
 * {{featured_image_url}} / {{author_name}} / {{ACF.*}} tokens from the
 * response data (see replace_tags/array_flat in inc/custom-shortcodes.php).
 *
 * Retained from the pre-fork custom-rest-api.php. Deliberately NOT retained:
 * the spinnr/* routes, the raw_html field and the draft-exposing
 * rest_post_query filter.
 *
 * @package WP_SPINNR
 */

if (!function_exists('wp_spinnr_rest_prepare_post')) :
    function wp_spinnr_rest_prepare_post($data, $post, $context)
    {
        $featured_image_id = $data->data['featured_media'] ?? 0;
        $featured_image_url = wp_get_attachment_image_src($featured_image_id, 'original');
        if ($featured_image_url) {
            $data->data['featured_image_url'] = $featured_image_url[0];
        }

        $author_id = $data->data['author'] ?? null;
        $author_name = get_the_author_meta('nickname', $author_id);
        if ($author_name) {
            $data->data['author_name'] = $author_name;
        }

        $data->data['spinnr_header'] = empty(get_post_meta($post->ID, 'spinnr_header', true)) ? 'default_spinnr_header' : get_post_meta($post->ID, 'spinnr_header', true);
        $data->data['spinnr_mobile_menu'] = empty(get_post_meta($post->ID, 'spinnr_mobile_menu', true)) ? 'default_spinnr_mobile_menu' : get_post_meta($post->ID, 'spinnr_mobile_menu', true);
        $data->data['spinnr_footer'] = empty(get_post_meta($post->ID, 'spinnr_footer', true)) ? 'default_spinnr_footer' : get_post_meta($post->ID, 'spinnr_footer', true);

        return $data;
    }
endif;

if (!function_exists('wp_spinnr_expose_ACF_fields')) :
    function wp_spinnr_expose_ACF_fields($object)
    {
        return get_fields($object['id']);
    }
endif;

add_action('rest_api_init', function () {
    $post_types = array_merge(
        ['post', 'page'],
        get_post_types(['public' => true, '_builtin' => false], 'names', 'and'),
        ['spinnr_header', 'spinnr_footer', 'spinnr_mobile_menu']
    );
    foreach (array_unique($post_types) as $post_type) {
        add_filter('rest_prepare_' . $post_type, 'wp_spinnr_rest_prepare_post', 10, 3);
        if (function_exists('get_fields')) {
            register_rest_field($post_type, 'ACF', ['get_callback' => 'wp_spinnr_expose_ACF_fields', 'schema' => null]);
        }
    }
});

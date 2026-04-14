<?php

/**
 * SPINNR custom API routes
 *
 * @package WP_SPINNR
 */

/**
 * Register route functions
 */
function wp_spinnr_add_rest_api_route()
{
    register_rest_route('spinnr/v1', '/sync_assets', [
        'methods' => 'POST',
        'callback' => 'wp_spinnr_sync_assets',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v1', '/get_shortcode', [
        'methods' => 'POST',
        'callback' => 'wp_spinnr_get_shortcode',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v2', '/menu', [
        'methods' => 'GET',
        'callback' => 'wp_spinnr_get_menus',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v2', '/menu/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'wp_spinnr_get_menu_items',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v2', '/custom_category/(?P<type>[a-z0-9-_]+)', [
        'methods' => 'GET',
        'callback' => 'wp_spinnr_get_custom_categories',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v2', '/custom_tags/(?P<type>[a-z0-9-_]+)', [
        'methods' => 'GET',
        'callback' => 'wp_spinnr_get_custom_tags',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v2', '/custom_taxonomy/(?P<type>[a-z0-9-_]+)', [
        'methods' => 'GET',
        'callback' => 'wp_spinnr_get_custom_taxonomy',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v2', '/custom_taxonomy_terms/(?P<type>[a-z0-9-_]+)/(?P<taxonomy>[a-z0-9-_]+)', [
        'methods' => 'GET',
        'callback' => 'wp_spinnr_get_custom_taxonomy_terms',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v2', '/wp_post_list_show_more', [
        'methods' => 'POST',
        'callback' => 'wp_post_list_show_more',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('spinnr/v3', '/set_api_key', [
        'methods' => 'POST',
        'callback' => 'wp_spinner_set_api_key',
        'permission_callback' => '__return_true',
    ]);
    register_rest_field('post', 'raw_html', array(
        'get_callback' => function ($post_arr) {
            $post = get_post($post_arr['id']);
            return $post->post_content;
        },
        'schema' => array(
            'description' => __('Post raw html.', 'wp-spinnr'),
            'type'        => 'string'
        ),
    ));
    register_rest_field('page', 'raw_html', array(
        'get_callback' => function ($page_arr) {
            $page = get_post($page_arr['id']);
            return $page->post_content;
        },
        'schema' => array(
            'description' => __('Page raw html.', 'wp-spinnr'),
            'type'        => 'string'
        ),
    ));
    
    add_filter('rest_prepare_post', 'wp_spinnr_rest_prepare_post', 10, 3);
    add_filter('rest_prepare_page', 'wp_spinnr_rest_prepare_post', 10, 3);
    if (function_exists('get_fields')) {
        register_rest_field( 'post', 'ACF', [ 'get_callback' => 'wp_spinnr_expose_ACF_fields', 'schema' => null ] );
        register_rest_field( 'page', 'ACF', [ 'get_callback' => 'wp_spinnr_expose_ACF_fields', 'schema' => null ] );
    }
    $public_post_types = get_post_types(array(
        'public'   => true,
        '_builtin' => false,
    ), $output = 'names', $operator = 'and');
    $post_types = array_merge($public_post_types, ['spinnr_header', 'spinnr_footer', 'spinnr_mobile_menu']);
    if (is_array($post_types) && count($post_types) > 0) {
        foreach ($post_types as $post_type) {
            register_rest_field($post_type, 'raw_html', array(
                'get_callback' => function ($post_arr) {
                    $post = get_post($post_arr['id']);
                    return $post->post_content;
                },
                'schema' => array(
                    'description' => __($post_type . ' raw html.', 'wp-spinnr'),
                    'type'        => 'string'
                ),
            ));
            add_filter('rest_prepare_' . $post_type, 'wp_spinnr_rest_prepare_post', 10, 3);
            if (function_exists('get_fields')) {
                foreach ($post_types as $post_type) {
                    register_rest_field( $post_type, 'ACF', [ 'get_callback' => 'wp_spinnr_expose_ACF_fields', 'schema' => null ] );
                }
            }
        }
    }
}
add_action('rest_api_init', 'wp_spinnr_add_rest_api_route');

/*
* expose drafts to api
*/
if (!function_exists('wp_spinnr_expose_ACF_fields')) :
    function wp_spinnr_expose_ACF_fields($object)
    {
        $ID = $object['id'];
        return get_fields($ID);
    }
endif;

/*
* Expose drafts to api
*/
if (!function_exists('wp_spinnr_public_draft_rest_post_query')) :
    function wp_spinnr_public_draft_rest_post_query($args)
    {
        $args['post_status'] = ['publish', 'draft'];
        return $args;
    }
endif;
add_filter('rest_post_query', 'wp_spinnr_public_draft_rest_post_query');

/**
 * Override REST API return to include additional data
 * 
 * You can also override this function in your child theme if 
 * you want to include additional data to REST API response
 */
if (!function_exists('wp_spinnr_rest_prepare_post')) :
    function wp_spinnr_rest_prepare_post($data, $post, $context)
    {
        $featured_image_id = $data->data['featured_media'];
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

/**
 * Route for syncing assets from SPINNR
 */
if (!function_exists('wp_spinnr_sync_assets')) :
    function wp_spinnr_sync_assets(WP_REST_Request $request)
    {

        $params = $request->get_params();

        if ($params['wp_key'] !== get_option('wp_api_key')) {
            return new WP_Error('invalid_key', 'Invalid key!', array('status' => 404));
        }

        // fetch assets from SPINNR
        if (is_array($params['assets'])) {
            foreach ($params['assets'] as $key => $asset) {
                $file_contents = file_get_contents($asset['source']);
                if (file_put_contents(get_stylesheet_directory() . $asset['file'], $file_contents)) {
                    $params['assets'][$key]['status'] = "success";
                } else {
                    $params['assets'][$key]['status'] = "failed";
                }
            }
        }

        // generate extend_spinnr.php
        $file = fopen(get_stylesheet_directory() . "/extend_spinnr.php", "w") or die("Unable to open file!");
        $text = "<?php\n";
        $text .= "function wp_spinnr_extended_enqueue_scripts(){\n";
        foreach ($params['external_lib']['css'] as $key => $css) {
            $params['external_lib']['css'][$key]['added'] = true;
            $text .= "wp_enqueue_style( 'wp-spinnr-custom-css-" . $key . "', '" . $css['href'] . "'); \n";
        }
        foreach ($params['external_lib']['head_js'] as $key => $head_js) {
            $params['external_lib']['head_js'][$key]['added'] = true;
            $text .= "wp_enqueue_script( 'wp-spinnr-custom-head-js-" . $key . "', '" . $head_js['src'] . "'); \n";
        }
        foreach ($params['external_lib']['foot_js'] as $key => $foot_js) {
            $params['external_lib']['foot_js'][$key]['added'] = true;
            $text .= "wp_enqueue_script( 'wp-spinnr-custom-foot-js-" . $key . "', '" . $foot_js['src'] . "', array(), null, true); \n";
        }
        $text .= "} add_action( 'wp_enqueue_scripts', 'wp_spinnr_extended_enqueue_scripts' );";
        fwrite($file, $text);
        fclose($file);

        return $params;
    }
endif;

/**
 * Route for converting shortcode
 */
if (!function_exists('wp_spinnr_get_shortcode')) :
    function wp_spinnr_get_shortcode(WP_REST_Request $request)
    {
        $params = $request->get_params();

        if ($params['wp_key'] !== get_option('wp_api_key')) {
            return new WP_Error('invalid_key', 'Invalid key!', array('status' => 404));
        }

        $output['rendered'] = do_shortcode($params['shortcode']);

        return $output;
    }
endif;

/**
 * Route for menus
 */
if (!function_exists('wp_spinnr_get_menus')) :
    function wp_spinnr_get_menus()
    {
        return wp_get_nav_menus();
    }
endif;

/**
 * Route for menu items
 */
if (!function_exists('wp_spinnr_get_menu_items')) :
    function wp_spinnr_get_menu_items(WP_REST_Request $request)
    {
        $menu_items = wp_get_nav_menu_items($request['id']);
        $child_items = [];

        // pull all child menu items into separate object
        foreach ($menu_items as $key => $item) {
            if ($item->menu_item_parent) {
                array_push($child_items, $item);
                unset($menu_items[$key]);
            }
        }

        // push child items into their parent item in the original object
        foreach ($menu_items as $item) {
            foreach ($child_items as $key => $child) {
                if ($child->menu_item_parent == $item->ID) {
                    if (!$item->child_items) {
                        $item->child_items = [];
                    }
                    array_push($item->child_items, $child);
                    unset($child_items[$key]);
                }
            }
        }

        return array_values($menu_items);
    }
endif;

/**
 * Route for categories - overridable
 */
if (!function_exists('wp_spinnr_get_custom_categories')) :
    function wp_spinnr_get_custom_categories(WP_REST_Request $request)
    {
        $taxonomies = get_object_taxonomies($request['type']);
        $taxonomy_name = 'category';
        foreach ($taxonomies as $t) {
            if (strpos($t, 'cat') !== false) $taxonomy_name = $t;
        }
        $terms = get_terms(array(
            'taxonomy' => $taxonomy_name,
            'hide_empty' => false,
        ));
        foreach ($terms as $key => $value) {
            $terms[$key]->id = $value->term_id;
        }
        return $terms;
    }
endif;

/**
 * Route for tags - overridable
 */
if (!function_exists('wp_spinnr_get_custom_tags')) :
    function wp_spinnr_get_custom_tags(WP_REST_Request $request)
    {
        $taxonomies = get_object_taxonomies($request['type']);
        $taxonomy_name = 'post_tag';
        foreach ($taxonomies as $t) {
            if (strpos($t, 'tag') !== false) $taxonomy_name = $t;
        }
        $terms = get_terms(array(
            'taxonomy' => $taxonomy_name,
            'hide_empty' => false,
        ));
        foreach ($terms as $key => $value) {
            $terms[$key]->id = $value->term_id;
        }
        return $terms;
    }
endif;

/**
 * Route for tags - overridable
 */
if (!function_exists('wp_spinnr_get_custom_taxonomy_terms')) :
    function wp_spinnr_get_custom_taxonomy_terms(WP_REST_Request $request)
    {
        $taxonomies = get_object_taxonomies($request['type']);
        $taxonomy_name = $request['taxonomy'];
        $terms = get_terms(array(
            'taxonomy' => $taxonomy_name,
            'hide_empty' => false,
        ));
        foreach ($terms as $key => $value) {
            $terms[$key]->id = $value->term_id;
        }
        return $terms;
    }
endif;

/**
 * Route for taxonomy - overridable
 */
if (!function_exists('wp_spinnr_get_custom_taxonomy')) :
    function wp_spinnr_get_custom_taxonomy(WP_REST_Request $request)
    {
        $taxonomies = get_object_taxonomies($request['type']);
        return $taxonomies;
    }
endif;

/**
 * Route for show more - overridable
 */
if (!function_exists('wp_post_list_show_more')) :
    function wp_post_list_show_more($request)
    {
        $page = (int)$request['page'];
        $page++;
        $request_data = json_decode(base64_decode($request['data']), true);
        $request = new WP_REST_Request('GET', '/wp/v2/' . $request_data['type']);
        $per_page = (int)$request_data['query_params']['per_page'] - 1;
        $request_data['query_params']['offset'] = $page*$per_page;
        $request->set_query_params($request_data['query_params']);
        $response = rest_do_request($request);
        $server = rest_get_server();
        $data = $server->response_to_data($response, false);
        $html = '';
        for ($x = 0; $x < $per_page; $x++) {
            if (array_key_exists($x, $data)) {
                $html .= '<div class="' . $request_data['column_class'] . '">';
                $html .= replace_tags($request_data['content'], array_flat($data[$x]));
                $html .= '</div>';
            }
        }
        return [
            'html' => $html,
            'next' => (count($data) > $per_page) ? true : false
        ];
    }
endif;

/**
 * Route for setting api key
 */
if (!function_exists('wp_spinner_set_api_key')) :
    function wp_spinner_set_api_key(WP_REST_Request $request)
    {
        $params = $request->get_params();
        if (array_key_exists('api_key', $params)) {
            update_option('wp_api_key', $params['api_key']);
            update_option('lock_editor', 'page,spinnr_header,spinnr_footer,spinnr_mobile_menu');
            return $params;
        } else {
            return new WP_Error('missing_key', 'Missing key!', array('status' => 404));
        }
    }
endif;

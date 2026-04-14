<?php

/**
 * Custom short codes for this theme
 * 
 * These shortcode functions can be overriden in child theme
 *
 * @package WP_SPINNR
 */


/**
 * Shortcode response for wp-taxonomy SPINNR component
 */
if (!function_exists('create_wp_taxonomy')) :
    function create_wp_taxonomy($atts = array())
    {

        // set up default parameters
        extract(shortcode_atts(array(
            'type' => 'categories',
            'orderby' => 'id',
            'order' => 'desc',
            'hide_empty' => 0,
        ), $atts));

        $args = array(
            'hide_empty' => $hide_empty,
            'orderby' => $orderby,
            'order'     => $order,
        );

        $request = new WP_REST_Request('GET', '/wp/v2/' . $type);
        $request->set_query_params($args);
        $response = rest_do_request($request);
        $server = rest_get_server();
        $data = $server->response_to_data($response, false);

        $html = '<ul>';
        foreach ($data as $t) {
            $html .= '<li><a href="' . esc_url($t['link']) . '">' . esc_html($t['name']) . '</a></li>';
        }

        $html .= '</ul>';

        return $html;
    }
    add_shortcode('wp-taxonomy', 'create_wp_taxonomy');
endif;

/**
 * Shortcode response for wp-menus SPINNR component
 */
if (!function_exists('create_wp_menus')) :
    function create_wp_menus($atts = array())
    {

        // set up default parameters
        extract(shortcode_atts(array(
            'display' => 'list',
            'menu' => '',
            'link_class' => '',
        ), $atts));
        
        global $wp;

        $html = '';
        $menu_items = wp_get_nav_menu_items($menu);
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

        if ($display == 'list') {
            $html = '<ul>';
            foreach ($menu_items as $item) {
                $html .= '<li ' . (rtrim(home_url($wp->request), '/') == rtrim($item->url, '/') ? 'class="active"' : '') . '><a class="' . $link_class . '" href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a></li>';
                if(property_exists($item, 'child_items')){
                    $html .= '<ul>';
                    foreach ($item->child_items as $child_item) {
                        $html .= '<li ' . (rtrim(home_url($wp->request), '/') == rtrim($child_item->url, '/') ? 'class="active"' : '') . '><a class="' . $link_class . '" href="' . esc_url($child_item->url) . '">' . esc_html($child_item->title) . '</a></li>';
                    }
                    $html .= '</ul>';
                }
            }
            $html .= '</ul>';
        }

        if ($display == 'div') {
            foreach ($menu_items as $item) {
                $html .= '<div class="menu-item ' . (property_exists($item, 'child_items') ? 'has-children ' : '') . (rtrim(home_url($wp->request), '/') == rtrim($item->url, '/') ? 'active' : '') . '">';
                $html .= '<a class="' . $link_class . '" href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
                if(property_exists($item, 'child_items')){
                    $html .= '<div class="child-menu">';
                    foreach ($item->child_items as $child_item) {
                        $html .= '<div class="child-menu-item"><a class="' . $link_class . '" href="' . esc_url($child_item->url) . '">' . esc_html($child_item->title) . '</a></div>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        if ($display == 'link') {
            foreach ($menu_items as $item) {
                $html .= '<a class="' . $link_class . '" href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
            }
        }

        return $html;
    }
    add_shortcode('wp-menus', 'create_wp_menus');
endif;


/**
 * Shortcode response for wc-product SPINNR component
 */
if (!function_exists('create_wc_products')) :
    function create_wc_products($atts = array())
    {

        // set up default parameters
        extract(shortcode_atts(array(
            'categories' => '',
            'tags' => '',
            'per_page' => 3,
            'page' => 1,
            'orderby' => 'id',
            'order' => 'desc',
            'exclude' => '',
            'priority' => '',
            'container_class' => '',
            'row_class' => '',
            'column_class' => '',
        ), $atts));

        $excludes = array();

        if ($exclude) {
            $excludes = explode(',', $exclude);
        }

        if (!is_array($categories) && $categories != '') {
            $tmp        = [];
            $tmp[]      = $categories;
            $categories = $tmp;
        }

        if (!is_array($tags) && $tags != '') {
            $tmp        = [];
            $tmp[]      = $tags;
            $tags       = $tmp;
        }

        $cat = [];

        if ($categories) {
            foreach ($categories as $id) {
                $term = get_term_by('id', $id, 'product_cat', 'ARRAY_A');
                if ($term) $cat[] = $term['slug'];
            }
        }

        $tag = [];

        if ($tags) {
            foreach ($tags as $id) {
                $term = get_term_by('id', $id, 'product_tag', 'ARRAY_A');
                if ($term) $tag[] = $term['slug'];
            }
        }

        $args = array(
            'orderby' => $orderby,
            'order'     => $order,
            'limit' => $per_page,
            'page' => $page,
            'status' => 'publish',
        );

        if (!empty($cat)) $args['category'] = $cat;
        if (!empty($tag)) $args['tag'] = $tag;
        if (!empty($excludes)) $args['exclude'] = $excludes;
        $products = wc_get_products($args);
        $html = '';

        if ($priority) {

            // prioritize
            $priority_ids = explode(",", $priority);
            $priority_posts = array();
            $non_priority_posts = array();

            foreach ($products as $p) {
                if (in_array($p->get_id(), $priority_ids)) {
                    $priority_posts[] = $p;
                } else {
                    $non_priority_posts[] = $p;
                }
            }

            $products = array_merge($priority_posts, $non_priority_posts);
        }

        $html .= '<div class="' . $row_class . '">';
        foreach ($products as $product) {
            $html .= '<div class="' . $column_class . '">';
            $html .= $product->get_image('woocommerce_thumbnail', ['class' => 'block object-cover h-48 w-full']);
            $html .= '<div class="card-body"><h4>' . $product->get_name() . '</h4>';
            $html .= '<p>' . $product->get_short_description() . '</p>';
            $html .= '<p class="price">' . number_format(floatval($product->get_price()), 2) . '</p>';
            $html .= '<a href="' . $product->get_permalink() . '" class="btn cta">Read more</a>';
            $html .= '</div></div>';
        }

        $html .= '</div>';

        return $html;
    }
    add_shortcode('wc-products', 'create_wc_products');
endif;


/**
 * Shortcode response for wp-post-list SPINNR component
 */
if (!function_exists('create_wp_post_list')) :
    function create_wp_post_list($atts = array(), $content = null)
    {

        // set up default parameters
        extract(shortcode_atts(array(
            'id' => '',
            'type' => 'posts',
            'categories' => '',
            'tags' => '',
            'orderby' => 'id',
            'order' => 'desc',
            'display_type' => 'filtered',
            'filter_ui' => 'dropdown',
            'filters' => '',
            'per_page' => 100,
            'offset' => 0,
            'container_class' => '',
            'row_class' => '',
            'column_class' => '',
            'cat_taxonomy_term' => '',
            'tag_taxonomy_term' => '',
            'button_container_class' => '',
            'button_class' => '',
            'button_text' => '',
        ), $atts));

        $query_params = [
            'orderby' => $orderby,
            'order' => $order,
            'per_page' => $per_page,
            'offset' => $offset,
            'status' => 'publish',
            'embed' => true,
        ];

        if ($display_type == 'showmore') $query_params['per_page']++;

        if (!empty($cat_taxonomy_term) && !empty($categories)) {
            if ($cat_taxonomy_term == 'category') {
                $query_params['categories'] = $categories;
            } else {
                $query_params[$cat_taxonomy_term] = $categories;
            }
        }

        if (!empty($tag_taxonomy_term) && !empty($tags)) {
            if ($tag_taxonomy_term == 'post_tag') {
                $query_params['tags'] = $tags;
            } else {
                $query_params[$tag_taxonomy_term] = $tags;
            }
        }



        $request = new WP_REST_Request('GET', '/wp/v2/' . $type);
        $request->set_query_params($query_params);
        $response = rest_do_request($request);
        $server = rest_get_server();
        $data = $server->response_to_data($response, false);

        $html = '';

        switch ($display_type) {

            case "filtered":

                $html .= '<div class="filters wp-post-list-filter-group">';
                foreach (explode(',', $filters) as $filter) {
                    $terms = get_terms(array(
                        'taxonomy' => $filter,
                        'hide_empty' => true,
                    ));

                    if ($filter_ui == 'list') {

                        $html .= '<ul class="list-filter">';
                        $html .= '<li><a href="#" data-filter="*">All</a></li>';
                        foreach ($terms as $f) {
                            $html .= '<li><a href="#" data-filter=".' . $f->slug . '">' . $f->name . '</a></li>';
                        }
                        $html .= '</ul>';
                    } elseif ($filter_ui == 'checkbox') {

                        $html .= '<div class="checkbox-filter">';
                        $html .= '<label class="filter-label">' . get_taxonomy($filter)->label . '</label>';
                        foreach ($terms as $f) {
                            $html .= '<label><input type="checkbox" name="checkbox-filter[]" value=".' . $f->slug . '" checked="checked" /> ' . $f->name . '</label>';
                        }
                        $html .= '</div>';
                    } else {

                        $html .= '<select class="dropdown-filter">';
                        $html .= '<option value="*">' . get_taxonomy($filter)->label . '</option>';
                        foreach ($terms as $f) {
                            $html .= '<option value=".' . $f->slug . '">' . $f->name . '</option>';
                        }
                        $html .= '</select>';
                    }
                }

                $html .= '</div>';
                $html .= '<div class="' . $container_class . '"><div id="' . $id . '" class="' . $row_class . '" >';

                foreach ($data as $post) {

                    $post_filters = [];
                    $taxonomy_names = get_post_taxonomies($post['id']);

                    foreach ($taxonomy_names as $t) {
                        $post_terms = get_the_terms($post['id'], $t);
                        if (is_array($post_terms)) {
                            foreach ($post_terms as $pt) {
                                $post_filters[] = $pt->slug;
                            }
                        }
                    }
                    $html .= '<div class="isotope-element ' . $column_class . ' ' . implode(' ', $post_filters) . '">';
                    $html .= replace_tags($content, array_flat($post));
                    $html .= '</div>';
                }
                $html .= '</div></div>';

                break;

            case "carousel":

                $html .= '<div class="' . $container_class . '"><div id="' . $id . '" class="owl-carousel owl-theme">';
                foreach ($data as $post) {
                    $html .= '<div class="item">';
                    $html .= replace_tags($content, array_flat($post));
                    $html .= '</div>';
                }
                $html .= '</div></div>';

                break;

            case "paginated":
            case "normal":

                $html .= '<div class="' . $container_class . '"><div id="' . $id . '" class="' . $row_class . '" >';
                foreach ($data as $post) {
                    $html .= '<div class="' . $column_class . '">';
                    $html .= replace_tags($content, array_flat($post));
                    $html .= '</div>';
                }
                $html .= '</div></div>';

                break;



            case "showmore":

                $html .= '<div class="' . $container_class . '"><div id="' . $id . '" class="' . $row_class . '" >';
                for ($x = 0; $x < $per_page; $x++) {
                    if (array_key_exists($x, $data)) {
                        $html .= '<div class="' . $column_class . '">';
                        $html .= replace_tags($content, array_flat($data[$x]));
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';

                if (count($data) > $per_page) {

                    $html .= '<div class="' . $button_container_class . '"><a href="#" class="' . $button_class . ' spinnr-showmore-'. $id .'" data-page="0" data-spinnr-showmore="' .
                        base64_encode(json_encode(
                            [
                                'type' => $type,
                                'query_params' => $query_params,
                                'target_id' => $id,
                                'column_class' => $column_class,
                                'content' => $content
                            ]
                        ))
                        . '">' . $button_text . '</a>';
                    $html .= '</div>';
                }
                $html .= '<script>jQuery(".spinnr-showmore-'.$id.'").on("click",function(e){ e.preventDefault();jQuery.post("/wp-json/spinnr/v2/wp_post_list_show_more",{page:jQuery(".spinnr-showmore-'.$id.'").data("page"),data:jQuery(".spinnr-showmore-'.$id.'").data("spinnr-showmore")},function(sreturn){jQuery("#'.$id.'").append(sreturn.html);jQuery(".spinnr-showmore-'.$id.'").data("page",parseInt(jQuery(".spinnr-showmore-'.$id.'").data("page"))+1);if(!sreturn.next){jQuery(".spinnr-showmore-'. $id .'").remove();}});});</script>';
                $html .= '</div>';

                break;
        }

        wp_reset_postdata();
        return $html;
    }
    add_shortcode('wp-post-list', 'create_wp_post_list');
endif;


/**
 * Shortcode response for wp-breadcrumbs SPINNR component
 */
if (!function_exists('create_wp_breadcrumbs')) :
    function create_wp_breadcrumbs($atts = array())
    {

        // set up default parameters
        extract(shortcode_atts(array(
            'homepage_title' => 'Home',
            'separator' => '>',
            'custom_taxonomy' => ''
        ), $atts));

        $html = '';

        global $post;

        if (!is_front_page()) {

            $html .= '<ul class="wp-breadcrumbs">';
            // Home page
            $html .= '<li class="item-home"><a class="bread-link bread-home" href="' . get_home_url() . '" title="' . $homepage_title . '">' . $homepage_title . '</a></li>';
            $html .= '<li class="separator separator-home"> ' . $separator . ' </li>';

            if (is_archive() && !is_tax() && !is_category() && !is_tag()) {
                $html .= '<li class="item-current item-archive"><strong class="bread-current bread-archive">' . post_type_archive_title($prefix, false) . '</strong></li>';
            } else if (is_archive() && is_tax() && !is_category() && !is_tag()) {

                // If post is a custom post type
                $post_type = get_post_type();

                // If it is a custom post type display name and link
                if ($post_type != 'post') {

                    $post_type_object = get_post_type_object($post_type);
                    $post_type_archive = get_post_type_archive_link($post_type);
                    $html .= '<li class="item-cat item-custom-post-type-' . $post_type . '"><a class="bread-cat bread-custom-post-type-' . $post_type . '" href="' . $post_type_archive . '" title="' . $post_type_object->labels->name . '">' . $post_type_object->labels->name . '</a></li>';
                    $html .= '<li class="separator"> ' . $separator . ' </li>';
                }

                $custom_tax_name = get_queried_object()->name;
                $html .= '<li class="item-current item-archive"><strong class="bread-current bread-archive">' . $custom_tax_name . '</strong></li>';
            } else if (is_single()) {

                // If post is a custom post type
                $post_type = get_post_type();

                // If it is a custom post type display name and link
                if ($post_type != 'post') {

                    $post_type_object = get_post_type_object($post_type);
                    $post_type_archive = get_post_type_archive_link($post_type);
                    $html .= '<li class="item-cat item-custom-post-type-' . $post_type . '"><a class="bread-cat bread-custom-post-type-' . $post_type . '" href="' . $post_type_archive . '" title="' . $post_type_object->labels->name . '">' . $post_type_object->labels->name . '</a></li>';
                    $html .= '<li class="separator"> ' . $separator . ' </li>';
                }



                // Get post category info
                $category = get_the_category();

                if (!empty($category)) {

                    // Get last category post is in
                    $last_category = end(array_values($category));

                    // Get parent any categories and create array
                    $get_cat_parents = rtrim(get_category_parents($last_category->term_id, true, ','), ',');
                    $cat_parents = explode(',', $get_cat_parents);

                    // Loop through parent categories and store in variable $cat_display
                    $cat_display = '';

                    foreach ($cat_parents as $parents) {
                        $cat_display .= '<li class="item-cat">' . $parents . '</li>';
                        $cat_display .= '<li class="separator"> ' . $separator . ' </li>';
                    }
                }

                // If it's a custom post type within a custom taxonomy
                $taxonomy_exists = taxonomy_exists($custom_taxonomy);
                if (empty($last_category) && !empty($custom_taxonomy) && $taxonomy_exists) {

                    $taxonomy_terms = get_the_terms($post->ID, $custom_taxonomy);
                    $cat_id = $taxonomy_terms[0]->term_id;
                    $cat_nicename = $taxonomy_terms[0]->slug;
                    $cat_link = get_term_link($taxonomy_terms[0]->term_id, $custom_taxonomy);
                    $cat_name = $taxonomy_terms[0]->name;
                }

                // Check if the post is in a category
                if (!empty($last_category)) {

                    $html .= $cat_display;
                    $html .= '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '" title="' . get_the_title() . '">' . get_the_title() . '</strong></li>';

                    // Else if post is in a custom taxonomy
                } else if (!empty($cat_id)) {

                    $html .= '<li class="item-cat item-cat-' . $cat_id . ' item-cat-' . $cat_nicename . '"><a class="bread-cat bread-cat-' . $cat_id . ' bread-cat-' . $cat_nicename . '" href="' . $cat_link . '" title="' . $cat_name . '">' . $cat_name . '</a></li>';
                    $html .= '<li class="separator"> ' . $separator . ' </li>';
                    $html .= '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '" title="' . get_the_title() . '">' . get_the_title() . '</strong></li>';
                } else {

                    $html .= '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '" title="' . get_the_title() . '">' . get_the_title() . '</strong></li>';
                }
            } else if (is_category()) {

                // Category page
                $html .= '<li class="item-current item-cat"><strong class="bread-current bread-cat">' . single_cat_title('', false) . '</strong></li>';
            } else if (is_page()) {

                // Standard page
                if ($post->post_parent) {

                    // If child page, get parents 
                    $anc = get_post_ancestors($post->ID);

                    // Get parents in the right order
                    $anc = array_reverse($anc);

                    // Parent page loop
                    if (!isset($parents)) $parents = null;
                    foreach ($anc as $ancestor) {
                        $parents .= '<li class="item-parent item-parent-' . $ancestor . '"><a class="bread-parent bread-parent-' . $ancestor . '" href="' . get_permalink($ancestor) . '" title="' . get_the_title($ancestor) . '">' . get_the_title($ancestor) . '</a></li>';
                        $parents .= '<li class="separator separator-' . $ancestor . '"> ' . $separator . ' </li>';
                    }

                    // Display parent pages
                    $html .= $parents;

                    // Current page
                    $html .= '<li class="item-current item-' . $post->ID . '"><strong title="' . get_the_title() . '"> ' . get_the_title() . '</strong></li>';
                } else {

                    // Just display current page if not parents
                    $html .= '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '"> ' . get_the_title() . '</strong></li>';
                }
            } else if (is_tag()) {

                // Get tag information
                $term_id = get_query_var('tag_id');
                $taxonomy = 'post_tag';
                $args = 'include=' . $term_id;
                $terms = get_terms($taxonomy, $args);
                $get_term_id = $terms[0]->term_id;
                $get_term_slug = $terms[0]->slug;
                $get_term_name = $terms[0]->name;

                // Display the tag name
                $html .= '<li class="item-current item-tag-' . $get_term_id . ' item-tag-' . $get_term_slug . '"><strong class="bread-current bread-tag-' . $get_term_id . ' bread-tag-' . $get_term_slug . '">' . $get_term_name . '</strong></li>';
            } elseif (is_day()) {

                // Day archive
                // Year link
                $html .= '<li class="item-year item-year-' . get_the_time('Y') . '"><a class="bread-year bread-year-' . get_the_time('Y') . '" href="' . get_year_link(get_the_time('Y')) . '" title="' . get_the_time('Y') . '">' . get_the_time('Y') . ' Archives</a></li>';
                $html .= '<li class="separator separator-' . get_the_time('Y') . '"> ' . $separator . ' </li>';

                // Month link
                $html .= '<li class="item-month item-month-' . get_the_time('m') . '"><a class="bread-month bread-month-' . get_the_time('m') . '" href="' . get_month_link(get_the_time('Y'), get_the_time('m')) . '" title="' . get_the_time('M') . '">' . get_the_time('M') . ' Archives</a></li>';
                $html .= '<li class="separator separator-' . get_the_time('m') . '"> ' . $separator . ' </li>';

                // Day display
                $html .= '<li class="item-current item-' . get_the_time('j') . '"><strong class="bread-current bread-' . get_the_time('j') . '"> ' . get_the_time('jS') . ' ' . get_the_time('M') . ' Archives</strong></li>';
            } else if (is_month()) {

                // Month Archive
                // Year link
                $html .= '<li class="item-year item-year-' . get_the_time('Y') . '"><a class="bread-year bread-year-' . get_the_time('Y') . '" href="' . get_year_link(get_the_time('Y')) . '" title="' . get_the_time('Y') . '">' . get_the_time('Y') . ' Archives</a></li>';
                $html .= '<li class="separator separator-' . get_the_time('Y') . '"> ' . $separator . ' </li>';

                // Month display
                $html .= '<li class="item-month item-month-' . get_the_time('m') . '"><strong class="bread-month bread-month-' . get_the_time('m') . '" title="' . get_the_time('M') . '">' . get_the_time('M') . ' Archives</strong></li>';
            } else if (is_year()) {

                // Display year archive
                $html .= '<li class="item-current item-current-' . get_the_time('Y') . '"><strong class="bread-current bread-current-' . get_the_time('Y') . '" title="' . get_the_time('Y') . '">' . get_the_time('Y') . ' Archives</strong></li>';
            } else if (is_author()) {

                // Auhor archive
                // Get the author information
                global $author;
                $userdata = get_userdata($author);

                // Display author name
                $html .= '<li class="item-current item-current-' . $userdata->user_nicename . '"><strong class="bread-current bread-current-' . $userdata->user_nicename . '" title="' . $userdata->display_name . '">' . 'Author: ' . $userdata->display_name . '</strong></li>';
            } else if (get_query_var('paged')) {

                // Paginated archives
                $html .= '<li class="item-current item-current-' . get_query_var('paged') . '"><strong class="bread-current bread-current-' . get_query_var('paged') . '" title="Page ' . get_query_var('paged') . '">' . __('Page', 'wp-spinnr') . ' ' . get_query_var('paged') . '</strong></li>';
            } else if (is_search()) {

                // Search results page
                $html .= '<li class="item-current item-current-' . get_search_query() . '"><strong class="bread-current bread-current-' . get_search_query() . '" title="Search results for: ' . get_search_query() . '">Search results for: ' . get_search_query() . '</strong></li>';
            } elseif (is_404()) {

                // 404 page
                $html .= '<li>' . 'Error 404' . '</li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }
    add_shortcode('wp-breadcrumbs', 'create_wp_breadcrumbs');
endif;


/**
 * Shortcode response for wp-post-content SPINNR component
 */
if (!function_exists('create_wp_post_content')) :
    function create_wp_post_content($atts = array(), $content = null)
    {

        // set up default parameters
        extract(shortcode_atts(array(
            'id' => '',
            'type' => 'posts',
            'post_id' => '',
        ), $atts));


        $request = new WP_REST_Request('GET', '/wp/v2/' . $type . '/' . $post_id);
        $response = rest_do_request($request);
        $server = rest_get_server();
        $data = $server->response_to_data($response, false);
        $html = $data['content']['rendered'];

        wp_reset_postdata();

        return $html;
    }
    add_shortcode('wp-post-content', 'create_wp_post_content');
endif;

/**
 * Shortcode response for wp-post-content SPINNR component
 */
if (!function_exists('wp_spinnr_current_year')) :
    function wp_spinnr_current_year()
    {
        return date('Y');
    }
    add_shortcode('current_year', 'wp_spinnr_current_year');
endif;


/**
 * Function used by shortcodes to replace {{tags}} 
 */
function replace_tags($string, $data)
{

    foreach ($data as $key => $value) {
        if (strpos($string, '{{' . $key . '}}') !== false) {
            $string = str_replace('{{' . $key . '}}', $value, $string);
        }
    }

    return $string;
}

/**
 * Function used by shortcodes to process variables
 */
function array_flat($array, $prefix = '')
{

    $result = array();

    foreach ($array as $key => $value) {
        $new_key = $prefix . (empty($prefix) ? '' : '.') . $key;
        if (is_array($value)) {
            $result = array_merge($result, array_flat($value, $new_key));
        } else {
            $result[$new_key] = $value;
        }
    }

    return $result;
}

/**
 * Shortcode response for wp-post-content SPINNR component
 */
if (!function_exists('create_wp_form_builder')) :
    function create_wp_form_builder($atts = array(), $content = null)
    {

        // set up default parameters
        extract(shortcode_atts(array(
            'id' => '',
            'url' => '',
        ), $atts));

        $html = '<div id="'.$id.'">
                    <iframe class="w-full h-full" src="'.$url.'?styles[]='.get_stylesheet_directory_uri() . '/spinnr.min.css" frameborder="0"></iframe>
                    <script>window.addEventListener("message",(e) => {document.getElementById("'.$id.'").style.height = e.data.height + "px";}, false);</script>
                </div>';

        return $html;
    }
    add_shortcode('wp-form-builder', 'create_wp_form_builder');
endif;

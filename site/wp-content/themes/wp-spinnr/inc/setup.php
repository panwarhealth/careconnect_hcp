<?php

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function wp_spinnr_setup()
{
    /*
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 */
    load_theme_textdomain('wp-spinnr', get_template_directory() . '/languages');

    /*
	 * Enable theme support.
	 *
	 * @link https://developer.wordpress.org/themes/functionality/
	 */
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'customize-selective-refresh-widgets' );
    add_theme_support( 'widgets' );

    /*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));

    /*
	 * Register default menus.
	 */
    register_nav_menus(array(
        'main' => esc_html__('Main', 'wp-spinnr'),
        'footer' => esc_html__('Footer', 'wp-spinnr'),
    ));

    // add default main menu
    $mainmenu = 'Main Menu';
    $mainmenulocation = 'main';
    $menu_exists = wp_get_nav_menu_object($mainmenu);
    if (!$menu_exists) {
        $main_menu_id = wp_create_nav_menu($mainmenu);

        wp_update_nav_menu_item($main_menu_id, 0, array(
            'menu-item-title' =>  __('Home', 'wp-spinnr'),
            'menu-item-classes' => '',
            'menu-item-url' => '/',
            'menu-item-status' => 'publish'
        ));

        // Grab the theme locations and assign our newly-created menu
        if (!has_nav_menu($mainmenulocation)) {
            $locations = get_theme_mod('nav_menu_locations');
            $locations[$mainmenulocation] = $main_menu_id;
            set_theme_mod('nav_menu_locations', $locations);
        }
    } else {
        $main_menu_id = $menu_exists->term_id;
    }

    // add default footer menu
    $footermenu = 'Terms And Conditions';
    $footermenulocation = 'footer';
    $menu_exists = wp_get_nav_menu_object($footermenu);
    if (!$menu_exists) {
        $footer_menu_id = wp_create_nav_menu($footermenu);

        wp_update_nav_menu_item($footer_menu_id, 0, array(
            'menu-item-title' =>  __('Privacy Policy', 'wp-spinnr'),
            'menu-item-classes' => '',
            'menu-item-url' => '#',
            'menu-item-status' => 'publish'
        ));

        wp_update_nav_menu_item($footer_menu_id, 0, array(
            'menu-item-title' =>  __('Terms and Conditions', 'wp-spinnr'),
            'menu-item-classes' => '',
            'menu-item-url' => '#',
            'menu-item-status' => 'publish'
        ));

        // Grab the theme locations and assign our newly-created menu
        if (!has_nav_menu($footermenulocation)) {
            $locations = get_theme_mod('nav_menu_locations');
            $locations[$footermenulocation] = $footer_menu_id;
            set_theme_mod('nav_menu_locations', $locations);
        }
    } else {
        $footer_menu_id = $menu_exists->term_id;
    }

    /*
	 * Register default post types.
	 */
    register_post_type(
        'spinnr_header',
        array(
            'labels'      => array(
                'name'          => __('Headers', 'wp-spinnr'),
                'singular_name' => __('Header', 'wp-spinnr'),
            ),
            'public'      => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'labels' => [
                'name' => __('Headers', 'wp-spinnr'),
                'singular_name' => __('Header', 'wp-spinnr'),
                'add_new' => __('Add New Header', 'wp-spinnr'),
                'add_new_item' => __('Add New Header', 'wp-spinnr'),
                'edit_item' => __('Edit Header', 'wp-spinnr'),
            ],
        )
    );
    register_post_type(
        'spinnr_footer',
        array(
            'labels'      => array(
                'name'          => __('Footers', 'wp-spinnr'),
                'singular_name' => __('Footer', 'wp-spinnr'),
            ),
            'public'      => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'labels' => [
                'name' => __('Footers', 'wp-spinnr'),
                'singular_name' => __('Footer', 'wp-spinnr'),
                'add_new' => __('Add New Footer', 'wp-spinnr'),
                'add_new_item' => __('Add New Footer', 'wp-spinnr'),
                'edit_item' => __('Edit Footer', 'wp-spinnr'),
            ],
        )
    );
    register_post_type(
        'spinnr_mobile_menu',
        array(
            'labels'      => array(
                'name'          => __('Mobile Menus', 'wp-spinnr'),
                'singular_name' => __('Mobile Menu', 'wp-spinnr'),
            ),
            'public'      => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'labels' => [
                'name' => __('Mobile Menus', 'wp-spinnr'),
                'singular_name' => __('Mobile Menu', 'wp-spinnr'),
                'add_new' => __('Add New Mobile Menu', 'wp-spinnr'),
                'add_new_item' => __('Add New Mobile Menu', 'wp-spinnr'),
                'edit_item' => __('Edit Mobile Menu', 'wp-spinnr'),
            ],
        )
    );

    /*
	 * Create default post type content.
	 */
    $logo_url = 'https://stage.spinnr.io/storage/files/logo-placeholder.png';
    $icon_url = 'https://stage.spinnr.io/storage/files/icon-placeholder.png';
    $query = new WP_Query(array('post_type' => 'spinnr_header', 'name' => 'default_spinnr_header'));
    if (!$query->have_posts()) {
        $header_menu_hash = base64_encode('{"id":"pb-element-wp-menus-1","label":"wp-menus","element":"wp-menus","children":[],"pb_attributes":{"class":"header-menu","style":""},"pb_component_data":{"display":"div","menu":' . $main_menu_id . ',"link_class":""}}');
        wp_insert_post(array(
            'post_title' => 'Default SPINNR Header (default)',
            'post_name' => 'default_spinnr_header',
            'post_type' => 'spinnr_header',
            'post_content' => '
                <header id="header" class="md:static site-header sticky top-0 w-full z-50" role="banner">
                    <div class="bg-white border-b border-stroke flex items-center justify-between lg:px-4xl px-xl">
                        <div class="">
                            <img src="'.$logo_url.'" class="h-base my-base" alt="" />
                        </div>
                        <div class="hidden md:block ml-auto">
                            <!--
                            spinnr:' . $header_menu_hash . '
                            -->
                            <div class="header-menu">
                            [wp-menus display="div" menu="' . $main_menu_id . '" link_class=""]</div>
                        </div>
                        <div class="flex items-center ml-auto md:ml-4xl"><a class="btn cta m-0" href="javascript:void(0)">Button</a>
                            <button class="btn ghost m-0 md:hidden ml-xs" onclick="toggleMobileMenu()">
                                <svg viewbox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-sm h-sm">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.25 4C1.25 3.44772 1.69772 3 2.25 3H15.75C16.3023 3 16.75 3.44772 16.75 4C16.75 4.55228 16.3023 5 15.75 5H2.25C1.69772 5 1.25 4.55228 1.25 4Z" fill="currentColor" />
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.25 9C1.25 8.44772 1.69772 8 2.25 8H15.75C16.3023 8 16.75 8.44772 16.75 9C16.75 9.55228 16.3023 10 15.75 10H2.25C1.69772 10 1.25 9.55228 1.25 9Z" fill="currentColor" />
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.25 14C1.25 13.4477 1.69772 13 2.25 13H15.75C16.3023 13 16.75 13.4477 16.75 14C16.75 14.5523 16.3023 15 15.75 15H2.25C1.69772 15 1.25 14.5523 1.25 14Z" fill="currentColor" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </header>
            ',
            'post_status' => 'publish',
        ));
    }
    $query = new WP_Query(array('post_type' => 'spinnr_footer', 'name' => 'default_spinnr_footer'));
    if (!$query->have_posts()) {
        $footer_menu_hash = base64_encode('{"id":"pb-element-wp-menus-2","label":"wp-menus","element":"wp-menus","children":[],"pb_attributes":{"class":"footer-menu flex space-x-2xl","style":""},"pb_component_data":{"display":"div","menu":' . $footer_menu_id . ',"link_class":"no-underline mb-0 text-paragraph text-md"}}');
        wp_insert_post(array(
            'post_title' => 'Default SPINNR Footer (default)',
            'post_name' => 'default_spinnr_footer',
            'post_type' => 'spinnr_footer',
            'post_content' => '
                <footer class="site-footer bg-white">
                    <div class="container grid md:grid-cols-12 pt-4xl gap-4xl">
                        <div class="lg:col-span-4 md:col-span-6">
                            <img src="'.$logo_url.'" class="h-base" />
                            <p class=" ">Elit pellentesque habitant morbi tristique senectus et netus. 
                            </p>
                            <div class="flex space-x-xl">
                                <img src="'.$icon_url.'" class="h-sm" />
                                <img src="'.$icon_url.'" class="h-sm" />
                                <img src="'.$icon_url.'" class="h-sm" />
                            </div>
                        </div>
                        <div class="lg:col-span-3 lg:col-start-6 md:col-span-6">
                            <h6 class="text-base uppercase font-semibold">About Us 
                            </h6>
                            <ul class="list-none space-y-xs">
                                <li class="">Elit pellentesque 
                                </li>
                                <li class="">Habitant morbi 
                                </li>
                                <li class="">Tristique senectus 
                                </li>
                            </ul>
                        </div>
                        <div class="lg:col-span-4 md:col-span-full">
                            <h6 class="text-base uppercase font-semibold">Contact Us 
                            </h6>
                            <p class=" ">Habitant morbi tristique senectus et netus. Suspendisse interdum. 
                            </p>
                            <div class="bg-secondary px-sm py-sm">Replace with the form shortcode 
                            </div>
                        </div>
                        <div class="border-stroke border-t items-center justify-between col-span-full py-lg md:flex">
                            <div class="mb-lg md:text-left text-center text-md md:mb-0">Copyright © [current_year]
                            </div>
                            <div class="">
                                <!--
                                spinnr:' . $footer_menu_hash . '
                                -->
                                <div class="footer-menu flex space-x-2xl">
                                [wp-menus display="link" menu="' . $footer_menu_id . '" link_class="no-underline mb-0 text-paragraph text-md"]</div>
                            </div>
                        </div>
                    </div>
                </footer>
            ',
            'post_status' => 'publish',
        ));
    }
    $query = new WP_Query(array('post_type' => 'spinnr_mobile_menu', 'name' => 'default_spinnr_mobile_menu'));
    if (!$query->have_posts()) {
        $mobile_menu_hash = base64_encode('{"id":"pb-element-wp-menus-3","label":"wp-menus","element":"wp-menus","children":[],"pb_attributes":{"class":"mobile-menu","style":""},"pb_component_data":{"display":"div","menu":' . $main_menu_id . ',"link_class":""}}');
        wp_insert_post(array(
            'post_title' => 'Default SPINNR Mobile Menu (default)',
            'post_name' => 'default_spinnr_mobile_menu',
            'post_type' => 'spinnr_mobile_menu',
            'post_content' => '
                <div class="bg-white h-full w-full pt-7xl p-4xl overflow-auto">
                    <!--
                    spinnr:' . $mobile_menu_hash . '
                    -->
                    <div class="mobile-menu">
                    [wp-menus display="div" menu="' . $main_menu_id . '" link_class=""]</div>
                    <!--
                    spinnr:eyJpZCI6InBiLWVsZW1lbnQtaW5saW5lLWNvZGUtNSIsImxhYmVsIjoiaW5saW5lLWNvZGUiLCJlbGVtZW50IjoiaW5saW5lLWNvZGUiLCJjaGlsZHJlbiI6W10sInBiX2F0dHJpYnV0ZXMiOnsiY2xhc3MiOiIifSwicGJfY29tcG9uZW50X2RhdGEiOnsiZGF0YSI6IjxzY3JpcHQ+alF1ZXJ5KGRvY3VtZW50KS5yZWFkeSgoZnVuY3Rpb24oZSl7ZShcIi5tb2JpbGUtbWVudSAubWVudS1pdGVtLmhhcy1jaGlsZHJlbiA+IGFcIikub24oXCJjbGlja1wiLChmdW5jdGlvbih0KXt0LnByZXZlbnREZWZhdWx0KCk7dmFyIGE9ZSh0aGlzKS5wYXJlbnQoKSxpPWEuZmluZChcIi5jaGlsZC1tZW51XCIpO2EuaGFzQ2xhc3MoXCJhY3RpdmVcIik/KGEucmVtb3ZlQ2xhc3MoXCJhY3RpdmVcIiksaS5yZW1vdmVBdHRyKFwic3R5bGVcIikpOihhLmFkZENsYXNzKFwiYWN0aXZlXCIpLGkuY3NzKFwibWF4LWhlaWdodFwiLGkucHJvcChcInNjcm9sbEhlaWdodFwiKStcInB4XCIpKX0pKX0pKTs8XC9zY3JpcHQ+In0sImRyYWdnYWJsZSI6dHJ1ZSwiZHJvcHBhYmxlIjp0cnVlfQ==
                    -->
                    <div id="pb-element-inline-code-5">
                        <script>jQuery(document).ready((function(e){e(".mobile-menu .menu-item.has-children > a").on("click",(function(t){t.preventDefault();var a=e(this).parent(),i=a.find(".child-menu");a.hasClass("active")?(a.removeClass("active"),i.removeAttr("style")):(a.addClass("active"),i.css("max-height",i.prop("scrollHeight")+"px"))}))}));</script>
                    </div>
                </div>
            ',
            'post_status' => 'publish',
        ));
    }
}
add_action('after_setup_theme', 'wp_spinnr_setup');

/*
* Register sidebar.
*/
function wp_spinnr_widgets_init()
{
    register_sidebar(array(
        'name'          => __('Main Sidebar', 'wp-spinnr'),
        'id'            => 'sidebar',
        'description'   => __('Widgets in this area will be shown on all posts and pages.', 'textdomain'),
        'before_widget' => '<li id="%1$s" class="widget %2$s">',
        'after_widget'  => '</li>',
        'before_title'  => '<h2 class="widgettitle">',
        'after_title'   => '</h2>',
    ));
}
add_action('widgets_init', 'wp_spinnr_widgets_init');

/**
 * Remove WordPress wpautop
 */
remove_filter('the_content', 'wpautop');

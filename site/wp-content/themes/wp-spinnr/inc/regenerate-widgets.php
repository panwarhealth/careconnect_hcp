<?php
/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package WP_Spinnr
 */

if ( ! function_exists( 'wp_spinnr_widgets_init' ) ) :

function wp_spinnr_widgets_init() {
    register_sidebar( array(
        'name'          => esc_html__( 'Sidebar', 'wp-spinnr' ),
        'id'            => 'sidebar-1',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Header Section', 'wp-spinnr' ),
        'id'            => 'header-section',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '',
        'after_widget'  => '',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Header Section Mobile', 'wp-spinnr' ),
        'id'            => 'header-section-mobile',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '',
        'after_widget'  => '',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer Address', 'wp-spinnr' ),
        'id'            => 'footer-address',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 1 Section 1', 'wp-spinnr' ),
        'id'            => 'footer-1-section-1',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 1 Section 2', 'wp-spinnr' ),
        'id'            => 'footer-1-section-2',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 4 Subscribe', 'wp-spinnr' ),
        'id'            => 'footer-4-subscribe',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 5 Mobile App Section', 'wp-spinnr' ),
        'id'            => 'footer-5-mobile-apps',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 8 Subscribe', 'wp-spinnr' ),
        'id'            => 'footer-8-subscribe',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'Footer Column 1', 'wp-spinnr' ),
        'id'            => 'footer-col-1',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer Column 2', 'wp-spinnr' ),
        'id'            => 'footer-col-2',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer Column 3', 'wp-spinnr' ),
        'id'            => 'footer-col-3',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer Column 4', 'wp-spinnr' ),
        'id'            => 'footer-col-4',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer Column 5', 'wp-spinnr' ),
        'id'            => 'footer-col-5',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer Copyright', 'wp-spinnr' ),
        'id'            => 'footer-copyright',
        'description'   => esc_html__( 'Add widgets here.', 'wp-spinnr' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    

    $active_widgets = get_option('sidebars_widgets');
    // dd($active_widgets);
    $widget_custom_html = get_option('widget_custom_html');
    $keys = array_keys($widget_custom_html); asort($keys);
    $last_key = array_pop($keys);
    $counter = intval($last_key) + 1;
    $widgets_update = false;

    if(isset($active_widgets['header-section']) && count($active_widgets['header-section']) == 0){
        $active_widgets['header-section'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<a class="btn btn-primary m-0" href="javascript:void(0)">Action</a>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['header-section-mobile']) && count($active_widgets['header-section-mobile']) == 0){
        $active_widgets['header-section-mobile'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<a href="javascript:void(0)" class="text-dark"><i class="fa fa-star fa-2x"></i></a>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-1-section-1']) && count($active_widgets['footer-1-section-1']) == 0){
        $active_widgets['footer-1-section-1'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<blockquote class="blockquote weight-500 mb-10 text-dark">
                    Change the color to match your brand or vision, add your logo, choose the perfect thumbnail, remove the playbar, add speed controls, and more.
                </blockquote>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-address']) && count($active_widgets['footer-address']) == 0){
        $active_widgets['footer-address'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<p class="mb-1">Stórhöfði 33</p>
                <p class="mb-1">110 Reykjavík, Iceland</p>
                <p class="mb-1">Tel: +354 587 9999</p>
                <p class="mb-0"><a class="text-dark" href=""><span>info@mountainguides.is</span></a></p>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-1-section-2']) && count($active_widgets['footer-1-section-2']) == 0){
        $active_widgets['footer-1-section-2'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<span class="text-muted">Made in Saint Petersburg</span>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-4-subscribe']) && count($active_widgets['footer-4-subscribe']) == 0){
        $active_widgets['footer-4-subscribe'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<p class="small text-muted mb-3">Join our newsletter to stay up to date on features and releases</p>
            <div class="form-group input-group input-group-lg mb-0"><input class="form-control form-control-lg" type="text" placeholder="Enter your email address" />
                <div class="input-group-control m-1"><a class="transparent-invert-link text-dark" href=""><i class="fa fa-paper-plane fa-2x"></i></a></div>
            </div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-5-mobile-apps']) && count($active_widgets['footer-5-mobile-apps']) == 0){
        $active_widgets['footer-5-mobile-apps'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<h4>Mobile apps</h4>
            <div class="mb-2"><a href=""><img src="/wp-content/themes/wp-spinnr/inc/assets/logos/app-store.svg" width="123" /></a></div>
            <div class="mb-0"><a href=""><img src="/wp-content/themes/wp-spinnr/inc/assets/logos/google-play.svg" width="123" /></a></div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-8-subscribe']) && count($active_widgets['footer-8-subscribe']) == 0){
        $active_widgets['footer-8-subscribe'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<div class="form-group input-group input-group-lg"><input class="form-control form-control-lg" type="text" placeholder="Enter your email address" />
                <div class="input-group-control m-1"><a class="transparent-invert-link text-dark" href=""><i class="fa fa-paper-plane fa-2x"></i></a></div>
            </div>
            <p class="small text-muted mb-0">Join our newsletter to stay up to date on features and releases</p>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-col-1']) && count($active_widgets['footer-col-1']) == 0){
        $active_widgets['footer-col-1'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<div class="py-6">Widget Area Column 1</div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-col-2']) && count($active_widgets['footer-col-2']) == 0){
        $active_widgets['footer-col-2'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<div class="py-6">Widget Area Column 2</div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-col-3']) && count($active_widgets['footer-col-3']) == 0){
        $active_widgets['footer-col-3'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<div class="py-6">Widget Area Column 3</div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-col-4']) && count($active_widgets['footer-col-4']) == 0){
        $active_widgets['footer-col-4'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<div class="py-6">Widget Area Column 4</div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-col-5']) && count($active_widgets['footer-col-5']) == 0){
        $active_widgets['footer-col-5'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<div class="py-6">Widget Area Column 5</div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if(isset($active_widgets['footer-copyright']) && count($active_widgets['footer-copyright']) == 0){
        $active_widgets['footer-copyright'][] = 'custom_html-'.$counter;
        $widget_custom_html[$counter] = [
            'title' => '',
            'content' => '<div class="border-t border-top py-1 small text-sm">© 2021 spinnr.io. All right reserved.</div>'
        ];
        $widgets_update = true;
        $counter++;
    }

    if($widgets_update){
        update_option( 'widget_custom_html', $widget_custom_html );
        update_option( 'sidebars_widgets', $active_widgets );
    }
}
add_action( 'widgets_init', 'wp_spinnr_widgets_init' );

endif;
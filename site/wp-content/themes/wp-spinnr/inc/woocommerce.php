<?php

/**
 * Sets up theme WooCommerce support for various WordPress features.
 *
 * https://woo.com/documentation/woocommerce/
 */
function wp_spinnr_wc_setup()
{
    /**
    * Checks if WooCommerce is installed. You can comment this code block if you do not wish to use SPINNR WooCommerce styling
    */
    if (class_exists('Woocommerce')){
        add_theme_support( 'woocommerce' );
        add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
        add_action('wp_enqueue_scripts', 'wp_spinnr_wc_styles');
        add_filter( 'woocommerce_breadcrumb_defaults', 'wp_spinnr_wc_breadcrumbs' );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
        add_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_rating', 30 );
        add_action( 'woocommerce_shop_loop_item_title', 'wp_spinnr_wc_product_excerpt', 20 ); 
    }
}

add_action('after_setup_theme', 'wp_spinnr_wc_setup');

/**
* Function to add woocommerce.css
*/
function wp_spinnr_wc_styles(){
    wp_enqueue_style('wp-spinnr-woocommerce-styles', get_template_directory_uri() . '/inc/assets/css/woocommerce.css');
}

/**
* Setup WooCommerce default breadcrumbs
*/
function wp_spinnr_wc_breadcrumbs(){
    return array(
        'delimiter'   => ' &gt; ',
        'wrap_before' => '<nav class="woocommerce-breadcrumb mb-lg" itemprop="breadcrumb">',
        'wrap_after'  => '</nav>',
        'before'      => '',
        'after'       => '',
        'home'        => _x( 'Home', 'breadcrumb', 'woocommerce' ),
    );
}

/**
* WooCommerce hook function to add excerpt to product loop
*/
function wp_spinnr_wc_product_excerpt() { 
    global $post; 
    echo '<p class="text-md text-paragraph opacity-60 font-normal">'.wp_trim_words($post->post_excerpt, 10).'</p>';
}
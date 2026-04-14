<?php
function wp_spinnr_extended_enqueue_scripts(){
wp_enqueue_style( 'wp-spinnr-custom-css-0', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css'); 
wp_enqueue_style( 'wp-spinnr-custom-css-1', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css'); 
wp_enqueue_script( 'wp-spinnr-custom-foot-js-0', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js', array(), null, true); 
} add_action( 'wp_enqueue_scripts', 'wp_spinnr_extended_enqueue_scripts' );
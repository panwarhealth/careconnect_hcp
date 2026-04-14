<?php

/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WP_SPINNR
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <div id="app" class="site"><!-- #page -->
        <a class="skip-link sr-only" href="#content"><?php esc_html_e('Skip to content', 'wp-spinnr'); ?></a>
        <?php wp_spinnr_load_header() ?>
        <div id="mobile-menu" class="fixed z-40 left-0 -top-full duration-300 w-full h-full">
            <?php wp_spinnr_load_mobile_menu(); ?>
        </div>
        <script type="text/javascript">
            function toggleMobileMenu() {
                var mobileMenu = document.getElementById('mobile-menu');
                if(mobileMenu.classList.contains('top-0')){
                    mobileMenu.classList.remove('top-0');
                    mobileMenu.classList.add('-top-full');
                } else {
                    mobileMenu.classList.remove('-top-full');
                    mobileMenu.classList.add('top-0');
                }
            }
        </script>
        <div id="content" class="site-content"><!-- #content -->
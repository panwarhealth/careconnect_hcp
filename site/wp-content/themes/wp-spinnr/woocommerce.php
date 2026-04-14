<?php
/**
 * The template for displaying Woocommerce Product
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WP_Spinnr
 */

get_header(); ?>

<div class="container">
    <div class="row">
        <div id="primary" class="content-area col-sm-12 col-md-12 col-lg-8 pt-4">
            <main id="main" class="site-main" role="main">

            <?php woocommerce_content(); ?>

            </main><!-- #main -->
        </div><!-- #primary -->
        <?php get_sidebar(); ?>
    </div>
</div>
<?php
get_footer();

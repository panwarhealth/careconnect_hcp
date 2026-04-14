<?php

/**
 * Template part for displaying blank content
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WP_SPINNR
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <?php
    the_content();

    wp_link_pages(array(
        'before' => '<div class="page-links">' . esc_html__('Pages:', 'wp-spinnr'),
        'after'  => '</div>',
    ));
    ?>
</article><!-- #post-## -->
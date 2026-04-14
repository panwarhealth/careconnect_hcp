<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WP_Spinnr
 */

get_header(); 
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<?php
		while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div class="entry-content">
				<?php
				if ( is_single() ) :
					the_content();
				else :
					the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'wp-spinnr' ) );
				endif;
				?>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

		<?php endwhile; ?>

	</main><!-- #main -->
</div><!-- #primary -->
<?php //get_sidebar(); ?>

<?php
/* ?>
<div class="container mx-auto px-4">
    <div class="row">
        <div id="primary" class="content-area col-12 pt-28">
            <main id="main" class="site-main" role="main">

				<?php
				while ( have_posts() ) : the_post();

					get_template_part( 'template-parts/content', get_post_format() );

						the_post_navigation();

					// If comments are open or we have at least one comment, load up the comment template.
					if ( comments_open() || get_comments_number() ) :
						comments_template();
					endif;

				endwhile; // End of the loop.
				?>

			</main><!-- #main -->
        </div><!-- #primary -->
        <?php //get_sidebar(); ?>
    </div>
</div>

<?php */
get_footer();

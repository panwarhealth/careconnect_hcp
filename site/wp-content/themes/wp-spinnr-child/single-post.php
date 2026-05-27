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
			<div class="entry-content relative">
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
get_footer();

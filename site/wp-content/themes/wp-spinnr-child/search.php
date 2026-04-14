<?php
/**
 * The template for displaying search results pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package WP_Spinnr
 */

get_header(); ?>

<div class="container mx-auto px-4">
    <div class="flex flex-col md:flex-row pt-28">
        <div id="primary" class="content-area w-full md:w-8/12 pt-4">
            <main id="main" class="site-main" role="main">

				<?php
				if ( have_posts() ) : ?>

					<header class="page-header">
						<h1 class="page-title"><?php printf( esc_html__( 'Search Results for: %s', 'wp-spinnr' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
					</header><!-- .page-header -->

					<?php
					/* Start the Loop */
					while ( have_posts() ) : the_post();

						/**
						 * Run the loop for the search to output the results.
						 * If you want to overload this in a child theme then include a file
						 * called content-search.php and that will be used instead.
						 */
						get_template_part( 'template-parts/content', 'search' );

					endwhile;

					the_posts_navigation();

				else :

					get_template_part( 'template-parts/content', 'none' );

				endif; ?>

			</main><!-- #main -->
        </div><!-- #primary -->
        <?php get_sidebar(); ?>
    </div>
</div>
<?php
get_footer();
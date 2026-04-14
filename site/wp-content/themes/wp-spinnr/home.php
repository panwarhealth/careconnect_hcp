<?php
/**
 * The template for displaying default home page
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


get_header(); 

switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
			'container' => 'container mx-auto px-4',
			'row' => 'flex',
			'col-8' => 'w-full lg:w-2/3 py-6'
		);
		break;
	default:
		$classes = array(
			'container' => 'container',
			'row' => 'row',
			'col-8' => 'col-sm-12 col-md-12 col-lg-8 py-4'
		);
		break;
}
?>

<div class="<?php echo $classes['container'] ?>">
    <div class="<?php echo $classes['row'] ?>">
        <div id="primary" class="content-area <?php echo $classes['col-8'] ?>">
            <main id="main" class="site-main <?php echo TBST_SPINNR_FRAMEWORK == 'tailwind-2' ? 'prose' : ''?>" role="main">

            <?php
            if ( have_posts() ) :

                if ( is_home() && ! is_front_page() ) : ?>
                    <header>
                        <h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
                    </header>

                <?php
                endif;

                /* Start the Loop */
                while ( have_posts() ) : the_post();

                    /*
                    * Include the Post-Format-specific template for the content.
                    * If you want to override this in a child theme, then include a file
                    * called content-___.php (where ___ is the Post Format name) and that will be used instead.
                    */
                    get_template_part( 'template-parts/content', get_post_format() );

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

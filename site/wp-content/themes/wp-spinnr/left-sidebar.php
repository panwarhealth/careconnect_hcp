<?php
/**
 * Template Name: Left Sidebar
 */

get_header();

switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
			'container' => 'container mx-auto px-4',
			'row' => 'flex flex-wrap',
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
        <?php get_sidebar();?>
        <section id="primary" class="content-area <?php echo $classes['col-8'] ?>">
            <main id="main" class="site-main <?php echo TBST_SPINNR_FRAMEWORK == 'tailwind-2' ? 'prose' : ''?>" role="main">

                <?php
                while ( have_posts() ) : the_post();

                    get_template_part( 'template-parts/content', 'blank' );

                    // If comments are open or we have at least one comment, load up the comment template.
                    if ( comments_open() || get_comments_number() ) :
                        comments_template();
                    endif;
                endwhile; // End of the loop.
                ?>

            </main><!-- #main -->
        </section><!-- #primary -->
    </div>
</div>
<?php
get_footer();

<?php
/**
 * Template Name: Page with Container
 */

get_header();

switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
			'container' => 'container mx-auto px-4',
			'row' => 'flex',
			'col-12' => 'w-full'
		);
		break;
	default:
		$classes = array(
			'container' => 'container',
			'row' => 'row',
			'col-12' => 'col-12'
		);
		break;
}
?>
<div class="<?php echo $classes['container'] ?>">
    <div class="<?php echo $classes['row'] ?>">
        <section id="primary" class="content-area <?php echo $classes['col-12'] ?>">
            <main id="main" class="site-main" role="main">

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

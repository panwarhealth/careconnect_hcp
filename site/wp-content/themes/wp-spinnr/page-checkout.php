<?php
/**
 * The template for displaying all pages
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
			'col-12' => 'w-full py-6'
		);
		break;
	default:
		$classes = array(
			'container' => 'container',
			'row' => 'row',
			'col-12' => 'col-12 pt-4'
		);
		break;
}
?>

<div class="<?php echo $classes['container'] ?>">
    <div class="<?php echo $classes['row'] ?>">
        <div id="primary" class="content-area <?php echo $classes['col-12'] ?>">
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
        </div><!-- #primary -->
    </div>
</div>
<?php get_footer();
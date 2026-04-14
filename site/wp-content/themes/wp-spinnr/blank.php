<?php

/**
 * Template Name: Blank Page
 */

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

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="profile" href="http://gmpg.org/xfn/11">
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div id="app" class="site">
    <a class="skip-link <?php echo TBST_SPINNR_FRAMEWORK == 'tailwind-2' ? 'sr-only' : 'screen-reader-text'?>" href="#content"><?php esc_html_e( 'Skip to content', 'wp-spinnr' ); ?></a>
	<div class="site-content">

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
    </div><!-- #content -->
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>

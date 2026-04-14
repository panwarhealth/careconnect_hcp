<?php

switch(TBST_SPINNR_FRAMEWORK){

	case 'tailwind-2':

		$classes = array(

            'row1' => 'flex flex-wrap pt-6 mb-6 '.get_theme_mod('footer_link_class', 'text-dark'),

			'class1' => 'border-t lg:hidden '.get_theme_mod('footer_link_class', 'text-dark'),

			'class2' => 'block lg:flex justify-between py-4 lg:py-3 text-sm '.get_theme_mod('footer_link_class', 'text-dark'),

			'col1' => 'w-full lg:w-4/12 lg:pr-0 mb-4 lg:mb-0',

			'col2' => 'w-full lg:w-5/12',

            'col3' => 'w-full lg:w-3/12 text-center lg:text-right',

            'col4' => 'w-full lg:w-1/12',

            'col5' => 'w-full lg:w-2/12 text-sm mt-6 lg:mt-0',

            'class3' => 'font-medium justify-between h-full',

            'class4' => 'text-sm mb-3 lg:mb-0 lg:text-right',

            'class5' => 'block lg:inline text-muted lg:ml-3 mb-3 lg:mb-0',

		);

		break;

	default:

		$classes = array(

			'row1' => 'row pt-4 mb-4 mb-lg-5 '.get_theme_mod('footer_link_class', 'text-dark'),

			'class1' => 'border-top d-lg-none '.get_theme_mod('footer_link_class', 'text-dark'),

			'class2' => 'd-block d-lg-flex justify-content-between py-3 py-lg-2 small '.get_theme_mod('footer_link_class', 'text-dark'),

			'col1' => 'col-12 col-lg-3 pr-lg-0 mb-4 mb-lg-0',

			'col2' => 'col-12 col-lg-2',

            'col3' => 'col-6 col-lg-2',

            'col4' => 'col-12 col-lg-1',

            'col5' => 'col-12 col-lg-2 small mt-4 mt-lg-0',

            'class3' => 'weight-500',

            'class4' => 'small mb-2 mb-lg-0',

            'class5' => 'd-block d-lg-inline text-muted ml-lg-2 mb-2 mb-lg-0',

		);

		break;

}

?>

<div class="<?php echo $classes['row1']; ?>">

    <div class="<?php echo $classes['col1']; ?>">

        <?php if (get_theme_mod('wp_spinnr_footer_logo')) : ?>

            <a href="<?php echo esc_url(home_url('/')); ?>">

                <img src="<?php echo esc_url(get_theme_mod('wp_spinnr_footer_logo')); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">

            </a>

        <?php else : ?>

            <a class="site-title <?php echo get_theme_mod('footer_link_class', 'text-dark');?>" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_url(bloginfo('name')); ?></a>

        <?php endif; ?>



        <?php dynamic_sidebar('footer-4-subscribe'); ?>

    </div>

    <div class="<?php echo $classes['col2']; ?>"></div>

    <div class="<?php echo $classes['col3']; ?>">

        <div class="navbar-nav <?php echo $classes['class3']; ?>">

            <?php
                wp_nav_menu(array(
                    'theme_location'  => 'subfooter1',
                    'container'       => '',
                    'items_wrap'      => '%3$s',
                    'before'          => '<div class="nav-item mb-1">',
                    'after'          => '</div>',
                    'menu_id'         => false,
                    'link_class'      => 'btn cta rounded-full bg-secondary hover:border-secondary hover:text-white',
                    'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                    'walker'          => new wp_spinnr_simple_navwalker()
                ));
            ?>

            <div class="flex flex-col lg:flex-row gap lg:gap-lg items-center lg:items-end justify-end mt-lg lg:mt-0">
                <?php
                    wp_nav_menu(array(
                        'theme_location'  => 'footer',
                        'container'       => '',
                        'items_wrap'      => '%3$s',
                        'menu_id'         => false,
                        'link_class'      => $classes['class5'],
                        'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                        'walker'          => new wp_spinnr_simple_navwalker()
                    ));
                ?>
                
                <div class="<?php echo $classes['class4']; ?>">
                    <span class="text-primary">&copy; <?php echo date('Y'); ?>
                </div>
            </div>
            

        </div>

    </div>

</div>
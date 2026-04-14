<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
			'row' => 'flex flex-wrap justify-between pt-6 pb-4 lg:pb-12 '.get_theme_mod('footer_link_class', 'text-black'),
			'col1' => 'w-full lg:w-7/12',
			'col2' => 'w-full lg:w-2/12 text-sm lg:text-right mb-6 lg:mb-0 pt-1',
            'col3' => 'w-full lg:w-3/12 pt-1 text-sm lg:text-right',
            'class1' => 'hidden lg:block',
            'class2' => 'lg:hidden block mb-6',
            'class3' => 'border-t lg:hidden',
            'class4' => 'lg:flex justify-between py-4 text-sm '.get_theme_mod('footer_link_class', 'text-black'),
            'class5' => 'block lg:inline mb-3 lg:mb-0 lg:mr-12',
		);
		break;
	default:
		$classes = array(
			'row' => 'row justify-content-between pt-4 pb-3 pb-lg-5 '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'col-12 col-lg-7',
			'col2' => 'col-12 col-lg-2 small text-lg-right mb-4 mb-lg-0 pt-1',
            'col3' => 'col-12 col-lg-3 pt-1 small text-lg-right',
            'class1' => 'd-none d-lg-block',
            'class2' => 'd-lg-none mb-4',
            'class3' => 'border-top d-lg-none',
            'class4' => 'd-lg-flex justify-content-between py-3 small '.get_theme_mod('footer_link_class', 'text-dark'),
            'class5' => 'd-block d-lg-inline text-muted mb-2 mb-lg-0 mr-lg-5',
		);
		break;
}
?>

<div class="grid grid-cols-1 text-center md:text-left md:grid-cols-4 gap-8 md:gap-2 md:divide-x items-center">
    <div class="md:pr-5">
        <?php if (get_theme_mod('wp_spinnr_footer_logo')) : ?>
            <a href="https://carepharmaceuticals.com.au/" target="_blank">
                <img class="w-72 mx-auto md:ml-0" src="<?php echo esc_url(get_theme_mod('wp_spinnr_footer_logo')); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
        <?php else : ?>
            <a class="site-title <?php echo get_theme_mod('header_link_class', 'text-dark');?>" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_url(bloginfo('name')); ?></a>
        <?php endif; ?>
        <?php //dynamic_sidebar('footer-1-section-1'); ?>
    </div>
    <div class="px-8 py-3 flex flex-col">
        <p>Free Call</p>
        <a href="tel:1800788870" class="mb-3 font-bold text-xl lg:text-3xl text-primary pb-0">1800 788 870</a>
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'subfooter1',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => 'text-primary md:mr-2',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
        <?php
        // wp_nav_menu(array(
        //     'theme_location'  => 'subfooter1',
        //     'container'       => '',
        //     'items_wrap'      => '%3$s',
        //     'before'          => '<p class="mb-1">',
        //     'after'          => '</p>',
        //     'menu_id'         => false,
        //     'link_class'      => '',
        //     'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
        //     'walker'          => new wp_spinnr_simple_navwalker()
        // ));
        ?>
    </div>
    <div class="px-8 py-8">
        <a href="https://www.linkedin.com/company/care-pharmaceuticals/">
            <i class="fab fa-linkedin text-primary fa-2x"></i>
            <p>care-pharmaceuticals</p>
        </a>
        <?php //dynamic_sidebar('footer-address'); ?>
    </div>
    <div class="md:pl-8 py-4 flex flex-col gap-2">
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'footer',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => 'text-primary md:mr-2',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
        <p class="text-primary">&copy; <?php echo date('Y');?></p>
    </div>
</div>
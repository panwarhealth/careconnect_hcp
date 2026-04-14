<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
			'row1' => 'flex flex-wrap items-center py-6 '.get_theme_mod('footer_link_class', 'text-black'),
			'row2' => 'flex flex-wrap pb-3 '.get_theme_mod('footer_link_class', 'text-black'),
			'col1' => 'w-full lg:w-2/12 text-center lg:text-left',
			'col2' => 'w-full lg:w-8/12 navbar-expand text-center font-medium',
            'col3' => 'w-full lg:w-2/12 hidden lg:block text-right',
            'col4' => 'w-full lg:hidden block text-center',
            'col5' => 'w-full text-center text-sm',
            'class1' => 'navbar-nav block lg:flex justify-center',
            'class2' => 'block lg:inline text-muted mx-1',
		);
		break;
	default:
		$classes = array(
			'row1' => 'row align-items-center py-4 '.get_theme_mod('footer_link_class', 'text-dark'),
			'row2' => 'row pb-3 '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'col-12 col-lg-2 text-center text-lg-left',
			'col2' => 'col-12 col-lg-8 navbar-expand text-center weight-500',
            'col3' => 'col-12 col-lg-2 d-none d-lg-block text-right',
            'col4' => 'col-12 d-lg-none text-center',
            'col5' => 'col-12 text-center small',
            'class1' => 'navbar-nav d-block d-lg-flex justify-content-center',
            'class2' => 'd-block d-lg-inline text-muted mx-1',
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
    </div>
    <div class="<?php echo $classes['col2']; ?>">
        <?php
        wp_nav_menu(array(
            'before'          => '<div class="nav-item">',
            'after'          => '</div>',
            'theme_location'  => 'subfooter1',
            'container'       => '',
            'items_wrap'      => '<div class="%2$s">%3$s</div>',
            'menu_id'         => false,
            'menu_class'      => $classes['class1'],
            'link_class'      => 'nav-link',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
    <div class="<?php echo $classes['col3']; ?>">
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'social',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => 'transparent-invert-link mr-1',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
    <div class="<?php echo $classes['col4']; ?>">
        <?php
            wp_nav_menu(array(
                'theme_location'  => 'social',
                'container'       => '',
                'items_wrap'      => '%3$s',
                'menu_id'         => false,
                'link_class'      => 'transparent-invert-link mx-1',
                'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                'walker'          => new wp_spinnr_simple_navwalker()
            ));
        ?>
    </div>
</div>
<div class="<?php echo $classes['row2']; ?>">
    <div class="<?php echo $classes['col5']; ?>">
        <span class="text-muted mr-1">&copy; <?php echo date('Y'); ?> <?php echo esc_attr(get_bloginfo('name')); ?>. All right reserved.</span>
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'footer',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => $classes['class2'],
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
</div>
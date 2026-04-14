<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
            'row1' => 'flex flex-wrap items-center py-6 '.get_theme_mod('footer_link_class', 'text-dark'),
			'class1' => 'border-t lg:hidden '.get_theme_mod('footer_link_class', 'text-dark'),
			'class2' => 'text-center py-6 lg:py-12 '.get_theme_mod('footer_link_class', 'text-dark'),
			'class3' => 'lg:pt-6 pb-6 lg:pb-4 text-center text-sm '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'w-full lg:w-2/12 text-center lg:text-left mb-6 lg:mb-0',
			'col2' => 'w-full lg:w-8/12 navbar-expand text-center font-medium mb-6 lg:mb-0',
            'col3' => 'w-full lg:w-2/12 text-sm text-center lg:text-right',
            'class4' => 'navbar-nav block lg:flex justify-center',
            'class5' => 'block lg:inline text-muted lg:mx-1 mb-3 lg:mb-0',
		);
		break;
	default:
		$classes = array(
			'row1' => 'row align-items-center py-4 '.get_theme_mod('footer_link_class', 'text-dark'),
			'class1' => 'border-top d-lg-none '.get_theme_mod('footer_link_class', 'text-dark'),
			'class2' => 'text-center py-4 py-lg-5 '.get_theme_mod('footer_link_class', 'text-dark'),
			'class3' => 'pt-lg-4 pb-4 pb-lg-3 text-center small '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'col-12 col-lg-2 text-center text-lg-left mb-4 mb-lg-0',
			'col2' => 'col-12 col-lg-8 navbar-expand text-center weight-500 mb-4 mb-lg-0',
            'col3' => 'col-12 col-lg-2 small text-center text-lg-right',
            'class4' => 'navbar-nav d-block d-lg-flex justify-content-center',
            'class5' => 'd-block d-lg-inline text-muted mx-lg-1 mb-2 mb-lg-0',
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
            'menu_class'      => $classes['class4'],
            'link_class'      => 'nav-link '.get_theme_mod('footer_link_class', 'text-dark'),
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
    <div class="<?php echo $classes['col3']; ?>"><a class="text-muted" href=""><span>English version</span></a></div>
</div>
<div class="<?php echo $classes['class1']; ?>"></div>
<div class="<?php echo $classes['class2']; ?>">
    <?php
    wp_nav_menu(array(
        'theme_location'  => 'social',
        'container'       => '',
        'items_wrap'      => '%3$s',
        'menu_id'         => false,
        'link_class'      => 'transparent-invert-link mx-2 fa-2x',
        'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
        'walker'          => new wp_spinnr_simple_navwalker()
    ));
    ?>
</div>
<div class="<?php echo $classes['class3']; ?>">
    <p class="mb-0">
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
    </p>
    <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> <?php echo esc_attr(get_bloginfo('name')); ?>. All right reserved.</p>
</div>
<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
            'row1' => 'flex flex-wrap py-6 '.get_theme_mod('footer_link_class', 'text-dark'),
			'row2' => 'lg:flex items-center justify-between border-t py-4 lg:py-3 lg:mt-12 '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'w-full lg:w-2/12 mb-6 lg:mb-0',
			'col2' => 'w-1/2 lg:w-2/12 mb-6 lg:mb-0',
            'col3' => 'w-full lg:w-1/3',
            'small' => 'text-sm',
            'class1' => 'border-t pt-6 lg:hidden',
            'class2' => 'text-sm mb-3 lg:mb-0',
            'class3' => 'block lg:inline text-muted lg:ml-3 mb-3 lg:mb-0',
            'class4' => 'hidden lg:block ml-auto',
            'class5' => 'lg:hidden',
		);
		break;
	default:
		$classes = array(
			'row1' => 'row py-4 '.get_theme_mod('footer_link_class', 'text-dark'),
			'row2' => 'd-lg-flex align-items-center justify-content-between border-top py-3 py-lg-2 mt-lg-5 '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'col-12 col-lg-2 mb-4 mb-lg-0',
			'col2' => 'col-6 col-lg-2 mb-4 mb-lg-0',
            'col3' => 'col-12 col-lg-4',
            'small' => 'small',
            'class1' => 'border-top pt-4 d-lg-none',
            'class2' => 'small mb-2 mb-lg-0',
            'class3' => 'd-block d-lg-inline text-muted ml-lg-2 mb-2 mb-lg-0',
            'class4' => 'd-none d-lg-block ml-auto',
            'class5' => 'd-lg-none',
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
    <?php
        $theme_locations = get_nav_menu_locations();
    ?>
    <div class="<?php echo $classes['col2']; ?>">
        <h4><?php echo isset($theme_locations['subfooter1']) && property_exists(get_term( $theme_locations['subfooter1'], 'nav_menu' ), 'name') ? get_term( $theme_locations['subfooter1'], 'nav_menu' )->name : 'First column';?></h4>
        <div class="<?php echo $classes['small']; ?>">
            <?php
            wp_nav_menu(array(
                'theme_location'  => 'subfooter1',
                'container'       => '',
                'items_wrap'      => '%3$s',
                'before'          => '<div class="mb-1">',
                'after'          => '</div>',
                'menu_id'         => false,
                'link_class'      => '',
                'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                'walker'          => new wp_spinnr_simple_navwalker()
            ));
            ?>
        </div>
    </div>
    <div class="<?php echo $classes['col2']; ?>">
        <h4><?php echo isset($theme_locations['subfooter2']) && property_exists(get_term( $theme_locations['subfooter2'], 'nav_menu' ), 'name') ? get_term( $theme_locations['subfooter2'], 'nav_menu' )->name : 'Second';?></h4>
        <div class="<?php echo $classes['small']; ?>">
            <?php
            wp_nav_menu(array(
                'theme_location'  => 'subfooter2',
                'container'       => '',
                'items_wrap'      => '%3$s',
                'before'          => '<div class="mb-1">',
                'after'          => '</div>',
                'menu_id'         => false,
                'link_class'      => '',
                'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                'walker'          => new wp_spinnr_simple_navwalker()
            ));
            ?>
        </div>
    </div>
    <div class="<?php echo $classes['col2']; ?>">
        <h4><?php echo isset($theme_locations['subfooter3']) && property_exists(get_term( $theme_locations['subfooter3'], 'nav_menu' ), 'name') ? get_term( $theme_locations['subfooter3'], 'nav_menu' )->name : 'Third';?></h4>
        <div class="<?php echo $classes['small']; ?>">
        <?php
            wp_nav_menu(array(
                'theme_location'  => 'subfooter3',
                'container'       => '',
                'items_wrap'      => '%3$s',
                'before'          => '<div class="mb-1">',
                'after'          => '</div>',
                'menu_id'         => false,
                'link_class'      => '',
                'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                'walker'          => new wp_spinnr_simple_navwalker()
            ));
            ?>
        </div>
    </div>
    <div class="<?php echo $classes['col3']; ?>">
        <div class="<?php echo $classes['class1']; ?>"></div>
        <h4>Subscribe</h4>
        <?php dynamic_sidebar('footer-8-subscribe'); ?> 
    </div>
</div>
<div class="<?php echo $classes['row2']; ?>">
    <div class="<?php echo $classes['class2']; ?>"><span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo esc_attr(get_bloginfo('name')); ?>. All right reserved.</span></div>
    <div class="<?php echo $classes['small']; ?>">
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'footer',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => $classes['class3'],
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
    <div class="<?php echo $classes['class4']; ?>">
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'social',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => 'transparent-invert-link ml-2',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
    <div class="<?php echo $classes['class5']; ?>">
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'social',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => 'transparent-invert-link ml-2',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
</div>
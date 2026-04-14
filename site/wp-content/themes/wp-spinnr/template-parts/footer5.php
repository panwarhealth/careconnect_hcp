<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
            'row1' => 'flex flex-wrap pt-6 mb-6 lg:mb-12 '.get_theme_mod('footer_link_class', 'text-dark'),
			'class1' => 'border-t lg:hidden '.get_theme_mod('footer_link_class', 'text-dark'),
			'class2' => 'block lg:flex justify-between py-4 lg:py-3 text-sm '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'w-full lg:w-1/3 mb-6 lg:mb-0',
			'col2' => 'w-1/2 lg:w-1/6',
            'small' => 'text-sm',
            'class3' => 'mt-4 text-sm text-muted',
            'class31' => 'mt-4 lg:hidden',
            'class32' => 'mt-4 hidden lg:block',
            'class33' => 'border-t mt-4 lg:hidden',
            'class4' => 'text-sm mb-3 lg:mb-0',
            'class5' => 'block lg:inline text-muted lg:ml-3 mb-3 lg:mb-0',
		);
		break;
	default:
		$classes = array(
			'row1' => 'row pt-4 mb-4 mb-lg-5 '.get_theme_mod('footer_link_class', 'text-dark'),
			'class1' => 'border-top d-lg-none '.get_theme_mod('footer_link_class', 'text-dark'),
			'class2' => 'd-block d-lg-flex justify-content-between py-3 py-lg-2 small '.get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'col-12 col-lg-4 mb-4 mb-lg-0',
			'col2' => 'col-6 col-lg-2',
            'small' => 'small',
            'class3' => 'mt-3 small text-muted',
            'class31' => 'mt-3 d-lg-none',
            'class32' => 'mt-3 d-none d-lg-block',
            'class33' => 'border-top mt-3 d-lg-none',
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

        <div class="<?php echo $classes['class3']; ?>">
            <?php dynamic_sidebar('footer-address'); ?> 
        </div>
        <div class="<?php echo $classes['class31']; ?>">
            <?php
            wp_nav_menu(array(
                'theme_location'  => 'social',
                'container'       => '',
                'items_wrap'      => '%3$s',
                'menu_id'         => false,
                'link_class'      => 'transparent-invert-link mr-2',
                'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                'walker'          => new wp_spinnr_simple_navwalker()
            ));
            ?>
        </div>
        <div class="<?php echo $classes['class32']; ?>">
            <?php
            wp_nav_menu(array(
                'theme_location'  => 'social',
                'container'       => '',
                'items_wrap'      => '%3$s',
                'menu_id'         => false,
                'link_class'      => 'transparent-invert-link mr-2',
                'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                'walker'          => new wp_spinnr_simple_navwalker()
            ));
            ?>
        </div>
        <div class="<?php echo $classes['class33']; ?>"></div>
    </div>
    <?php
        $theme_locations = get_nav_menu_locations();
    ?>
    <div class="<?php echo $classes['col2']; ?>">
        <h4><?php echo isset($theme_locations['subfooter1']) && property_exists(get_term( $theme_locations['subfooter1'], 'nav_menu' ), 'name') ? get_term( $theme_locations['subfooter1'], 'nav_menu' )->name : 'First Column';?></h4>
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
        <h4><?php echo isset($theme_locations['subfooter2']) && property_exists(get_term( $theme_locations['subfooter2'], 'nav_menu' ), 'name') ? get_term( $theme_locations['subfooter2'], 'nav_menu' )->name : 'Second Column';?></h4>
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
        <h4><?php echo isset($theme_locations['subfooter3']) && property_exists(get_term( $theme_locations['subfooter3'], 'nav_menu' ), 'name') ? get_term( $theme_locations['subfooter3'], 'nav_menu' )->name : 'Third Column';?></h4>
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
    <div class="<?php echo $classes['col2']; ?>">
        <?php dynamic_sidebar('footer-5-mobile-apps'); ?>
    </div>
</div>
<div class="<?php echo $classes['class1']; ?>"></div>
<div class="<?php echo $classes['class2']; ?>">
    <div class="<?php echo $classes['class4']; ?>"><span class="text-muted mr-5">&copy; <?php echo date('Y'); ?> <?php echo esc_attr(get_bloginfo('name')); ?>. All right reserved.</span></div>
    <div class="<?php echo $classes['small']; ?>">
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
    </div>
</div>
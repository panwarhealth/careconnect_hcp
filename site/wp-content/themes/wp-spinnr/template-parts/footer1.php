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

<div class="<?php echo $classes['row']; ?>">
    <div class="<?php echo $classes['col1']; ?>">
        <?php dynamic_sidebar('footer-1-section-1'); ?>
        <div class="<?php echo $classes['class1']; ?>">
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
        <div class="<?php echo $classes['class2']; ?>">
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
    </div>
    <div class="<?php echo $classes['col2']; ?>">
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'subfooter1',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'before'          => '<p class="mb-1">',
            'after'          => '</p>',
            'menu_id'         => false,
            'link_class'      => '',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
    <div class="<?php echo $classes['col3']; ?>">
        <?php dynamic_sidebar('footer-address'); ?>
    </div>
</div>
<div class="<?php echo $classes['class3']; ?>"></div>
<div class="<?php echo $classes['class4']; ?>">
    <div>
        <span class="<?php echo $classes['class4']; ?>">&copy; <?php echo date('Y');?> <?php echo esc_attr(get_bloginfo('name')); ?>. All right reserved.</span>
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'footer',
            'container'       => '',
            'items_wrap'      => '%3$s',
            'menu_id'         => false,
            'link_class'      => 'text-muted mr-2',
            'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
            'walker'          => new wp_spinnr_simple_navwalker()
        ));
        ?>
    </div>
    <div><?php dynamic_sidebar('footer-1-section-2'); ?></div>
</div>
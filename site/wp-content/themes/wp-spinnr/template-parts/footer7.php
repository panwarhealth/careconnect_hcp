<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
            'row1' => 'lg:flex justify-between py-4 lg:py-3 '.get_theme_mod('footer_link_class', 'text-dark'),
			'class1' => 'text-sm mb-3 lg:mb-0',
            'small' => 'text-sm',
			'class3' => 'block lg:inline text-muted lg:ml-4 mb-3 lg:mb-0',
		);
		break;
	default:
		$classes = array(
			'row1' => 'd-lg-flex justify-content-between py-3 py-lg-2 '.get_theme_mod('footer_link_class', 'text-dark'),
            'class1' => 'small mb-2 mb-lg-0',
            'small' => 'small',
			'class3' => 'd-block d-lg-inline text-muted ml-lg-3 mb-2 mb-lg-0',
		);
		break;
}
?>
<div class="<?php echo $classes['row1']; ?>">
    <div class="<?php echo $classes['class1']; ?>"><span class="text-muted mr-5">&copy; <?php echo date('Y'); ?> <?php echo esc_attr(get_bloginfo('name')); ?>. All right reserved.</span></div>
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
</div>
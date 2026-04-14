<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
            'row1' => 'flex flex-wrap '.get_theme_mod('footer_link_class', 'text-dark'),
			'row2' => get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'w-full lg:w-1/3',
			'col2' => 'w-full lg:w-1/3',
            'col3' => 'w-full lg:w-1/3',
		);
		break;
	default:
		$classes = array(
			'row1' => 'row '.get_theme_mod('footer_link_class', 'text-dark'),
			'row2' => get_theme_mod('footer_link_class', 'text-dark'),
			'col1' => 'col-12 col-lg-4',
			'col2' => 'col-12 col-lg-4',
            'col3' => 'col-12 col-lg-4',
		);
		break;
}
?>
<div class="<?php echo $classes['row1']; ?>">
    <div class="<?php echo $classes['col1']; ?>">
        <?php dynamic_sidebar('footer-col-1'); ?>
    </div>
    <div class="<?php echo $classes['col2']; ?>">
        <?php dynamic_sidebar('footer-col-2'); ?>
    </div>
    <div class="<?php echo $classes['col3']; ?>">
        <?php dynamic_sidebar('footer-col-3'); ?>
    </div>
</div>
<div class="<?php echo $classes['row2']; ?>">
    <?php dynamic_sidebar('footer-copyright'); ?>
</div>
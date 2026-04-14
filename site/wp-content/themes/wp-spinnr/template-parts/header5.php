<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
            'navbar' => 'navbar navbar-expand-lg justify-between',
            'class1' => 'w-auto lg:w-4/12 px-0',
            'class2' => 'w-2/12 hidden lg:block text-center px-0',
            'class3' => 'w-4/12 hidden lg:flex justify-end items-center font-medium px-0',
            'class4' => 'lg:hidden',
            'class6' => 'flex flex-wrap flex-col justify-between',
            'class7' => 'block xl:inline text-muted mb-1 xl:mb-0 mr-3',
            'small' => 'text-sm',
            'weight-500' => 'font-medium',
            'weight-700' => 'font-bold',
		);
		break;
	default:
		$classes = array(
            'navbar' => 'navbar navbar-expand-lg justify-content-between',
            'class1' => 'col col-lg-4 px-0',
            'class2' => 'col-2 d-none d-lg-block text-center px-0',
            'class3' => 'col-4 d-none d-lg-flex justify-content-end align-items-center weight-500 px-0',
            'class4' => 'd-lg-none',
            'class6' => 'd-flex flex-column justify-content-between',
            'class7' => 'd-block d-xl-inline text-muted mb-1 mb-xl-0 mr-2',
            'small' => 'small',
            'weight-500' => 'weight-500',
            'weight-700' => 'weight-700',
		);
		break;
}
?>
<div class="<?php echo $classes['navbar']; ?>">
    <div class="<?php echo $classes['class1']; ?>">
        <div class="nav-item">
            <a class="navbar-toggler mr-1" href="javascript:void(0)" data-id="menu-sidebar-toggle">    
                <i class="fas fa-bars fa-2x <?php echo get_theme_mod('header_link_class', 'text-dark');?>"></i>
            </a>
        </div>
    </div>
    <div class="<?php echo $classes['class2']; ?>">
        <?php if (get_theme_mod('wp_spinnr_logo')) : ?>
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <img class="navbar-logo" src="<?php echo esc_url(get_theme_mod('wp_spinnr_logo')); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
        <?php else : ?>
            <a class="site-title <?php echo get_theme_mod('header_link_class', 'text-dark');?>" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_url(bloginfo('name')); ?></a>
        <?php endif; ?>
    </div>
    <?php
    wp_nav_menu(array(
        'theme_location'  => 'primary',
        'container'       => 'div',
        'container_id'    => 'main-nav',
        'container_class' => $classes['class3'],
        'items_wrap'      => '<div class="%2$s">%3$s</div>',
        'menu_id'         => false,
        'menu_class'      => 'navbar-nav m-auto',
        'depth'           => 3,
        'fallback_cb'     => 'wp_spinnr_navwalker::fallback',
        'walker'          => new wp_spinnr_navwalker()
    ));
    ?>
    <div class="<?php echo $classes['class4']; ?>">
        <div class="nav-item"><a class="nav-link <?php echo get_theme_mod('header_link_class', 'text-dark');?> pr-0" href=""><i class="fas fa-search"></i></a></div>
    </div>
</div>
<div class="navbar-side" id="menu-sidebar">
    <div class="navbar-side-content <?php echo $classes['class6']; ?> <?php echo get_theme_mod('header_class', 'bg-light');?>">
        <a class="navbar-side-close mb-4 <?php echo get_theme_mod('header_link_class', 'text-dark');?>" href="javascript:void(0)" data-id="menu-sidebar-close"><i class="far fa-window-close"></i></a>
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'primary',
            'container'       => 'div',
            'container_id'    => 'main-nav',
            'container_class' => $classes['weight-700'],
            'menu_id'         => false,
            'menu_class'      => 'navbar-nav',
            'depth'           => 3,
            'fallback_cb'     => 'wp_spinnr_navwalker::fallback',
            'walker'          => new wp_spinnr_navwalker()
        ));
        ?>
        <div>
            <p class="mb-3">
                <?php
                wp_nav_menu(array(
                    'theme_location'  => 'social',
                    'container'       => '',
                    'items_wrap'      => '%3$s',
                    'menu_id'         => false,
                    'link_class'      => 'transparent-invert-link mr-2 '.get_theme_mod('header_link_class', 'text-dark'),
                    'fallback_cb'     => 'wp_spinnr_simple_navwalker::fallback',
                    'walker'          => new wp_spinnr_simple_navwalker()
                ));
                ?>
            </p>
            <p class="<?php echo $classes['small']; ?> mb-0 <?php echo get_theme_mod('header_link_class', 'text-dark');?>">
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
            </p>
        </div>
    </div>
</div>
<script>
    (function($) {
        $('[data-id=menu-sidebar-toggle]').click(function(e) {
            e.preventDefault();
            $('#menu-sidebar').toggleClass('show');
        });

        $('[data-id=menu-sidebar-close]').click(function(e) {
            e.preventDefault();
            $('#menu-sidebar').removeClass('show');
        });
    }(jQuery));
</script>
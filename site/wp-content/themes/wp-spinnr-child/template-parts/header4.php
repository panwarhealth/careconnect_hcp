<?php
switch(TBST_SPINNR_FRAMEWORK){
	case 'tailwind-2':
		$classes = array(
            'navbar' => 'container mx-auto flex flex-wrap p-5 flex-row items-center',
            'class1' => 'lg:hidden',
            'class2' => 'md:ml-auto flex flex-wrap items-center text-base justify-center',
            'class3' => 'lg:hidden ml-auto',
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
            'class1' => 'd-lg-none',
            'class2' => 'flex-grow-0 weight-500',
            'class3' => 'd-lg-none ml-auto',
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
    <div>
        <?php if (get_theme_mod('wp_spinnr_logo')) : 
            $home_url = "/";
            if( is_user_logged_in() ) {
                if ( rcp_user_has_active_membership(get_current_user_id()) ) {
                    $customer = rcp_get_customer_by_user_id(get_current_user_id());
                    if(in_array( "Pharmacist", rcp_get_customer_membership_level_names($customer->get_id()) )) {
                        $home_url = "/welcome-pharmacist/";
                    } else if (count( array_intersect( rcp_get_customer_membership_level_names($customer->get_id()), array( "HCP", "Staff" ) ) )){
                        $home_url = "/welcome/";
                    }
                }
            }

            ?>
            <a href="<?php echo $home_url; ?>">
                <img class="navbar-logo" src="<?php echo esc_url(get_theme_mod('wp_spinnr_logo')); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
        <?php else : ?>
            <a class="site-title <?php echo get_theme_mod('header_link_class', 'text-dark');?>" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_url(bloginfo('name')); ?></a>
        <?php endif; ?>
    </div>
    <p class="ml-10 pt-4 text-primary"><?php	
			$current_user = wp_get_current_user();
		if($current_user)
			echo 'Welcome '.$current_user->user_firstname?>, <a href="/wp-login.php?action=logout" class="ml-md">Logout</a></p>
    <?php
    $checked = true;
    if( is_page(array(3,229)) ) { // Privacy Policy & Terms of Use
        if(get_current_user_id()) {
            $checked = true;
        }
        else {
            $checked = false;
        }
    } else if(is_page(array(123,2774)) || is_404()) { // Login, Register, 404
        $checked = false;
    }
    if ( $checked ) : // Login, Register, 404 || Privacy Policy & Terms of Use
	
	if(!is_page_template( 'page-withoutnav.php' )):
    ?>
    <div class="<?php echo $classes['class1']; ?>">
        <a class="navbar-toggler mr-1" href="javascript:void(0)" data-id="menu-sidebar-toggle">    
            <i class="fas fa-bars fa-2x <?php echo get_theme_mod('header_link_class', 'text-dark');?>"></i>
        </a>
    </div>
    <?php
    // $theme_location = (!is_page(array(123,2774))) ? 'primary' : 'primary-alt';
        wp_nav_menu(array(
            'theme_location'  => 'primary',
            'container'       => 'div',
            'container_id'    => 'main-nav',
            'container_class' => 'hidden lg:block '.$classes['class2'],
            'items_wrap'      => '<div class="%2$s">%3$s</div>',
            'menu_id'         => false,
            'menu_class'      => 'm-auto flex flex-row gap-4 items-center',
            'depth'           => 3,
            'fallback_cb'     => 'wp_spinnr_navwalker::fallback',
            'walker'          => new wp_spinnr_navwalker()
        ));
    endif;
	endif;
    ?>
</div>
<div class="navbar-side bg-white" id="menu-sidebar">
    <div class="navbar-side-content <?php echo $classes['class6']; ?> <?php echo get_theme_mod('header_class', 'bg-gray-300');?>">
        <a class="navbar-side-close mt-12 text-3xl mb-4 <?php echo get_theme_mod('header_link_class', 'text-dark');?>" href="javascript:void(0)" data-id="menu-sidebar-close"><i class="far fa-window-close"></i></a>
        <?php
        wp_nav_menu(array(
            'theme_location'  => 'primary',
            'container'       => 'div',
            'container_id'    => 'main-nav',
            'container_class' => $classes['weight-700'],
            'menu_id'         => false,
            'menu_class'      => 'navbar-nav flex flex-col gap-y-6',
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
                <span class="block">&copy; <?php echo date('Y');?> <?php echo esc_attr(get_bloginfo('name')); ?>. All right reserved.</span>
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
<?php

/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WP_SPINNR
 */

get_header();

$add_section = true;
$show_sidebar = true;

// Check if post is locked to SPINNR
$post_types = explode(',', get_option('lock_editor'));
if (count($post_types) > 0 && in_array(get_post_type(), $post_types)) {
	$add_section = false;
}

if (class_exists('Woocommerce')){
	if(is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url()){
		$add_section = true;
		$show_sidebar = false;
	}
}

if ($add_section) :
?>
	<div class="section">
		<div class="container grid <?php echo $show_sidebar ? 'lg:grid-cols-12' : ''; ?>">
			<?php if (is_archive()) : ?>
				<div class="column col-span-full">
					<?php
					the_archive_title('<h1 class="page-title">', '</h1>');
					the_archive_description('<div class="archive-description">', '</div>');
					?>
				</div>
			<?php endif; ?>
			<?php if (is_search()) : ?>
				<div class="column col-span-full">
					<h1 class="page-title"><?php printf(esc_html__('Search Results for: %s', 'wp-spinnr'), '<span>' . get_search_query() . '</span>'); ?></h1>
				</div>
			<?php endif; ?>
			<?php if (class_exists('Woocommerce')) : 
				if(is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url()): ?>
				<div class="column col-span-full">
					<?php
						the_title('<h2 class="page-title">', '</h2>');
					?>
				</div>
				<?php endif; 
			endif; ?>
			<div class="column <?php echo $show_sidebar ? 'lg:col-span-7' : ''; ?>">
				<div id="primary" class="content-area">
					<main id="main" class="site-main" role="main">

						<?php
						if (have_posts()) :

							/* Start the Loop */
							while (have_posts()) : the_post();

								/*
								* Include the Post-Format-specific template for the content.
								* If you want to override this in a child theme, then include a file
								* called content-___.php (where ___ is the Post Format name) and that will be used instead.
								*/
								
								if (is_single()) {

									get_template_part('template-parts/content', 'single');

									// If comments are open or we have at least one comment, load up the comment template.
									if (comments_open() || get_comments_number()) :
										comments_template();
									endif;

								} 
								elseif (class_exists('Woocommerce')){
									if(is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url()){

										get_template_part('template-parts/content', 'blank');

									} else {

										get_template_part('template-parts/content', get_post_format());
										
									}
								}
								else {

									get_template_part('template-parts/content', get_post_format());

								}

							endwhile;

							the_posts_navigation();

						else :

							get_template_part('template-parts/content', 'none');

						endif; ?>

					</main><!-- #main -->
				</div><!-- #primary -->
			</div>
			<?php if ($show_sidebar) get_sidebar(); ?>
		</div>
	</div>

<?php else : ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php
			if (have_posts()) :

				/* Start the Loop */
				while (have_posts()) : the_post();

					/*
                * Include the Post-Format-specific template for the content.
                * If you want to override this in a child theme, then include a file
                * called content-___.php (where ___ is the Post Format name) and that will be used instead.
                */
					get_template_part('template-parts/content', 'blank');

				endwhile;

				the_posts_navigation();

			else :

				get_template_part('template-parts/content', 'none');

			endif; ?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
endif;
get_footer();

<?php

/**
 * The sidebar containing the main widget area
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WP_SPINNR
 */

if (is_active_sidebar('sidebar')) : ?>
	<div id="secondary" class="column lg:col-span-4 lg:col-start-9">
		<aside class="widget-area" role="complementary">
			<?php dynamic_sidebar('sidebar'); ?>
		</aside>
	</div><!-- #secondary -->
<?php else : ?>
	<div id="secondary" class="column lg:col-span-4 lg:col-start-9">
		<aside class="widget-area space-y-xl" role="complementary">
			<div class="widget nav-sidebar">
				<?php get_search_form(); ?>
			</div>

			<div class="widget nav-sidebar">
				<div class="border border-stroke rounded p-lg">
					<h6 class="font-semibold uppercase"><?php _e('Recent Posts', 'wp-spinnr'); ?></h6>
					<div class="border-t border-stroke pt-base space-y-xl">
						<?php
						$recent_posts = wp_get_recent_posts(array(
							'numberposts' => 4,
							'offset'      => 0,
							'orderby'     => 'post_date',
							'post_status' => 'publish'
						));
						foreach ($recent_posts as $r) :  ?>
							<div class="grid grid-cols-3">
								<div class="">
									<?php if (has_post_thumbnail($r['ID'])) : ?>
										<img class="w-full h-full object-cover mb-0" src="<?php echo get_the_post_thumbnail_url($r['ID']); ?>" alt="<?php echo get_the_post_thumbnail_caption($r['ID']); ?>" />
									<?php else : ?>
										<img class="w-full h-full object-cover mb-0" src="<?php echo get_template_directory_uri(); ?>/inc/assets/images/img-placeholder.jpg" alt="" />
									<?php endif; ?>
								</div>
								<div class="col-span-2">
									<h6 class="text-base leading-tight"><?php echo wp_trim_words($r['post_title'], 6); ?></h6>
									<a href="<?php echo get_permalink($r['ID']); ?>">Read more</a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="widget nav-sidebar">
				<div class="border border-stroke rounded p-lg">
					<h6 class="font-semibold uppercase"><?php _e('Categories', 'wp-spinnr'); ?></h6>
					<div class="border-t border-stroke pt-base space-y-xl">
						<ul class="list-none"><?php wp_list_categories('show_count=1&title_li='); ?></ul>
					</div>
				</div>
			</div>

			<div class="widget nav-sidebar">
				<div class="border border-stroke rounded p-lg">
					<h6 class="font-semibold uppercase"><?php _e('Archives', 'wp-spinnr'); ?></h6>
					<div class="border-t border-stroke pt-base space-y-xl">
						<ul class="list-none"><?php wp_get_archives('monthly'); ?></ul>
					</div>
				</div>
			</div>

		</aside>
	</div><!-- #secondary -->
<?php endif; ?>
<?php

/**
 * Template part for displaying generic posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WP_SPINNR
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="grid md:grid-cols-12 border-b border-stroke pb-xl mb-xl">
		<?php if (has_post_thumbnail()) : ?>
			<div class="post-thumbnail md:col-span-5">
				<?php the_post_thumbnail('post-thumbnail', ['class' => 'thumbnail mb-0']); ?>
			</div>
			<div class="md:col-span-7 flex flex-col justify-between">
			<?php else : ?>
				<div class="md:col-span-full flex flex-col justify-between">
				<?php endif; ?>
				<div>
					<?php
					$categories = get_the_category();
					if (!empty($categories)) : ?>
						<div class="flex flex-wrap">
							<?php foreach ($categories as $category) : ?>
								<a class="no-underline mb-2 mr-2 bg-secondary rounded text-sm text-paragraph px-2 py-0.5" href="<?php echo esc_url(get_category_link($category->term_id)); ?>"><?php echo esc_html($category->name); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<a class="no-underline" href="<?php echo the_permalink(); ?>"><?php the_title('<h4 class="hover:text-accent hover:underline">', '</h4>'); ?></a>
					<?php the_excerpt(); ?>
				</div>
				<div class="w-full flex justify-between text-sm">
					<div>
						by <a class="text-sm" href="/author/<?php echo get_the_author_meta('user_nicename'); ?>"><?php the_author_meta('display_name'); ?></a>
					</div>
					<div>
						<?php echo get_the_date('F j, Y'); ?>
					</div>
				</div>
				</div>
			</div>
</article><!-- #post-## -->
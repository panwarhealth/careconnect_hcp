<?php

/**
 * Template part for displaying single posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WP_SPINNR
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php
		$categories = get_the_category();
		if (!empty($categories)) : ?>
			<div class="flex flex-wrap mb-base">
				<?php foreach ($categories as $category) : ?>
					<a class="no-underline mb-2 mr-2 bg-secondary rounded text-sm text-paragraph px-2 py-0.5" href="<?php echo esc_url(get_category_link($category->term_id)); ?>"><?php echo esc_html($category->name); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php

		the_title('<h1 class="entry-title">', '</h1>');

		if ('post' === get_post_type()) : ?>
			<div class="entry-meta mb-base">
				<div class="w-full flex justify-between">
					<div>
						by <a href="/author/<?php echo get_the_author_meta('user_nicename'); ?>"><?php the_author_meta('display_name'); ?></a>
					</div>
					<div>
						<?php echo get_the_date('F j, Y'); ?>
					</div>
				</div>
			</div>
		<?php
		endif; ?>
	</header><!-- .entry-header -->
	<div class="post-thumbnail">
		<?php the_post_thumbnail('large', ['class' => '']); ?>
	</div>
	<div class="entry-content">
		<?php the_content(); ?>
	</div><!-- .entry-content -->

	<footer class="entry-footer mt-3xl">
		<?php
		edit_post_link(
			sprintf(
				esc_html__('Edit %s', 'wp-spinnr'),
				get_the_title()
			),
			'<span class="edit-link">',
			'</span>'
		);
		?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->
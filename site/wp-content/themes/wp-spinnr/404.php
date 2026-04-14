<?php

/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package WP_SPINNR
 */

get_header();
?>

<div class="section">
	<div class="container grid lg:grid-cols-2">
		<div class="column">
			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title"><?php esc_html_e('Oops! That page can&rsquo;t be found.', 'wp-spinnr'); ?></h1>
				</header><!-- .page-header -->

				<div class="page-content">
					<p><?php esc_html_e('It looks like nothing was found at this location. Maybe try one of the links below or a search?', 'wp-spinnr'); ?></p>

					<?php
					get_search_form();
					?>

				</div><!-- .page-content -->
			</section><!-- .error-404 -->
		</div><!-- .column -->
	</div><!-- .container -->
</div><!-- .section -->

<?php
get_footer();

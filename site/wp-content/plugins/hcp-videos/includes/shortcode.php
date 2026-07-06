<?php
/**
 * [video_grid] — lists "listed" videos as cards that LINK to the single video
 * pages (no inline play). Reuses the existing resources-card markup/classes so
 * it visually matches the rest of the site.
 *
 * Attributes:
 *   audience="slug"       — optional, filter by an audience term slug
 *   topic="slug"          — optional, filter by a video_topic term slug
 *   exclude_topic="slug"  — optional, omit videos in a video_topic term slug
 *   series_total="4"      — optional, label cards "Episode N" and show a
 *                           "coming soon" note until N episodes exist (0 = off)
 *   columns="3"           — optional, grid columns: 1, 2, or 3 (default 3)
 *   limit="-1"            — optional, max videos (default all)
 */

defined( 'ABSPATH' ) || exit;

function hcp_videos_grid_shortcode( $atts ): string {
	$atts = shortcode_atts( array(
		'audience'      => '',
		'topic'         => '',
		'exclude_topic' => '',
		'series_total'  => 0,
		'columns'       => 3,
		'limit'         => -1,
	), $atts, 'video_grid' );

	$args = array(
		'post_type'      => 'video',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['limit'],
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
		// Listed only: video_listed truthy. Treat missing meta as listed.
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => 'video_listed', 'value' => '1' ),
			array( 'key' => 'video_listed', 'compare' => 'NOT EXISTS' ),
		),
	);

	$tax_query = array();
	if ( $atts['audience'] !== '' ) {
		$tax_query[] = array( 'taxonomy' => 'audience', 'field' => 'slug', 'terms' => $atts['audience'] );
	}
	if ( $atts['topic'] !== '' ) {
		$tax_query[] = array( 'taxonomy' => 'video_topic', 'field' => 'slug', 'terms' => $atts['topic'] );
	}
	if ( $atts['exclude_topic'] !== '' ) {
		$tax_query[] = array( 'taxonomy' => 'video_topic', 'field' => 'slug', 'terms' => $atts['exclude_topic'], 'operator' => 'NOT IN' );
	}
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query;
	}

	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) {
		wp_reset_postdata();
		return '';
	}

	$series_total = (int) $atts['series_total'];
	$is_series    = $series_total > 0;

	$cols      = max( 1, (int) $atts['columns'] );
	$grid_cols = $cols === 1 ? '' : ( $cols === 2 ? 'md:grid-cols-2' : 'lg:grid-cols-3 md:grid-cols-2' );

	ob_start();
	echo '<div id="postgrid" class="grid ' . esc_attr( $grid_cols ) . ' gap-base">';
	$n = 0;
	while ( $q->have_posts() ) {
		$q->the_post();
		$n++;
		$id      = get_the_ID();
		$thumb   = hcp_videos_thumb_url( $id, 'medium' );
		$eyebrow = $is_series ? 'Episode ' . $n : hcp_videos_audience_label( $id );
		$icon    = hcp_videos_play_icon();
		?>
		<a class="no-underline" href="<?php echo esc_url( get_permalink( $id ) ); ?>">
			<div class="card h-full overflow-hidden">
				<div class="bg-secondary p-md h-48 rounded-t relative">
					<?php echo $icon; ?>
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" class="h-full object-contain mx-auto" alt="<?php echo esc_attr( get_the_title( $id ) ); ?>" />
					<?php endif; ?>
					<?php echo hcp_videos_duration_badge( $id ); ?>
				</div>
				<div class="card-body">
					<div>
						<?php if ( $eyebrow ) : ?><p class="text-sm text-black"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
						<h5 class="min-h-14"><?php echo esc_html( get_the_title( $id ) ); ?></h5>
					</div>
					<span class="underline text-accent font-semibold">Watch Video</span>
				</div>
			</div>
		</a>
		<?php
	}
	echo '</div>';
	if ( $is_series && $n < $series_total ) {
		echo '<p class="hcp-soon-note">Stay tuned &mdash; Episode ' . (int) ( $n + 1 ) . ' of ' . (int) $series_total . ' coming soon.</p>';
	}
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'video_grid', 'hcp_videos_grid_shortcode' );

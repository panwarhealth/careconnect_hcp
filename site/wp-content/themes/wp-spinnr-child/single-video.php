<?php
/**
 * Single Video template (CAPH0138) — YouTube-style layout.
 *
 * Main column: responsive Vimeo player + title + audience + description.
 * Sidebar:     300x250 ad slot, then related videos.
 * Responsive:  single column by default (sidebar stacks under description);
 *              splits to main + sidebar at lg.
 *
 * Gating: when logged out, the whole section carries `logged_in_users_only` so
 * the footer login/register overlay covers the entire page (matching the site
 * pattern), and the Vimeo embed URL is never output to non-members. Registration
 * is AHPRA-gated, so "logged in" == verified HCP.
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<?php while ( have_posts() ) : the_post();
			$post_id   = get_the_ID();
			$vimeo_id  = function_exists( 'hcp_videos_vimeo_id' ) ? hcp_videos_vimeo_id( get_field( 'vimeo' ) ) : '';
			$player    = function_exists( 'hcp_videos_player_url' ) ? hcp_videos_player_url( $vimeo_id ) : '';
			$audience  = function_exists( 'hcp_videos_audience_label' ) ? hcp_videos_audience_label( $post_id ) : '';
			$poster    = function_exists( 'hcp_videos_thumb_url' ) ? hcp_videos_thumb_url( $post_id, 'large' ) : '';
			$gated     = ! is_user_logged_in();
		?>
		<div class="section <?php echo $gated ? 'logged_in_users_only' : ''; ?>" style="padding-bottom:12rem;">
			<div class="container grid lg:grid-cols-12 gap-4xl">

				<!-- MAIN COLUMN -->
				<div class="column lg:col-span-8">

					<div class="relative w-full rounded-lg overflow-hidden bg-black" style="aspect-ratio:16/9;">
						<?php if ( ! $gated && $player ) : ?>
							<iframe src="<?php echo esc_url( $player ); ?>"
								class="absolute inset-0 w-full h-full"
								frameborder="0"
								allow="autoplay; fullscreen; picture-in-picture"
								allowfullscreen
								title="<?php echo esc_attr( get_the_title() ); ?>"></iframe>
						<?php elseif ( $poster ) : ?>
							<img src="<?php echo esc_url( $poster ); ?>" class="absolute inset-0 w-full h-full object-cover" alt="<?php echo esc_attr( get_the_title() ); ?>" />
						<?php endif; ?>
					</div>

					<?php if ( $audience ) : ?>
						<p class="text-sm text-accent font-semibold mt-base mb-0"><?php echo esc_html( $audience ); ?></p>
					<?php endif; ?>

					<h1 class="text-2xl mt-1"><?php the_title(); ?></h1>

					<div class="content-block">
						<?php the_content(); ?>
					</div>
				</div>

				<!-- SIDEBAR (stacks under main on mobile) -->
				<aside class="column lg:col-span-4">

					<?php
					// 300x250 ad slot — top of the sidebar. Real ad if set, else a
					// mock placeholder so the slot is always visible.
					$ad_image = get_field( 'ad_image' );
					$ad_link  = get_field( 'ad_link' );
					?>
					<div class="mb-2xl flex lg:justify-start justify-center">
						<?php if ( $ad_image && ! empty( $ad_image['url'] ) ) : ?>
							<?php if ( $ad_link ) : ?><a href="<?php echo esc_url( $ad_link ); ?>" target="_blank" rel="noopener nofollow"><?php endif; ?>
								<img src="<?php echo esc_url( $ad_image['url'] ); ?>"
									width="300" height="250"
									style="width:300px;height:250px;object-fit:cover;max-width:100%;border-radius:.5rem;"
									alt="<?php echo esc_attr( $ad_image['alt'] ?? '' ); ?>" />
							<?php if ( $ad_link ) : ?></a><?php endif; ?>
						<?php else : ?>
							<div style="width:300px;height:250px;max-width:100%;border:2px dashed #c9ced6;background:#f3f5f6;border-radius:.5rem;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9aa3af;text-align:center;line-height:1.4;">
								<span style="font-weight:700;letter-spacing:.05em;text-transform:uppercase;font-size:.75rem;">Advertisement</span>
								<span style="font-size:.875rem;">300 × 250</span>
							</div>
						<?php endif; ?>
					</div>

					<?php
					// Related videos.
					$related = function_exists( 'hcp_videos_related_ids' ) ? hcp_videos_related_ids( $post_id, 6 ) : array();
					if ( $related ) : ?>
						<h5 class="mb-base">Related videos</h5>
						<div class="space-y-base hcp-related-scroll">
							<?php foreach ( $related as $rid ) :
								$rthumb = function_exists( 'hcp_videos_thumb_url' ) ? hcp_videos_thumb_url( $rid, 'medium' ) : '';
								$raud   = function_exists( 'hcp_videos_audience_label' ) ? hcp_videos_audience_label( $rid ) : '';
							?>
								<a class="no-underline flex gap-base items-start" href="<?php echo esc_url( get_permalink( $rid ) ); ?>">
									<div class="bg-secondary rounded relative flex-shrink-0 overflow-hidden" style="width:140px;height:80px;">
										<?php echo function_exists( 'hcp_videos_play_icon' ) ? hcp_videos_play_icon() : ''; ?>
										<?php if ( $rthumb ) : ?>
											<img src="<?php echo esc_url( $rthumb ); ?>" class="w-full h-full object-cover" alt="" />
										<?php endif; ?>
										<?php echo hcp_videos_duration_badge( $rid ); ?>
									</div>
									<div>
										<?php if ( $raud ) : ?><p class="text-xs text-black mb-0"><?php echo esc_html( $raud ); ?></p><?php endif; ?>
										<p class="font-semibold text-paragraph text-sm mb-0"><?php echo esc_html( get_the_title( $rid ) ); ?></p>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

				</aside>

			</div>
		</div>
		<?php endwhile; ?>
	</main>
</div>

<?php get_footer();

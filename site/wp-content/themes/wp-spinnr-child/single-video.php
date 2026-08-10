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
 * the footer login/register overlay covers the entire page. The Vimeo embed URL
 * and all authenticated content (description, related videos, ad) are only
 * rendered for logged-in users. Registration is AHPRA-gated so logged-in == HCP.
 * A campaign ungate window (see inc/ungate.php) drops the gate for everyone
 * while it runs, which is what makes an eDM series publicly watchable.
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<?php while ( have_posts() ) : the_post();
			$post_id = get_the_ID();
			$gated   = ! is_user_logged_in() && ! hcp_ungate_is_open( $post_id );

			// Episode numbering is derived from the series, so titles stay clean in
			// the database and re-ordering a series never means re-titling it.
			$episode_title = function_exists( 'hcp_videos_episode_title' )
				? hcp_videos_episode_title( $post_id )
				: get_the_title();

			if ( ! $gated ) {
				$vimeo_id = function_exists( 'hcp_videos_vimeo_id' ) ? hcp_videos_vimeo_id( get_field( 'vimeo' ) ) : '';
				$player   = function_exists( 'hcp_videos_player_url' ) ? hcp_videos_player_url( $vimeo_id ) : '';
				$audience = function_exists( 'hcp_videos_audience_label' ) ? hcp_videos_audience_label( $post_id ) : '';
				$duration = function_exists( 'hcp_videos_duration' ) ? hcp_videos_duration( $post_id ) : '';
				$related  = function_exists( 'hcp_videos_related_ids' ) ? hcp_videos_related_ids( $post_id, 3 ) : array();
				if ( $related ) {
					_prime_post_caches( $related, true, true );
				}
				$resources = function_exists( 'get_field' ) ? array_filter( (array) get_field( 'video_resources', $post_id ) ) : array();
			} else {
				$poster = function_exists( 'hcp_videos_thumb_url' ) ? hcp_videos_thumb_url( $post_id, 'large' ) : '';
			}
		?>
		<div class="section <?php echo $gated ? 'logged_in_users_only' : ''; ?>" style="padding-bottom:12rem;">
			<div class="container <?php echo $gated ? '' : 'grid lg:grid-cols-12 gap-4xl'; ?>">

				<?php if ( $gated ) : ?>
					<div class="column">
						<div class="relative w-full rounded-lg overflow-hidden bg-black" style="aspect-ratio:16/9;max-width:860px;margin:0 auto;">
							<?php if ( ! empty( $poster ) ) : ?>
								<img src="<?php echo esc_url( $poster ); ?>" class="absolute inset-0 w-full h-full object-cover" alt="<?php echo esc_attr( get_the_title() ); ?>" />
							<?php endif; ?>
						</div>
						<h1 class="text-2xl mt-base" style="max-width:860px;margin-left:auto;margin-right:auto;"><?php echo esc_html( $episode_title ); ?></h1>
					</div>

				<?php else : ?>
					<!-- MAIN COLUMN -->
					<div class="column lg:col-span-8">

						<div class="relative w-full rounded-lg overflow-hidden bg-black" style="aspect-ratio:16/9;">
							<?php if ( ! empty( $player ) ) : ?>
								<iframe src="<?php echo esc_url( $player ); ?>"
									class="absolute inset-0 w-full h-full"
									frameborder="0"
									allow="autoplay; fullscreen; picture-in-picture"
									allowfullscreen
									title="<?php echo esc_attr( get_the_title() ); ?>"></iframe>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $audience ) || ! empty( $duration ) ) : ?>
							<p class="text-sm font-semibold mt-base mb-0">
								<?php if ( ! empty( $audience ) ) : ?><span class="text-accent"><?php echo esc_html( $audience ); ?></span><?php endif; ?>
								<?php if ( ! empty( $audience ) && ! empty( $duration ) ) : ?><span class="text-paragraph"> &middot; </span><?php endif; ?>
								<?php if ( ! empty( $duration ) ) : ?><span class="text-paragraph"><?php echo esc_html( $duration ); ?></span><?php endif; ?>
							</p>
						<?php endif; ?>

						<h1 class="text-2xl mt-1"><?php echo esc_html( $episode_title ); ?></h1>

						<div class="content-block">
							<div class="hcp-video-desc hcp-clamp hcp-collapsed">
								<?php
								// "Video N of M:" opens the description and links back to the
								// series, so an episode reached directly from an eDM or a
								// search result still shows where it sits in the run.
								$description = apply_filters( 'the_content', get_the_content() );
								echo function_exists( 'hcp_videos_prepend_episode_intro' )
									? hcp_videos_prepend_episode_intro( $description, $post_id )
									: $description;
								?>
							</div>
							<button type="button" class="hcp-desc-toggle">Read more</button>
						</div>
						<script>
						document.addEventListener('DOMContentLoaded', function(){
							var el = document.querySelector('.hcp-clamp');
							var btn = document.querySelector('.hcp-desc-toggle');
							if (!el || !btn) return;
							if (el.scrollHeight <= el.clientHeight + 4) { el.classList.remove('hcp-collapsed'); btn.style.display = 'none'; return; }
							btn.addEventListener('click', function(){
								el.classList.toggle('hcp-collapsed');
								btn.textContent = el.classList.contains('hcp-collapsed') ? 'Read more' : 'Read less';
							});
						});
						</script>
					</div>

					<!-- SIDEBAR (stacks under main on mobile) -->
					<aside class="column lg:col-span-4">

						<?php
						$ad_image = get_field( 'ad_image' );
						$ad_link  = get_field( 'ad_link' );
						?>
						<?php if ( $ad_image && ! empty( $ad_image['url'] ) ) : ?>
						<div class="mb-2xl hidden lg:flex lg:justify-start justify-center">
							<?php if ( $ad_link ) : ?><a href="<?php echo esc_url( $ad_link ); ?>" target="_blank" rel="noopener nofollow"><?php endif; ?>
								<img src="<?php echo esc_url( $ad_image['url'] ); ?>"
									width="300" height="250"
									style="width:300px;height:250px;object-fit:cover;max-width:100%;border-radius:.5rem;"
									alt="<?php echo esc_attr( $ad_image['alt'] ?? '' ); ?>" />
							<?php if ( $ad_link ) : ?></a><?php endif; ?>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $related ) ) : ?>
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
												<img src="<?php echo esc_url( $rthumb ); ?>" class="h-full object-contain mx-auto" alt="" />
											<?php endif; ?>
											<?php if ( function_exists( 'hcp_videos_duration_badge' ) ) : ?>
												<?php echo hcp_videos_duration_badge( $rid ); ?>
											<?php endif; ?>
										</div>
										<div>
											<?php if ( $raud ) : ?><p class="text-xs text-black mb-0"><?php echo esc_html( $raud ); ?></p><?php endif; ?>
											<p class="font-semibold text-paragraph text-sm mb-0"><?php
												echo esc_html( function_exists( 'hcp_videos_episode_title' ) ? hcp_videos_episode_title( $rid ) : get_the_title( $rid ) );
											?></p>
										</div>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

					</aside>

					<?php if ( ! empty( $resources ) ) : ?>
					<div class="column lg:col-span-12 mt-lg">
						<h5 class="mb-base">Related Resources</h5>
						<div class="grid lg:grid-cols-3 md:grid-cols-2 gap-base">
							<?php foreach ( $resources as $rid ) { echo hcp_videos_resource_card( (int) $rid ); } ?>
						</div>
					</div>
					<?php endif; ?>

					<?php // Mobile only: ad moves to the very bottom, after related videos + resources. ?>
					<?php if ( $ad_image && ! empty( $ad_image['url'] ) ) : ?>
					<div class="column lg:col-span-12 lg:hidden mt-2xl flex justify-center">
						<?php if ( $ad_link ) : ?><a href="<?php echo esc_url( $ad_link ); ?>" target="_blank" rel="noopener nofollow"><?php endif; ?>
							<img src="<?php echo esc_url( $ad_image['url'] ); ?>"
								width="300" height="250"
								style="width:300px;height:250px;object-fit:cover;max-width:100%;border-radius:.5rem;"
								alt="<?php echo esc_attr( $ad_image['alt'] ?? '' ); ?>" />
						<?php if ( $ad_link ) : ?></a><?php endif; ?>
					</div>
					<?php endif; ?>
				<?php endif; ?>

			</div>
		</div>
		<?php endwhile; ?>
	</main>
</div>

<?php get_footer();

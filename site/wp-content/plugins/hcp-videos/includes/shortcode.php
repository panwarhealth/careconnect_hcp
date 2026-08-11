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
 *   layout="grid"         — optional, "grid" (default) or "carousel" (Owl)
 *   columns="3"           — optional, grid columns: 1, 2, or 3 (default 3)
 *   limit="-1"            — optional, max videos (default all)
 *   new_tab="1"           — optional, open video pages in a new tab (default on,
 *                           so a listing page is never navigated away from)
 *
 * Gated cards: a logged-out visitor (outside any ungate window) sees the card in
 * full — thumbnail, episode number and title — with the "Watch Video" link
 * replaced by Login/Register buttons. The card itself does not navigate, so
 * nobody is sent to a page that will only gate them again. The series is a
 * marketing surface: the titles are the pitch, and hiding them behind a blur
 * removed the reason to sign up.
 */

defined( 'ABSPATH' ) || exit;

function hcp_videos_grid_shortcode( $atts ): string {
	$atts = shortcode_atts( array(
		'audience'      => '',
		'topic'         => '',
		'exclude_topic' => '',
		'series_total'  => 0,
		'layout'        => 'grid',
		'columns'       => 3,
		'limit'         => -1,
		'new_tab'       => 1,
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
	$is_carousel  = strtolower( (string) $atts['layout'] ) === 'carousel';

	$cols      = max( 1, (int) $atts['columns'] );
	$grid_cols = $cols === 1 ? '' : ( $cols === 2 ? 'md:grid-cols-2' : 'lg:grid-cols-3 md:grid-cols-2' );

	// Build the cards once; the wrapper (grid vs carousel) is chosen below.
	$target = (int) $atts['new_tab'] ? ' target="_blank" rel="noopener"' : '';
	$cards  = array();
	$n      = 0;
	while ( $q->have_posts() ) {
		$q->the_post();
		$n++;
		$id      = get_the_ID();
		$thumb   = hcp_videos_thumb_url( $id, 'medium' );
		$eyebrow = $is_series ? 'Episode ' . $n : hcp_videos_audience_label( $id );
		$gated   = hcp_videos_is_gated( $id );

		ob_start();
		?>
		<?php if ( $gated ) : ?>
			<div class="hcp-video-card-gated">
		<?php else : ?>
			<a class="no-underline" href="<?php echo esc_url( get_permalink( $id ) ); ?>"<?php echo $target; ?>>
		<?php endif; ?>
			<div class="card h-full overflow-hidden">
				<div class="bg-secondary p-md h-48 rounded-t relative">
					<?php echo hcp_videos_play_icon(); ?>
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
					<?php if ( $gated ) : ?>
						<?php echo hcp_videos_gate_cta( 'links', get_permalink( $id ) ); ?>
					<?php else : ?>
						<span class="underline text-accent font-semibold">Watch Video</span>
					<?php endif; ?>
				</div>
			</div>
		<?php echo $gated ? '</div>' : '</a>'; ?>
		<?php
		$cards[] = ob_get_clean();
	}
	wp_reset_postdata();

	$soon = ( $is_series && $n < $series_total )
		? '<p class="hcp-soon-note">Stay tuned &mdash; Episode ' . (int) ( $n + 1 ) . ' of ' . (int) $series_total . ' coming soon.</p>'
		: '';

	if ( $is_carousel ) {
		return hcp_videos_carousel_wrap( $cards ) . $soon;
	}

	$grid_cls = trim( $grid_cols . ( $cols === 1 ? ' hcp-grid-single' : '' ) );
	return '<div id="postgrid" class="grid ' . esc_attr( $grid_cls ) . ' gap-base">' . implode( '', $cards ) . '</div>' . $soon;
}

/**
 * Whether a video must be withheld from the current visitor: logged out, and no
 * campaign ungate window open for it. Mirrors single-video.php so a card and the
 * page it points at never disagree about whether the visitor may watch.
 */
function hcp_videos_is_gated( int $post_id ): bool {
	if ( is_user_logged_in() ) {
		return false;
	}

	return ! ( function_exists( 'hcp_ungate_is_open' ) && hcp_ungate_is_open( $post_id ) );
}

/**
 * The call to action on a gated card or hero. The theme's login/register
 * modals open in place, so the visitor keeps their spot on the listing
 * instead of bouncing through a login page and back.
 *
 * Two styles: 'buttons' for a hero, where the CTA is the section's one
 * primary action, and 'links' for cards — a row of buttons on every episode
 * reads as clutter (client feedback), so cards get text links styled like
 * their usual "Watch Video" line. The links carry real login/register hrefs
 * as the no-JS fallback; the modal handler intercepts them.
 */
function hcp_videos_gate_cta( string $style = 'buttons', string $target = '' ): string {
	hcp_videos_gate_scripts();

	// Deep-link: the modal JS copies this into the login form's redirect_to and
	// the registration form's hcp_reg_target, so the visitor lands on the video
	// or resource they clicked rather than back on the listing.
	$data = $target ? ' data-hcp-target="' . esc_url( $target ) . '"' : '';

	if ( 'links' === $style ) {
		return '<span class="hcp-gate-cta hcp-gate-cta--links">'
			. '<a class="underline text-accent font-semibold" href="' . esc_url( home_url( '/login' ) ) . '" data-hcp-login' . $data . '>Login</a>'
			. '<span class="hcp-gate-or">or</span>'
			. '<a class="underline text-accent font-semibold" href="' . esc_url( home_url( '/register' ) ) . '" data-hcp-register' . $data . '>Register</a>'
			. '<span class="hcp-gate-tail">to view</span>'
			. '</span>';
	}

	return '<span class="hcp-gate-cta">'
		. '<button type="button" class="btn cta m-0" data-hcp-login' . $data . '>Login</button>'
		. '<span class="hcp-gate-or">or</span>'
		. '<button type="button" class="btn cta m-0" data-hcp-register' . $data . '>Register</button>'
		. '<span class="hcp-gate-tail">to view</span>'
		. '</span>';
}

/**
 * Open the theme's login/register modal from a gated card.
 *
 * The theme binds its own handlers per `.login-overlay` as it builds them, so
 * buttons rendered outside that loop get nothing. This delegates from the
 * document instead, which also survives the carousel cloning its slides.
 * Falls back to the login page if the modal markup is absent.
 */
function hcp_videos_gate_scripts(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	echo '<style id="hcp-video-gate-css">
		.hcp-gate-cta{display:flex;align-items:center;flex-wrap:wrap;gap:.5rem;font-weight:600;}
		.hcp-gate-cta .btn{padding:.35rem 1rem;font-size:.85rem;}
		.hcp-gate-or,.hcp-gate-tail{color:#485055;font-weight:400;font-size:.85rem;}
		.hcp-gate-cta--links{gap:.3rem;align-items:baseline;}
		.hcp-gate-cta--links .hcp-gate-or,.hcp-gate-cta--links .hcp-gate-tail{font-size:inherit;}
		.hcp-video-card-gated .card{cursor:default;}
	</style>';

	echo '<script id="hcp-video-gate-js">
	(function($){
		if(!window.jQuery) return;
		$(document).on("click","[data-hcp-login],[data-hcp-register]",function(e){
			e.preventDefault();
			var wantsLogin = this.hasAttribute("data-hcp-login");
			var modal = $("#popup-overlay");
			var login = $("#login-popup-content");
			var register = $("#register-popup-content");
			if(!modal.length || !login.length || !register.length){
				window.location.href = wantsLogin ? "/login" : "/register";
				return;
			}
			var target = this.getAttribute("data-hcp-target") || "";
			window.hcpGateTarget = target || null;
			if(target){
				login.find("input[name=redirect_to]").val(target);
				var regForm = register.find("form");
				if(regForm.length){
					var field = regForm.find("input[name=hcp_reg_target]");
					if(!field.length){ field = $("<input>",{type:"hidden",name:"hcp_reg_target"}).appendTo(regForm); }
					field.val(target);
				}
			}
			login.toggleClass("hidden", !wantsLogin);
			register.toggleClass("hidden", wantsLogin);
			modal.removeClass("hidden").hide().fadeIn(400);
		});
		// The theme popup never runs Formidable\'s post-registration redirect,
		// so follow it here: the server has already applied the deep-link
		// filter to response.redirect. Fall back to a reload, which at least
		// swaps the page to its logged-in state.
		$(document).on("frmFormComplete", function(ev, form, response){
			if(!form || !$(form).is("#form_registration")) return;
			var t = (response && response.redirect) || window.hcpGateTarget || "";
			if(t){ window.location.href = t; } else { window.location.reload(); }
		});
	})(jQuery);
	</script>';
}

/**
 * Wrap cards in an Owl Carousel (the theme enqueues Owl 2.3.4 site-wide via
 * extend_spinnr.php). Each card becomes a slide; init is inlined per the site's
 * existing carousel pattern. Unique id per call so multiple grids on a page
 * don't collide.
 *
 * @param string[] $cards One rendered card per slide.
 */
function hcp_videos_carousel_wrap( array $cards ): string {
	static $seq = 0;
	$seq++;
	$uid = 'hcp-video-carousel-' . $seq;

	// Owl treats each direct child as a slide.
	$slides = '';
	foreach ( $cards as $card ) {
		$slides .= '<div class="item">' . $card . '</div>';
	}

	$opts = wp_json_encode( array(
		'margin'             => 20,
		'nav'                => true,
		'dots'               => true,
		'loop'               => true,
		'autoplay'           => true,
		'autoplayTimeout'    => 4000,
		'autoplayHoverPause' => true,
		'smartSpeed'         => 1200,
		'navText'            => array( '<span class="hcp-nav-prev">&#8249;</span>', '<span class="hcp-nav-next">&#8250;</span>' ),
		'responsive'         => array(
			0    => array( 'items' => 1 ),
			768  => array( 'items' => 2 ),
			1200 => array( 'items' => 3 ),
		),
	) );

	// Carousel only at >=992px (desktop). Below that the cards fall back to a
	// responsive grid (1-up on phones, 2-up at tablet portrait) — see the CSS in
	// helpers.php. Re-evaluated on resize so it works both ways.
	$init = '<script>(function($){var opts=' . $opts . ';function boot(){var $c=$("#' . $uid . '");if(!$c.length)return;var big=window.matchMedia("(min-width:992px)").matches;if(big){if(!$c.hasClass("owl-loaded")&&$.fn.owlCarousel){$c.owlCarousel(opts);}}else if($c.hasClass("owl-loaded")){$c.trigger("destroy.owl.carousel");}}$(function(){boot();});$(window).on("resize",boot);})(jQuery);</script>';

	return '<div id="' . $uid . '" class="owl-carousel owl-theme hcp-video-carousel">' . $slides . '</div>' . $init;
}
add_shortcode( 'video_grid', 'hcp_videos_grid_shortcode' );

/**
 * [video_series_cta url="/video/..." label="Watch now"] — the hero button on a
 * series landing page. Renders the button normally, and Login/Register buttons
 * to a gated visitor, matching the cards below it.
 *
 * Gating follows the page the shortcode sits on, so a landing page opted into a
 * campaign window via `_ungate_series` opens its hero at the same moment as its
 * episodes.
 */
function hcp_videos_series_cta_shortcode( $atts ): string {
	$atts = shortcode_atts( array(
		'url'     => '',
		'label'   => 'Watch now',
		'new_tab' => 1,
	), $atts, 'video_series_cta' );

	if ( hcp_videos_is_gated( (int) get_the_ID() ) ) {
		return hcp_videos_gate_cta( 'buttons', home_url( (string) $atts['url'] ) );
	}

	if ( '' === trim( (string) $atts['url'] ) ) {
		return '';
	}

	return sprintf(
		'<a class="btn cta ico i-arrow-right" href="%s"%s>%s</a>',
		esc_url( $atts['url'] ),
		(int) $atts['new_tab'] ? ' target="_blank" rel="noopener"' : '',
		esc_html( $atts['label'] )
	);
}
add_shortcode( 'video_series_cta', 'hcp_videos_series_cta_shortcode' );

/**
 * [video_resources ids="1,2,3"] — the same Related Resources row the video pages
 * show, for use on a series landing page. Cards gate like the episode cards do:
 * visible, with Login/Register in place of the download link.
 */
function hcp_videos_resources_shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'ids' => '' ), $atts, 'video_resources' );

	$ids = array_filter( array_map( 'intval', explode( ',', (string) $atts['ids'] ) ) );
	if ( ! $ids ) {
		return '';
	}

	$gated = hcp_videos_is_gated( (int) get_the_ID() );
	$cards = '';
	foreach ( $ids as $id ) {
		$cards .= hcp_videos_resource_card( $id, $gated );
	}

	return $cards === ''
		? ''
		: '<div class="grid lg:grid-cols-3 md:grid-cols-2 gap-base">' . $cards . '</div>';
}
add_shortcode( 'video_resources', 'hcp_videos_resources_shortcode' );

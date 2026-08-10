<?php
/**
 * Shared helpers used by the shortcode and the single-video template.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extract the numeric Vimeo ID from a stored value that may be a full URL,
 * a player URL, a showcase URL, or a bare ID.
 */
function hcp_videos_vimeo_id( $raw ): string {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) {
		return '';
	}
	if ( ctype_digit( $raw ) ) {
		return $raw;
	}
	// Must be a vimeo.com URL to proceed further.
	if ( ! preg_match( '~vimeo\.com~', $raw ) ) {
		return '';
	}
	// Prefer an explicit /video/ID segment (showcase, album, player URLs).
	if ( preg_match( '~(?:^|/)video/(\d+)~', $raw, $m ) ) {
		return $m[1];
	}
	// The video ID is the last run of 6+ digits in the URL path (covers plain,
	// channels/name/ID, groups/name/videos/ID, showcase/.../video/ID, etc.).
	if ( preg_match_all( '~/(\d{6,})~', strtok( $raw, '?' ), $matches ) ) {
		return end( $matches[1] );
	}
	return '';
}

/**
 * Build the Vimeo player embed URL for an ID.
 */
function hcp_videos_player_url( string $id ): string {
	return $id ? 'https://player.vimeo.com/video/' . $id : '';
}

/**
 * The play-icon overlay. Centered in its (position:relative) parent via a flex
 * wrapper so the hover-scale doesn't fight the centering transform.
 */
function hcp_videos_play_icon(): string {
	hcp_videos_play_styles();
	return '<span class="hcp-video-play" aria-hidden="true"><svg viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
		<circle cx="25" cy="25" r="25" fill="white" fill-opacity="0.92"/>
		<path d="M37.4331 24.1265C38.1172 24.5079 38.1172 25.4921 37.4331 25.8735L18.7369 36.2955C18.0703 36.6671 17.25 36.1852 17.25 35.422L17.25 14.578C17.25 13.8148 18.0703 13.3329 18.7369 13.7045L37.4331 24.1265Z" fill="#35B1C9"/>
	</svg></span>';
}

/**
 * Inline styles for the play overlay. Output once per request regardless of
 * whether wp_head has fired — hcp_videos_play_icon() calls this directly so
 * the styles are always present in AJAX/block-editor preview contexts too.
 */
function hcp_videos_play_styles(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	echo '<style id="hcp-video-play-css">
		.hcp-video-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:5;}
		.hcp-video-play svg{width:56px;height:56px;filter:drop-shadow(0 2px 6px rgba(0,0,0,.25));transition:transform .18s ease;}
		a:hover .hcp-video-play svg{transform:scale(1.12);}
		.hcp-video-duration{position:absolute;bottom:8px;right:8px;z-index:6;background:rgba(0,0,0,.8);color:#fff;font-size:.72rem;font-weight:600;line-height:1;padding:3px 6px;border-radius:4px;letter-spacing:.02em;}
		.hcp-related-scroll .hcp-video-play svg{width:34px;height:34px;}
		.hcp-related-scroll .hcp-video-duration{bottom:4px;right:4px;font-size:.62rem;padding:2px 4px;}
		.hcp-video-desc, .hcp-video-desc p{letter-spacing:.2px;font-size:.875rem;line-height:1.6;}
		.hcp-video-desc .speaker-label{font-weight:700;}
		.hcp-clamp.hcp-collapsed{max-height:6.4em;overflow:hidden;position:relative;}
		.hcp-clamp.hcp-collapsed::after{content:"";position:absolute;left:0;right:0;bottom:0;height:2em;background:linear-gradient(rgba(255,255,255,0),#fff);pointer-events:none;}
		.hcp-desc-toggle{display:inline-block;background:none;border:0;padding:0;margin-top:.5rem;color:#35B1C9;font-weight:600;font-size:.875rem;cursor:pointer;text-decoration:underline;}
		.hcp-soon-note{margin-top:1rem;font-size:.9rem;color:#8a94a0;}
		.hcp-video-carousel{position:relative;}
		.hcp-video-carousel.owl-loaded{margin-top:1.5rem;}
		.hcp-video-carousel .owl-nav{margin:0;}
		.hcp-video-carousel .owl-nav button.owl-prev,
		.hcp-video-carousel .owl-nav button.owl-next{position:absolute!important;top:calc(50% - 1rem);transform:translateY(-50%);width:44px;height:44px;margin:0!important;border-radius:9999px!important;background:rgba(255,255,255,.95)!important;box-shadow:0 2px 10px rgba(0,0,0,.18);display:flex;align-items:center;justify-content:center;color:#35B1C9!important;z-index:10;transition:background .15s ease;}
		.hcp-video-carousel .owl-nav button.owl-prev{left:-6px;}
		.hcp-video-carousel .owl-nav button.owl-next{right:-6px;}
		.hcp-video-carousel .owl-nav button.owl-prev:hover,
		.hcp-video-carousel .owl-nav button.owl-next:hover{background:#fff!important;}
		.hcp-video-carousel .owl-nav button.owl-prev.disabled,
		.hcp-video-carousel .owl-nav button.owl-next.disabled{opacity:0;pointer-events:none;}
		.hcp-video-carousel .hcp-nav-prev,.hcp-video-carousel .hcp-nav-next{font-size:1.7rem;line-height:1;background:none!important;padding:0!important;margin:0!important;}
		.hcp-video-carousel .owl-dots{text-align:center;margin-top:1rem;}
		@media(max-width:991px){.hcp-video-carousel:not(.owl-loaded){display:grid!important;grid-template-columns:1fr;gap:2rem 1.25rem;padding-top:1.75rem;}.hcp-video-carousel:not(.owl-loaded) .item{max-width:360px;width:100%;margin:0 auto;}.hcp-grid-single{justify-items:center;}.hcp-grid-single>a{width:100%;min-width:260px;max-width:360px;}}
		@media(min-width:600px) and (max-width:991px){.hcp-video-carousel:not(.owl-loaded){grid-template-columns:1fr 1fr;}}
		@media(max-width:899px){.hcp-cb-hero .grid.items-center{grid-template-columns:1fr!important;}.hcp-cb-hero .grid.items-center>.column{grid-column:auto!important;}}
		@media(min-width:1024px){.hcp-related-scroll{max-height:640px;overflow-y:auto;padding-right:10px;}}
		[id^="clinicalbites"],.hcp-anchor-target{scroll-margin-top:110px;}
	</style>';

	hcp_videos_anchor_script();
}

/**
 * Re-apply a #hash jump once the page has settled.
 *
 * The browser scrolls to an anchor as soon as it parses the target, but the
 * carousel initialises after that and changes height, so anything below it has
 * moved by the time the page is stable — a link to a section under the carousel
 * lands short. Re-running the jump on load, and once more after the carousel has
 * had a frame to lay out, puts it where the reader expects.
 *
 * Abandoned the moment the reader scrolls themselves: yanking the page out from
 * under someone who has started reading is worse than landing in the wrong spot.
 */
function hcp_videos_anchor_script(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	echo '<script id="hcp-video-anchor-js">
	(function(){
		if(!window.location.hash) return;
		var target;
		try { target = document.querySelector(window.location.hash); } catch(e) { return; }
		if(!target) return;
		var cancelled = false;
		function cancel(){ cancelled = true; }
		window.addEventListener("wheel", cancel, {passive:true, once:true});
		window.addEventListener("touchmove", cancel, {passive:true, once:true});
		window.addEventListener("keydown", cancel, {once:true});
		// The target is the last section before the footer, so on a tall window
		// there is not enough page beneath it to bring it to the top — the scroll
		// clamps early and leaves the section stranded mid-page. Extend the page
		// by the shortfall so it can reach.
		function runway(){
			var margin = parseInt(getComputedStyle(target).scrollMarginTop, 10) || 0;
			var needed = target.getBoundingClientRect().top + window.scrollY - margin;
			var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
			var shortfall = Math.ceil(needed - maxScroll);
			if(shortfall > 0){
				var spacer = document.getElementById("hcp-anchor-runway") || (function(){
					var el = document.createElement("div");
					el.id = "hcp-anchor-runway";
					el.setAttribute("aria-hidden", "true");
					document.body.appendChild(el);
					return el;
				})();
				spacer.style.height = shortfall + "px";
			}
		}
		function settle(){
			if(cancelled) return;
			runway();
			target.scrollIntoView();
		}
		// Not gated on window.load: with slow assets (Vimeo thumbnails, ads) the
		// load event can arrive many seconds late or after a promo modal has
		// locked body scrolling, by which point the jump is impossible. Retry on
		// a schedule instead, ending once the layout has stopped moving the
		// target — each retry is a no-op scroll when it is already in place.
		var attempts = [0, 300, 700, 1200, 1800, 2600];
		attempts.forEach(function(ms){ setTimeout(settle, ms); });
	})();
	</script>';
}

/**
 * Format a number of seconds as m:ss (or h:mm:ss).
 */
function hcp_videos_format_duration( int $secs ): string {
	if ( $secs <= 0 ) {
		return '';
	}
	$h = intdiv( $secs, 3600 );
	$m = intdiv( $secs % 3600, 60 );
	$s = $secs % 60;
	return $h > 0
		? sprintf( '%d:%02d:%02d', $h, $m, $s )
		: sprintf( '%d:%02d', $m, $s );
}

/**
 * Display duration for a video: the manual ACF field if set, else the
 * auto-fetched value cached in _hcpvid_duration postmeta. Never fetches live.
 */
function hcp_videos_duration( int $post_id ): string {
	$manual = trim( (string) get_field( 'duration', $post_id ) );
	if ( $manual !== '' ) {
		return $manual;
	}
	return (string) get_post_meta( $post_id, '_hcpvid_duration', true );
}

/**
 * Fetch duration + poster thumbnail from Vimeo oEmbed and cache both in
 * postmeta. Works for embed-only videos where vumbnail.com cannot reach a
 * frame. 3-second timeout; surfaces an admin notice on failure.
 */
function hcp_videos_cache_vimeo_meta( int $post_id ): string {
	$id = hcp_videos_vimeo_id( get_field( 'vimeo', $post_id ) );
	if ( ! $id ) {
		return '';
	}
	$resp = wp_remote_get(
		'https://vimeo.com/api/oembed.json?width=640&url=https://vimeo.com/' . $id,
		// Referer = the whitelisted prod domain so embed-restricted videos still
		// return their poster regardless of which environment makes the call.
		array( 'timeout' => 5, 'headers' => array( 'Referer' => 'https://hcp.carepharma.com.au' ) )
	);
	if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>HCP Videos:</strong> Could not auto-fetch details from Vimeo (video may be private or the server blocked the request). Enter the duration manually and set a featured image.</p></div>';
		} );
		return '';
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( ! empty( $data['thumbnail_url'] ) ) {
		update_post_meta( $post_id, '_hcpvid_thumb', esc_url_raw( $data['thumbnail_url'] ) );
	}

	$secs = isset( $data['duration'] ) ? (int) $data['duration'] : 0;
	$formatted = hcp_videos_format_duration( $secs );
	if ( $formatted !== '' ) {
		update_post_meta( $post_id, '_hcpvid_duration', $formatted );
	}
	return $formatted;
}

add_action( 'acf/save_post', function ( $post_id ) {
	if ( get_post_type( $post_id ) === 'video' ) {
		hcp_videos_cache_vimeo_meta( (int) $post_id );
	}
}, 20 );

/**
 * Small duration badge for a thumbnail corner (YouTube-style).
 */
function hcp_videos_duration_badge( int $post_id ): string {
	$d = hcp_videos_duration( $post_id );
	if ( $d === '' ) {
		return '';
	}
	return '<span class="hcp-video-duration">' . esc_html( $d ) . '</span>';
}

/**
 * Thumbnail URL for a video: the featured image if set, else the Vimeo
 * thumbnail via vumbnail.com (the convention already used on this site).
 */
function hcp_videos_thumb_url( int $post_id, string $size = 'medium' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		if ( $url ) {
			return $url;
		}
	}
	$cached = get_post_meta( $post_id, '_hcpvid_thumb', true );
	if ( $cached ) {
		return $cached;
	}
	$id = hcp_videos_vimeo_id( get_field( 'vimeo', $post_id ) );
	return $id ? 'https://vumbnail.com/' . $id . '.jpg' : '';
}

/**
 * The audience term names for a video, comma-joined (card label).
 */
function hcp_videos_audience_label( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'audience' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	return join( ', ', wp_list_pluck( $terms, 'name' ) );
}

/**
 * Resolve the click target + label for a `resources` post, mirroring the
 * priority used by the site's [resources] shortcode: a downloadable file, then
 * an interactive-tool URL, then a Vimeo link. Returns url '' when none set.
 */
function hcp_videos_resource_link( int $rid ): array {
	// A non-`resources` post (e.g. a blog article) picked as a resource — link
	// straight to the article rather than a download/tool/video.
	if ( get_post_type( $rid ) !== 'resources' ) {
		return array( 'url' => get_permalink( $rid ), 'label' => 'Read Article', 'is_video' => false );
	}

	$download = get_field( 'download', $rid );
	if ( is_array( $download ) && ! empty( $download['url'] ) ) {
		return array( 'url' => $download['url'], 'label' => 'View Resource', 'is_video' => false );
	}

	$tool = trim( (string) get_field( 'tool', $rid ) );
	if ( $tool !== '' ) {
		return array( 'url' => $tool, 'label' => 'Try Now', 'is_video' => false );
	}

	$vimeo = hcp_videos_vimeo_id( get_field( 'vimeo', $rid ) );
	if ( $vimeo !== '' ) {
		return array( 'url' => hcp_videos_player_url( $vimeo ), 'label' => 'Watch Video', 'is_video' => true );
	}

	return array( 'url' => '', 'label' => '', 'is_video' => false );
}

/**
 * Render a single resource as a house-style card (matches [video_grid] and the
 * /resources page). Returns '' if the resource is unpublished or has no target.
 */
function hcp_videos_resource_card( int $rid, bool $gated = false ): string {
	if ( get_post_status( $rid ) !== 'publish' ) {
		return '';
	}
	$link = hcp_videos_resource_link( $rid );
	if ( $link['url'] === '' ) {
		return '';
	}

	$thumb    = get_the_post_thumbnail_url( $rid, 'medium' );
	$audience = hcp_videos_audience_label( $rid );
	$icon     = $link['is_video'] ? hcp_videos_play_icon() : '';

	ob_start();
	?>
	<?php if ( $gated ) : ?>
		<div class="hcp-video-card-gated">
	<?php else : ?>
		<a class="no-underline" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener">
	<?php endif; ?>
		<div class="card h-full overflow-hidden">
			<div class="bg-secondary p-md h-48 rounded-t relative">
				<?php echo $icon; ?>
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" class="h-full object-contain mx-auto" alt="<?php echo esc_attr( get_the_title( $rid ) ); ?>" />
				<?php endif; ?>
			</div>
			<div class="card-body">
				<div>
					<?php if ( $audience ) : ?><p class="text-sm text-black"><?php echo esc_html( $audience ); ?></p><?php endif; ?>
					<h5 class="min-h-14"><?php echo esc_html( get_the_title( $rid ) ); ?></h5>
				</div>
				<?php if ( $gated ) : ?>
					<?php echo hcp_videos_gate_cta(); ?>
				<?php else : ?>
					<span class="underline text-accent font-semibold"><?php echo esc_html( $link['label'] ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	<?php echo $gated ? '</div>' : '</a>'; ?>
	<?php
	return ob_get_clean();
}

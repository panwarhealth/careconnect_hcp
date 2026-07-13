<?php
/**
 * Plugin Name: HCP Therapy-Area Hubs (MOCKUP / blow-away branch)
 * Description: Prototype of the Therapy Area hub navigation, built with the site's own theme
 *              components (card / btn / bg-accent-secondary / accent). Reads live therapy_area
 *              terms + resources. Local-only, throwaway branch.
 * Version:     0.0.2
 *
 * Shortcodes:
 *   [hub_landing]                   — Therapy Areas landing (grid of hubs)
 *   [hub_area slug="nasal-health"]  — a single hub (also reads ?area=slug)
 *   [hub_product]                   — a sample Product page
 */

defined( 'ABSPATH' ) || exit;

const HBM_LANDING = '/therapy-areas/';
const HBM_HUB     = '/therapy-hub/';
const HBM_PRODUCT = '/therapy-product/';
const HBM_PRODUCT_IMG = '/wp-content/uploads/2023/06/FESS-Original-product-screenshot.jpg';

/* per-area copy + initials (presentation only; names come from the live term) */
function hbm_areas() {
	return array(
		'nasal-health'      => array( 'NS', 'FESS leaflets, congestion charts, how-to videos and saline product info.' ),
		'rehydration'       => array( 'R',  'Hydralyte care plans, dosing charts and rehydration explainers.' ),
		'digestive-care'    => array( 'DC', 'Bowel prep guides, patient leaflets and clinical fact sheets.' ),
		'eye-care'          => array( 'EC', 'Dry-eye and allergy management resources, the Allergy Analyser and Zaditen info.' ),
		'rectal-health'     => array( 'RH', 'Consultation resources and the 3D interactive model.' ),
		'womens-health'     => array( 'WH', 'Patient leave-behinds and educational materials.' ),
		'paediatric-health' => array( 'PH', 'Child-friendly leaflets, dosing charts and parent guides.' ),
	);
}

/* classify a resource */
function hbm_type( $pid ) {
	if ( get_field( 'vimeo', $pid ) ) return 'Video';
	if ( get_field( 'tool', $pid ) )  return 'Tool';
	return 'PDF';
}
function hbm_subtype( $pid ) {
	$t = get_the_title( $pid );
	foreach ( array(
		'Care Plan' => 'Care plan', 'Chart' => 'Chart', 'Factsheet' => 'Fact sheet',
		'Fact Sheet' => 'Fact sheet', 'Leaflet' => 'Leaflet', 'Detailer' => 'Detailer',
		'Recommendation' => 'Recommendation', 'Training' => 'Training guide',
		'Dosing' => 'Dosing info', 'Dosage' => 'Dosing info', 'Guide' => 'Guide',
	) as $needle => $label ) {
		if ( stripos( $t, $needle ) !== false ) return $label;
	}
	return 'Resource';
}
function hbm_audience( $pid ) {
	$terms = get_the_terms( $pid, 'audience' );
	$names = $terms && ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
	foreach ( $names as $n ) { if ( stripos( $n, 'patient' ) !== false ) return 'Patient'; }
	return 'Healthcare Professional';
}
function hbm_link( $pid ) {
	if ( $d = get_field( 'download', $pid ) ) return array( is_array( $d ) ? $d['url'] : $d, 'View resource' );
	if ( $v = get_field( 'vimeo', $pid ) )    return array( $v, 'Watch video' );
	if ( $t = get_field( 'tool', $pid ) )     return array( $t, 'Try now' );
	return array( '#', 'View' );
}
function hbm_resources( $slug, $limit = -1 ) {
	return get_posts( array(
		'post_type' => 'resources', 'posts_per_page' => $limit, 'orderby' => 'title', 'order' => 'ASC',
		'tax_query' => array( array( 'taxonomy' => 'therapy_area', 'field' => 'slug', 'terms' => $slug ) ),
	) );
}

/* play icon (same accent SVG the resources grid uses) */
function hbm_play() {
	return '<svg class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2" width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="25" cy="25" r="25" fill="white" fill-opacity="0.7"/><path d="M37.4331 24.1265C38.1172 24.5079 38.1172 25.4921 37.4331 25.8735L18.7369 36.2955C18.0703 36.6671 17.25 36.1852 17.25 35.422L17.25 14.578C17.25 13.8148 18.0703 13.3329 18.7369 13.7045L37.4331 24.1265Z" fill="#35B1C9"/></svg>';
}

/* a resource card — the theme's own card markup (mirrors resources_fn) */
function hbm_card( $pid, $eyebrow = '' ) {
	$type  = hbm_type( $pid );
	$sub   = $type === 'PDF' ? hbm_subtype( $pid ) : $type;
	$aud   = hbm_audience( $pid );
	list( $url, $label ) = hbm_link( $pid );
	$thumb = get_the_post_thumbnail_url( $pid, 'medium' );
	ob_start(); ?>
	<a class="no-underline hbm-item" data-sub="<?php echo esc_attr( $sub ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
		<div class="card h-full overflow-hidden">
			<div class="bg-secondary p-md h-48 rounded-t relative flex items-center justify-center">
				<?php if ( $type === 'Video' ) echo hbm_play(); ?>
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" class="h-full object-contain mx-auto" alt="">
				<?php endif; ?>
			</div>
			<div class="card-body">
				<div>
					<?php if ( $eyebrow ) : ?><p class="text-xs uppercase tracking-wide text-accent font-semibold mb-2"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
					<p class="text-sm"><span class="text-accent font-semibold"><?php echo esc_html( $sub ); ?></span> · <?php echo esc_html( $aud ); ?></p>
					<h5 class="min-h-14"><?php echo esc_html( get_the_title( $pid ) ); ?></h5>
				</div>
				<span class="underline text-accent font-semibold"><?php echo esc_html( $label ); ?> →</span>
			</div>
		</div>
	</a>
	<?php return ob_get_clean();
}

/* small count chip */
function hbm_chip( $text ) {
	return '<span class="bg-secondary rounded-full px-4 py-2 text-sm font-semibold" style="border:1px solid #cfdcde">' . esc_html( $text ) . '</span>';
}

/* full-width section wrappers — alternate white / light-blue (white first),
   using the site's own bg-secondary section colour */
function hbm_sec_open( &$i ) {
	$bg = ( $i % 2 === 1 ) ? ' bg-secondary' : '';
	$i++;
	return '<div class="section' . $bg . '"><div class="container"><div class="column"><div class="content-block">';
}
function hbm_sec_close() {
	return '</div></div></div></div>';
}

/* per-area banner background image (defaults to the shared clinical banner) */
function hbm_banner_img( $slug ) {
	$map = array(
		// 'nasal-health' => '2025/xx/some-nasal-banner.jpg',
	);
	$file = isset( $map[ $slug ] ) ? $map[ $slug ] : '2025/02/Resources.jpg';
	return home_url( '/wp-content/uploads/' . $file );
}

/* ================= [hub_landing] ================= */
function hbm_landing( $atts ) {
	$atts = shortcode_atts( array( 'columns' => '3' ), $atts );
	$two  = ( $atts['columns'] === '2' );
	$grid = $two ? 'grid lg:grid-cols-2 md:grid-cols-2 gap-lg' : 'grid lg:grid-cols-3 md:grid-cols-2 gap-base';
	$imgh = $two ? '260px' : '160px';
	$htag = $two ? 'h4' : 'h5';
	$dcls = $two ? 'mb-base' : 'text-sm mb-base';
	$out  = '<div class="hbm"><div class="' . $grid . '">';
	foreach ( hbm_areas() as $slug => $a ) {
		$term = get_term_by( 'slug', $slug, 'therapy_area' );
		if ( ! $term ) continue;
		$res = hbm_resources( $slug );
		$pdf = count( array_filter( $res, fn( $p ) => hbm_type( $p->ID ) === 'PDF' ) );
		$vid = count( array_filter( $res, fn( $p ) => hbm_type( $p->ID ) === 'Video' ) );
		$tool= count( array_filter( $res, fn( $p ) => hbm_type( $p->ID ) === 'Tool' ) );
		$img = home_url( '/wp-content/uploads/hub-mockup/' . $slug . '.jpg' );
		$out .= '<a class="no-underline" href="' . esc_url( home_url( HBM_HUB . '?area=' . $slug ) ) . '">';
		$out .= '<div class="card h-full overflow-hidden">';
		$out .= '<img src="' . esc_url( $img ) . '" alt="" style="width:100%;height:' . $imgh . ';object-fit:cover;display:block">';
		$out .= '<div class="card-body"><' . $htag . ' class="mb-2">' . esc_html( html_entity_decode( $term->name ) ) . '</' . $htag . '><p class="' . $dcls . '">' . esc_html( $a[1] ) . '</p>';
		$out .= '<div class="flex flex-wrap gap-2 mb-base">';
		$out .= '<span class="bg-secondary rounded-full px-3 py-1 text-xs font-semibold">' . $pdf . ' resources</span>';
		if ( $vid )  $out .= '<span class="bg-secondary rounded-full px-3 py-1 text-xs font-semibold">' . $vid . ' videos</span>';
		if ( $tool ) $out .= '<span class="bg-secondary rounded-full px-3 py-1 text-xs font-semibold">' . $tool . ' tool' . ( $tool > 1 ? 's' : '' ) . '</span>';
		$out .= '</div><span class="text-accent font-semibold">Explore hub →</span></div></div></a>';
	}
	$out .= '</div></div>';
	return $out;
}
add_shortcode( 'hub_landing', 'hbm_landing' );

/* ================= [hub_area] ================= */
function hbm_area( $atts ) {
	$atts  = shortcode_atts( array( 'slug' => '' ), $atts );
	$slug  = isset( $_GET['area'] ) ? sanitize_title( wp_unslash( $_GET['area'] ) ) : $atts['slug'];
	$areas = hbm_areas();
	if ( ! isset( $areas[ $slug ] ) ) $slug = 'nasal-health';
	$term  = get_term_by( 'slug', $slug, 'therapy_area' );
	$name  = $term ? html_entity_decode( $term->name ) : $slug;
	$res   = hbm_resources( $slug );
	$pdfs  = array_filter( $res, fn( $p ) => hbm_type( $p->ID ) === 'PDF' );
	$vids  = array_filter( $res, fn( $p ) => hbm_type( $p->ID ) === 'Video' );
	$tools = array_filter( $res, fn( $p ) => hbm_type( $p->ID ) === 'Tool' );

	/* BANNER — full-width dark, matches Resources page */
	$out  = '<div class="bg-center bg-cover flex items-center section theme-dark md:h-4xl tbst-bg-image" style="background-image:url(\'' . esc_url( hbm_banner_img( $slug ) ) . '\')">';
	$out .= '<div class="container"><div class="column"><div class="content-block max-w-3xl">';
	$out .= '<p class="text-sm mb-base"><a href="' . esc_url( home_url( HBM_LANDING ) ) . '">Therapy Areas</a> / ' . esc_html( $name ) . '</p>';
	$out .= '<h1>' . esc_html( $name ) . '</h1>';
	$out .= '<p>' . esc_html( $areas[ $slug ][1] ) . '</p>';
	$out .= '<div class="mt-lg" style="max-width:520px"><input type="search" class="hbm-search" placeholder="Search this hub…" style="width:100%;padding:12px 16px;border-radius:10px;border:1px solid #cfdcde;background:#fff;color:#0f262e;font-size:15px"></div>';
	$out .= '</div></div></div></div>';

	$i = 0;

	/* SECTION 1 (light blue) — counts + featured */
	$out .= hbm_sec_open( $i );
	$out .= '<div class="flex flex-wrap gap-4 mb-2xl">';
	$out .= hbm_chip( 'Resources · ' . count( $pdfs ) );
	if ( $vids )  $out .= hbm_chip( 'Videos · ' . count( $vids ) );
	if ( $tools ) $out .= hbm_chip( 'Tools · ' . count( $tools ) );
	$out .= hbm_chip( 'Products · 1' );
	$out .= '</div>';
	$out .= '<p class="text-sm uppercase tracking-wide text-accent font-semibold mb-base">Featured in this hub</p>';
	$out .= '<div class="grid lg:grid-cols-3 md:grid-cols-2 gap-base">';
	if ( $vids ) $out .= hbm_card( reset( $vids )->ID, 'Latest video' );
	if ( $pdfs ) $out .= hbm_card( reset( $pdfs )->ID, 'Most downloaded' );
	$out .= hbm_product_card();
	$out .= '</div>';
	$out .= hbm_sec_close();

	/* SECTION 2 (white) — resources + sub-type tabs */
	$subs = array();
	foreach ( $pdfs as $p ) { $subs[ hbm_subtype( $p->ID ) ] = true; }
	$out .= hbm_sec_open( $i );
	$out .= '<div class="flex items-center justify-between mb-base"><h2 class="m-0">Resources <span class="text-sm">' . count( $pdfs ) . ' items</span></h2><a class="text-accent font-semibold" href="#">View all →</a></div>';
	$out .= '<div class="flex flex-wrap gap-2 mb-lg hbm-tabs"><button class="btn cta" data-f="all">All</button>';
	foreach ( array_keys( $subs ) as $s ) { $out .= '<button class="btn ghost" data-f="' . esc_attr( $s ) . '">' . esc_html( $s ) . '</button>'; }
	$out .= '</div><div class="grid lg:grid-cols-3 md:grid-cols-2 gap-base hbm-cards">';
	foreach ( $pdfs as $p ) { $out .= hbm_card( $p->ID ); }
	$out .= '</div>';
	$out .= hbm_sec_close();

	/* SECTION 3 (light blue) — videos */
	if ( $vids ) {
		$out .= hbm_sec_open( $i );
		$out .= '<div class="flex items-center justify-between mb-base"><h2 class="m-0">Videos <span class="text-sm">' . count( $vids ) . ' items</span></h2><a class="text-accent font-semibold" href="#">View all →</a></div>';
		$out .= '<div class="grid lg:grid-cols-3 md:grid-cols-2 gap-base">';
		foreach ( $vids as $p ) { $out .= hbm_card( $p->ID ); }
		$out .= '</div>';
		$out .= hbm_sec_close();
	}

	/* SECTION 4 (white) — tools */
	if ( $tools ) {
		$out .= hbm_sec_open( $i );
		$out .= '<h2 class="mb-base">Tools <span class="text-sm">' . count( $tools ) . ' items</span></h2>';
		$out .= '<div class="grid lg:grid-cols-3 md:grid-cols-2 gap-base">';
		foreach ( $tools as $p ) { $out .= hbm_card( $p->ID ); }
		$out .= '</div>';
		$out .= hbm_sec_close();
	}

	$out .= '<script>document.querySelectorAll(".hbm-tabs").forEach(function(tabs){var sec=tabs.closest(".section");tabs.addEventListener("click",function(e){var b=e.target.closest("button");if(!b)return;e.preventDefault();tabs.querySelectorAll("button").forEach(function(x){x.classList.remove("cta");x.classList.add("ghost");});b.classList.add("cta");b.classList.remove("ghost");var f=b.dataset.f;sec.querySelectorAll(".hbm-cards .hbm-item").forEach(function(c){c.style.display=(f==="all"||c.dataset.sub===f)?"":"none";});});});</script>';
	$out .= '<script>document.querySelectorAll(".hbm-search").forEach(function(inp){inp.addEventListener("input",function(){var q=inp.value.trim().toLowerCase();document.querySelectorAll(".hbm-item").forEach(function(c){var h=c.querySelector("h5");var t=h?h.textContent.toLowerCase():"";c.style.display=(!q||t.indexOf(q)>-1)?"":"none";});});});</script>';
	return $out;
}
add_shortcode( 'hub_area', 'hbm_area' );

/* a "product" card for the featured row */
function hbm_product_card() {
	ob_start(); ?>
	<a class="no-underline" href="<?php echo esc_url( home_url( HBM_PRODUCT ) ); ?>">
		<div class="card h-full overflow-hidden">
			<div class="bg-secondary p-md h-48 rounded-t flex items-center justify-center">
				<img src="<?php echo esc_url( home_url( HBM_PRODUCT_IMG ) ); ?>" class="h-full object-contain mx-auto" alt="Saline Nasal Spray">
			</div>
			<div class="card-body">
				<div>
					<p class="text-xs uppercase tracking-wide text-accent font-semibold mb-2">New product</p>
					<p class="text-sm"><span class="text-accent font-semibold">Product</span> · Patient</p>
					<h5 class="min-h-14">Saline Nasal Spray</h5>
				</div>
				<span class="underline text-accent font-semibold">View →</span>
			</div>
		</div>
	</a>
	<?php return ob_get_clean();
}

/* ================= [hub_product] ================= */
function hbm_product() {
	$related = hbm_resources( 'nasal-health', 4 );

	/* BANNER (dark image bg) — product hero with info + pack shot */
	$out  = '<div class="bg-center bg-cover section theme-dark tbst-bg-image" style="background-image:url(\'' . esc_url( hbm_banner_img( 'nasal-health' ) ) . '\')">';
	$out .= '<div class="container"><div class="column"><div class="content-block">';
	$out .= '<div class="grid lg:grid-cols-3 gap-base items-center">';
	$out .= '<div class="lg:col-span-2">';
	$out .= '<p class="text-sm mb-base"><a href="' . esc_url( home_url( HBM_LANDING ) ) . '">Therapy Areas</a> / <a href="' . esc_url( home_url( HBM_HUB . '?area=nasal-health' ) ) . '">Nasal &amp; Sinus Health</a> / Saline Nasal Spray</p>';
	$out .= '<h1 class="mb-2">Saline Nasal Spray</h1><p class="font-semibold mb-base">FESS · Nasal &amp; Sinus Health</p>';
	$out .= '<p class="text-lg mb-lg">Non-medicated isotonic saline to thin mucus and clear congestion — a first-line recommendation for colds, allergy and post-operative care, suitable for all ages.</p>';
	$out .= '<div class="flex flex-wrap gap-4"><a class="btn cta" href="#">Order samples</a><a class="btn ghost" href="#">Visit product site ↗</a></div>';
	$out .= '</div>';
	$out .= '<div class="lg:col-span-1"><div class="bg-primary rounded-lg flex items-center justify-center p-lg" style="min-height:260px"><img src="' . esc_url( home_url( HBM_PRODUCT_IMG ) ) . '" class="object-contain" style="max-height:230px;max-width:100%" alt="Saline Nasal Spray"></div></div>';
	$out .= '</div>';
	$out .= '</div></div></div></div>';

	/* SECTION (white) — detail + specs */
	$i = 0;
	$out .= hbm_sec_open( $i );
	$out .= '<div class="grid lg:grid-cols-3 gap-base">';
	$out .= '<div class="lg:col-span-2">';
	$out .= '<h2 class="mb-base">About this product</h2><p class="mb-lg">FESS Saline Nasal Spray thins and loosens mucus to relieve nasal and sinus congestion without medication. Because it is non-medicated, it can be recommended alongside decongestants, antihistamines and intranasal corticosteroids, and used as often as needed.</p>';
	$out .= '<h2 class="mb-base">When to recommend</h2><div class="grid md:grid-cols-2 gap-base mb-2xl">';
	foreach ( array(
		array( 'Colds &amp; flu', 'First-line congestion relief before medicated options.' ),
		array( 'Allergic rhinitis', 'Clears allergens; improves INCS deposition when used first.' ),
		array( 'Pregnancy &amp; infants', 'Non-medicated option where medicines are limited.' ),
		array( 'Post-operative care', 'Keeps passages moist and clear after sinus surgery.' ),
	) as $w ) {
		$out .= '<div class="bg-secondary rounded-lg p-md"><p class="font-semibold mb-2">' . $w[0] . '</p><p class="text-sm m-0">' . $w[1] . '</p></div>';
	}
	$out .= '</div>';
	$out .= '<h2 class="mb-base">Related resources</h2><div class="grid md:grid-cols-2 gap-base">';
	foreach ( $related as $p ) { $out .= hbm_card( $p->ID ); }
	$out .= '</div></div>';
	$out .= '<div class="lg:col-span-1"><div class="card"><div class="card-body">';
	$out .= '<p class="text-sm uppercase tracking-wide text-accent font-semibold mb-base">Key specs</p>';
	foreach ( array(
		array( 'Format', 'Metered nasal spray' ), array( 'Pack size', '30 mL · ~200 sprays' ),
		array( 'Active', 'NaCl 9 mg/mL isotonic' ), array( 'Suitable for', 'All ages incl. infants' ),
		array( 'Schedule', 'General sale' ),
	) as $s ) {
		$out .= '<div class="flex justify-between py-2" style="border-bottom:1px solid rgba(207,220,222,1)"><span class="text-sm">' . $s[0] . '</span><span class="text-sm font-semibold">' . $s[1] . '</span></div>';
	}
	$out .= '<a class="btn cta mt-lg" style="width:100%" href="#">Order samples</a><a class="btn ghost mt-base" style="width:100%" href="#">Download product info PDF</a>';
	$out .= '</div></div></div>';
	$out .= hbm_sec_close();
	return $out;
}
add_shortcode( 'hub_product', 'hbm_product' );

/**
 * Idempotent installer — recreates the mockup's DB state (pages + menu 258 tweaks)
 * so the throwaway branch is restorable after a DB re-seed or fresh clone.
 * Runs on plugin (re)activation. Safe to re-run.
 */
function hbm_install() {
	$base   = home_url( '' );
	$banner = '<div class="bg-center bg-cover flex items-center section theme-dark md:h-4xl tbst-bg-image" style="background-image: url(\'' . $base . '/wp-content/uploads/2025/02/Resources.jpg\');"> <div class="container"> <div class="column"> <div class="content-block max-w-3xl"> <h1>Therapy Areas</h1> <p>Everything for each therapy area in one place — resources, videos, tools, articles and product information. Choose an area to explore its hub.</p> </div> </div> </div> </div>' . "\n";

	$pages = array(
		'therapy-areas'    => array( 'Mockup Therapy Areas', $banner . '<div class="section"> <div class="container"> <div class="column"> <div class="content-block"> [hub_landing] </div> </div> </div> </div>' ),
		'therapy-areas-v2' => array( 'Therapy Areas v2',     $banner . '<div class="section"> <div class="container"> <div class="column"> <div class="content-block"> [hub_landing columns="2"] </div> </div> </div> </div>' ),
		'therapy-hub'      => array( 'Mockup Hub',           '[hub_area slug="nasal-health"]' ),
		'therapy-product'  => array( 'Mockup Product',       '[hub_product]' ),
	);
	foreach ( $pages as $slug => $p ) {
		if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
			continue;
		}
		wp_insert_post( array(
			'post_title'   => $p[0],
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => $p[1],
			'post_author'  => 1,
		) );
	}

	// Menu 258: add "Therapy Areas", remove Resources + Tools & Videos (both if-menu copies).
	if ( is_nav_menu( 258 ) ) {
		$raw = get_posts( array(
			'post_type'      => 'nav_menu_item',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'tax_query'      => array( array( 'taxonomy' => 'nav_menu', 'field' => 'term_id', 'terms' => 258 ) ),
		) );
		$have_therapy = false;
		foreach ( $raw as $item ) {
			$title = strtolower( trim( $item->post_title ) );
			$url   = (string) get_post_meta( $item->ID, '_menu_item_url', true );
			if ( in_array( $title, array( 'resources', 'tools & videos', 'tools and videos' ), true )
				|| strpos( $url, '/resources' ) !== false
				|| strpos( $url, '/tools-and-videos' ) !== false ) {
				wp_delete_post( $item->ID, true );
				continue;
			}
			if ( strpos( $url, '/therapy-areas' ) !== false ) {
				$have_therapy = true;
			}
		}
		if ( ! $have_therapy ) {
			wp_update_nav_menu_item( 258, 0, array(
				'menu-item-title'    => 'Therapy Areas',
				'menu-item-url'      => trailingslashit( $base ) . 'therapy-areas/',
				'menu-item-status'   => 'publish',
				'menu-item-type'     => 'custom',
				'menu-item-position' => 1,
			) );
		}
	}
}
register_activation_hook( __FILE__, 'hbm_install' );

<?php
/**
 * Create CAPH0125 "Guess the Glucose Challenge" interactive quiz article.
 *
 * A mythbusting quiz: over 8 rounds the reader compares Hydralyte Powder (2.9 g glucose / 200 mL)
 * against an everyday food/drink and picks the LOWER-sugar item — Hydralyte is always lower,
 * reinforcing that WHO-aligned ORS like Hydralyte are suitable for people with diabetes.
 *
 * Self-contained: the interactive quiz (markup + scoped CSS + JS) is inline in post_content,
 * following the CAPH0111 article pattern (a `post`, no plugin/shortcode).
 *
 * Becomes the /blog/ featured card automatically by being the newest post: it publishes on
 * migration-run (post_date = now), so it is the newest `post` and fills the featured slot while
 * CAPH0111 drops into the grid. To hold it until the 13/08 go-live on prod instead, set post_date
 * to a future '2026-08-13 00:00:00' (WordPress will store it as a scheduled 'future' post that
 * auto-publishes — and auto-features — on that date).
 *
 * Files required at wp-content/uploads/ on each environment BEFORE running:
 *   - 2026/07/ggq-hero.png  (header banner + /blog/ featured-card thumbnail: title + food/ORS coins)
 *   - 2026/07/ggq/<key>.png tile images: 8 foods (latte, apple, blueberries, oat-milk, almonds,
 *     coconut-water, apple-juice, banana) as circular transparent PNGs + the three Hydralyte
 *     Powder pack shots (hydralyte-orange, hydralyte-apple-blackcurrant, hydralyte-lemonade)
 *     cycled across the rounds per KM review — watermelon and fruity bliss excluded.
 * Media already on the site (no upload needed): Vimeo MOA video 1122083239,
 *   Hydralyte Patient Leaflet PDF (2025/11/CAPH0051...), leaflet thumbnail (2025/12/...),
 *   Sick Days leaflet PDF + thumb (2026/03/CAPH0089..., 2026/03/hydra-sick-days.png),
 *   Sip to Stand featured image (2025/11/iStock-1296209723-768x512.jpg).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Related-resource card (CAPH0111 download-card style). Declared before the migration's
 * `return` so it exists when the closure runs (a `return` at file scope halts execution,
 * so anything after it is never defined). Guarded against redeclaration.
 */
if ( ! function_exists( 'hcp_ggq_res_card' ) ) {
	function hcp_ggq_res_card( $title, $desc, $url, $cta, $target, $img, $is_video = false ) {
		$play = $is_video
			? '<span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">'
				. '<span style="width:60px;height:60px;border-radius:50%;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center">'
				. '<span style="width:0;height:0;border-left:20px solid #FF6B00;border-top:12px solid transparent;border-bottom:12px solid transparent;margin-left:5px"></span>'
				. '</span></span>'
			: '';
		return '<div class="card column flex flex-col text-center md:text-left" data-pb-label="Column">'
			. '<div class="bg-secondary content-block" data-pb-label="Content Block" style="position:relative;padding:12px"><a href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '" style="display:block" aria-label="' . esc_attr( $title ) . '"><img src="' . esc_url( $img ) . '" class="mx-auto h-56" alt="" />' . $play . '</a></div>'
			. '<div class="card-body content-block pb-0 h-auto" data-pb-label="Content Block"><h3>' . esc_html( $title ) . '</h3><hr /><p>' . esc_html( $desc ) . '</p></div>'
			. '<div class="card-body content-block mt-auto h-auto" data-pb-label="Content Block"><div class="flex mt-auto justify-center md:justify-start"><a class="btn cta my-0" href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '">' . esc_html( $cta ) . '</a></div></div>'
			. '</div>';
	}
}

return [
	'description' => 'Create CAPH0125 "Guess the Glucose Challenge" interactive quiz article (mythbusting ORS glucose quiz).',

	'up' => function (): string {
		$slug = 'guess-the-glucose-challenge';

		$existing = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $existing ) {
			return "Post '$slug' already exists (ID {$existing->ID}) — skipping.";
		}

		$base = home_url( '' );

		/* ---- data: ORS (fixed lower-sugar side) + the 8 comparators ---- */
		$img_base = $base . '/wp-content/uploads/2026/07/ggq/';
		$ors      = [ 'Hydralyte Powder', '2.9 g / 200 mL', 2.9 ];
		$ors_imgs = [ 'hydralyte-orange.png', 'hydralyte-apple-blackcurrant.png', 'hydralyte-lemonade.png' ];

		// Keep a value's unit with its number ("100 mL" never splits) and compound food names together,
		// so the glucose list wraps sensibly ("1.35 g /" then "100 mL"; "Unsweetened" then "oat milk").
		$fmt_unit  = function ( $s ) { return str_replace( [ ' mL', ' cup', ' g' ], [ "\u{A0}mL", "\u{A0}cup", "\u{A0}g" ], $s ); };
		$grp_label = function ( $s ) { return str_replace( [ 'oat milk', 'coconut water', 'apple juice' ], [ "oat\u{A0}milk", "coconut\u{A0}water", "apple\u{A0}juice" ], $s ); };

		$foods = [
			[ 'Unsweetened oat milk',      '3 g / 200 mL',  3,  'oat-milk' ],
			[ 'Unsweetened coconut water', '6 g / 200 mL',  6,  'coconut-water' ],
			[ 'Almonds',                   '9 g / 1 cup',   9,  'almonds' ],
			[ 'Small latte',               '9 g / 230 mL',  9,  'latte' ],
			[ 'Unsweetened apple juice',   '20 g / 200 mL', 20, 'apple-juice' ],
			[ 'Blueberries',               '14 g / 1 cup',  14, 'blueberries' ],
			[ 'Whole banana',              '17 g',          17, 'banana' ],
			[ 'Whole apple',               '17 g',          17, 'apple' ],
		];

		$tile = function ( $role, $name, $serving, $img, $thumb_class = '' ) use ( $fmt_unit, $grp_label ) {
			return '<button type="button" class="ggq-tile" data-role="' . esc_attr( $role ) . '">'
				. '<span class="ggq-badge" aria-hidden="true"></span>'
				. '<span class="ggq-thumb ' . esc_attr( $thumb_class ) . '"><img src="' . esc_url( $img ) . '" alt="" loading="lazy" /></span>'
				. '<span class="ggq-name">' . esc_html( $grp_label( $name ) ) . '</span>'
				. '<span class="ggq-grams">' . esc_html( $fmt_unit( $serving ) ) . '</span>'
				. '</button>';
		};

		/* ---- rounds ---- */
		$rounds = '';
		// Pack flavour + matching pale circle tint, cycled orange -> purple -> lemon.
		// JS re-assigns these by on-screen order after the shuffle (first shown is always orange);
		// this baked assignment is the no-JS fallback.
		$ors_tints = [ 'ggq-ors-orange', 'ggq-ors-purple', 'ggq-ors-lemon' ];
		foreach ( $foods as $i => $f ) {
			$n         = $i + 1;
			$ors_img   = $img_base . $ors_imgs[ $i % count( $ors_imgs ) ];
			$ors_tint  = $ors_tints[ $i % count( $ors_tints ) ];
			$food_img  = $img_base . $f[3] . '.png';
			$rounds .= '<div class="ggq-round" data-round="' . $n . '">'
				. '<p class="ggq-eyebrow">Round ' . $n . ' of 8</p>'
				. '<h3 class="ggq-q">Which contains <em>less</em> sugar?</h3>'
				. '<div class="ggq-tiles">'
				. $tile( 'ors', $ors[0], $ors[1], $ors_img, $ors_tint )
				. $tile( 'food', $f[0], $f[1], $food_img )
				. '</div>'
				. '<p class="ggq-cue" aria-hidden="true">Next ↓</p>'
				. '</div>';
		}

		/* ---- ranked hierarchy (all 9 items, ascending) for the results reveal ---- */
		$ranked = array_merge( [ $ors ], $foods );
		usort( $ranked, function ( $a, $b ) { return $a[2] <=> $b[2]; } );
		$max  = 20;
		$bars = '';
		foreach ( $ranked as $r ) {
			$pct    = max( 9, round( $r[2] / $max * 100 ) );
			$is_ors = ( abs( $r[2] - 2.9 ) < 0.01 );
			$bars  .= '<div class="ggq-bar-row' . ( $is_ors ? ' is-ors' : '' ) . '">'
				. '<span class="ggq-bar-label">' . esc_html( $grp_label( $r[0] ) ) . '</span>'
				. '<span class="ggq-bar-track"><span class="ggq-bar-fill" style="width:' . $pct . '%"></span></span>'
				. '<span class="ggq-bar-val">' . esc_html( $fmt_unit( $r[1] ) ) . '</span>'
				. '</div>';
		}

		/* ---- scoped CSS (Hydralyte orange #FF6B00; green/red stay semantic) ---- */
		$css = <<<'CSS'
<style>
#ggq{--ggq-orange:#FF6B00;--ggq-orange-d:#E05E00;--ggq-green:#1a9d5b;--ggq-red:#d64545;--ggq-line:#d9e1e4;max-width:820px;margin:0 auto}
#ggq .ggq-progress{position:fixed;left:50%;bottom:22px;transform:translateX(-50%) translateY(160%);z-index:60;display:flex;align-items:center;gap:14px;min-width:270px;max-width:90vw;padding:12px 22px;background:#fff;border:1px solid var(--ggq-line);border-radius:99px;box-shadow:0 10px 34px rgba(20,30,40,.16);opacity:0;transition:transform .3s ease,opacity .3s ease}
#ggq .ggq-progress.is-visible{transform:translateX(-50%) translateY(0);opacity:1}
#ggq .ggq-progress.is-hidden{transform:translateX(-50%) translateY(160%);opacity:0}
#ggq .ggq-progress-count{font-weight:700;color:var(--ggq-orange-d);white-space:nowrap;font-size:.9rem}
#ggq .ggq-progress-track{flex:1;height:8px;min-width:110px;border-radius:99px;background:#f0f2f4;overflow:hidden}
#ggq .ggq-progress-fill{display:block;height:100%;width:0;background:var(--ggq-orange);border-radius:99px;transition:width .35s ease}
#ggq .ggq-round{padding:48px 0;border-bottom:1px dashed var(--ggq-line)}
#ggq .ggq-rounds .ggq-round:first-child{padding-top:36px}
#ggq .ggq-eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.8rem;font-weight:700;color:var(--ggq-orange-d);text-align:center;margin:0 0 8px}
#ggq .ggq-q{text-align:center;font-size:2rem;line-height:1.2;margin:0 0 28px}
#ggq .ggq-q em{color:var(--ggq-orange);font-style:normal}
#ggq .ggq-tiles{display:grid;grid-template-columns:1fr 1fr;gap:18px}
#ggq .ggq-tile{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;min-height:300px;padding:28px 18px;background:#fff;border:2px solid var(--ggq-line);border-radius:14px;cursor:pointer;text-align:center;transition:border-color .15s,box-shadow .15s,transform .15s;font:inherit;width:100%}
#ggq .ggq-tile:hover{border-color:var(--ggq-orange);box-shadow:0 8px 24px rgba(255,107,0,.14);transform:translateY(-2px)}
#ggq .ggq-thumb{display:flex;align-items:center;justify-content:center;width:168px;height:168px;border-radius:50%;overflow:hidden;background:transparent}
#ggq .ggq-thumb img{width:100%;height:100%;object-fit:cover;display:block;margin:0}
#ggq .ggq-tile[data-role="ors"] .ggq-thumb{background:#FFE7D2}
#ggq .ggq-tile[data-role="ors"] .ggq-thumb.ggq-ors-orange{background:#FFE7D2}
#ggq .ggq-tile[data-role="ors"] .ggq-thumb.ggq-ors-purple{background:#ECE1F3}
#ggq .ggq-tile[data-role="ors"] .ggq-thumb.ggq-ors-lemon{background:#FBF3CC}
#ggq .ggq-tile[data-role="ors"] .ggq-thumb img{object-fit:contain;padding:20px}
#ggq .ggq-name{font-weight:800;font-size:1.35rem;line-height:1.25}
#ggq .ggq-grams{font-weight:700;font-size:1.05rem;color:#5b6b72;opacity:0;max-height:0;transition:opacity .3s}
#ggq .ggq-badge{position:absolute;top:12px;right:12px;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;opacity:0;transform:scale(.6);transition:opacity .2s,transform .2s}
#ggq .ggq-round.is-done .ggq-tile{cursor:default}
#ggq .ggq-round.is-done .ggq-tile:hover{transform:none;box-shadow:none;border-color:var(--ggq-line)}
#ggq .ggq-round.is-done .ggq-grams{opacity:1;max-height:40px}
#ggq .ggq-tile.is-correct{border-color:var(--ggq-green);background:#eefaf3}
#ggq .ggq-tile.is-correct .ggq-badge{background:var(--ggq-green);opacity:1;transform:scale(1)}
#ggq .ggq-tile.is-correct .ggq-badge::after{content:"✓"}
#ggq .ggq-tile.is-wrong{border-color:var(--ggq-red);background:#fdecec}
#ggq .ggq-tile.is-wrong .ggq-badge{background:var(--ggq-red);opacity:1;transform:scale(1)}
#ggq .ggq-tile.is-wrong .ggq-badge::after{content:"✕"}
#ggq .ggq-cue{text-align:center;color:var(--ggq-orange-d);font-weight:600;margin:22px 0 0;opacity:0;transition:opacity .3s}
#ggq .ggq-round.is-done .ggq-cue{opacity:1}
#ggq .ggq-round:last-of-type .ggq-cue{display:none}
#ggq-results{display:none;padding:44px 0 48px}
#ggq-results.is-shown{display:block}
#ggq .ggq-score{text-align:center;margin:0 0 6px}
#ggq .ggq-score-num{font-size:2.4rem;font-weight:800;color:var(--ggq-orange)}
#ggq .ggq-score-sub{text-align:center;color:#5b6b72;margin:0 0 30px}
#ggq .ggq-rank-title{text-transform:uppercase;letter-spacing:.1em;font-weight:800;font-size:.9rem;color:var(--ggq-orange-d);text-align:center;margin:28px 0 4px}
#ggq .ggq-rank-arrow{position:relative;height:2px;background:var(--ggq-orange);margin:0 12px 22px}
#ggq .ggq-rank-arrow::after{content:"";position:absolute;right:-2px;top:-4px;border-left:10px solid var(--ggq-orange);border-top:5px solid transparent;border-bottom:5px solid transparent}
#ggq .ggq-bar-row{display:grid;grid-template-columns:150px 1fr 120px;align-items:center;gap:12px;padding:0;min-height:52px}
#ggq .ggq-bar-label{font-size:.9rem;text-align:right;color:#3a464c;line-height:1.25}
#ggq .ggq-bar-track{height:22px;background:#f0f2f4;border-radius:99px;overflow:hidden}
#ggq .ggq-bar-fill{display:block;height:100%;background:#c9d3d7;border-radius:99px}
#ggq .ggq-bar-row.is-ors .ggq-bar-label{font-weight:800;color:var(--ggq-orange-d)}
#ggq .ggq-bar-row.is-ors .ggq-bar-fill{background:var(--ggq-orange)}
#ggq .ggq-bar-val{font-size:.85rem;font-weight:700;color:#5b6b72}
#ggq .ggq-notscale{text-align:center;font-size:.8rem;color:#8a99a0;margin:14px 0 0}
@media(max-width:640px){#ggq .ggq-q{font-size:1.5rem}#ggq .ggq-tiles{grid-template-columns:1fr;max-width:380px;margin-left:auto;margin-right:auto}#ggq .ggq-tile{min-height:220px}#ggq .ggq-thumb{width:140px;height:140px}#ggq .ggq-bar-row{grid-template-columns:84px 1fr 104px;gap:8px}#ggq .ggq-bar-label{font-size:.8rem}#ggq .ggq-bar-val{font-size:.8rem}}
</style>
CSS;

		/* ---- inline JS: randomise, reveal, score, results ---- */
		$js = <<<'JS'
<script>
(function(){
  var root=document.getElementById('ggq');
  if(!root)return;
  function shuffle(a){for(var i=a.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1));var t=a[i];a[i]=a[j];a[j]=t;}return a;}
  var rounds=Array.prototype.slice.call(root.querySelectorAll('.ggq-round'));
  var wrap=rounds.length?rounds[0].parentNode:null;
  // randomise round order
  if(wrap){shuffle(rounds.slice()).forEach(function(r){wrap.appendChild(r);});}
  // randomise left/right within each round
  // NB: keep this inline script free of less-than signs and double-ampersands, in code
  // AND comments: wptexturize desyncs on the former, then entity-encodes the latter,
  // and the whole block dies with a SyntaxError.
  rounds.forEach(function(r){var t=r.querySelector('.ggq-tiles');if(t){if(0.5>Math.random()){t.appendChild(t.firstElementChild);}}});
  // pack flavour + matching pale circle tint by on-screen order: orange, purple, lemon, repeating
  var obase=root.getAttribute('data-ors-base')||'';
  var flav=[{f:'hydralyte-orange.png',c:'ggq-ors-orange'},{f:'hydralyte-apple-blackcurrant.png',c:'ggq-ors-purple'},{f:'hydralyte-lemonade.png',c:'ggq-ors-lemon'}];
  Array.prototype.slice.call(root.querySelectorAll('.ggq-round')).forEach(function(r,i){
    var e=r.querySelector('.ggq-eyebrow');if(e){e.textContent='Round '+(i+1)+' of 8';}
    var ors=r.querySelector('.ggq-tile[data-role="ors"]');
    if(ors){
      var fl=flav[i%flav.length];
      if(obase){var img=ors.querySelector('.ggq-thumb img');if(img){img.src=obase+fl.f;}}
      var th=ors.querySelector('.ggq-thumb');if(th){th.classList.remove('ggq-ors-orange','ggq-ors-purple','ggq-ors-lemon');th.classList.add(fl.c);}
    }
  });
  var total=rounds.length,answered=0,score=0,done=false;
  var bar=root.querySelector('.ggq-progress');
  var countEl=root.querySelector('.ggq-progress-count');
  var fillEl=root.querySelector('.ggq-progress-fill');
  var results=document.getElementById('ggq-results');
  function update(){if(countEl)countEl.textContent=answered+' of '+total+' done';if(fillEl)fillEl.style.width=(answered/total*100)+'%';}
  update();
  // keep the running-score pill visible only while the quiz is on screen and not yet finished
  if(bar){
    if('IntersectionObserver' in window){
      var io=new IntersectionObserver(function(es){es.forEach(function(e){ if(!done){ bar.classList.toggle('is-visible', e.isIntersecting); } });},{rootMargin:'0px 0px -15% 0px'});
      io.observe(root);
    } else { bar.classList.add('is-visible'); }
  }
  root.addEventListener('click',function(ev){
    var tile=ev.target.closest?ev.target.closest('.ggq-tile'):null;
    if(!tile)return;
    var round=tile.closest('.ggq-round');
    if(!round||round.classList.contains('is-done'))return;
    round.classList.add('is-done');
    var ors=round.querySelector('.ggq-tile[data-role="ors"]');
    if(ors)ors.classList.add('is-correct');
    if(tile.getAttribute('data-role')==='food'){tile.classList.add('is-wrong');}else{score++;}
    answered++;update();
    var next=round.nextElementSibling;
    if(answered===total){
      done=true;
      var num=results.querySelector('.ggq-score-num');
      if(num)num.textContent=score+' out of '+total;
      if(bar){bar.classList.remove('is-visible');bar.classList.add('is-hidden');}
      results.classList.add('is-shown');
    }
    // auto-advance: let the reveal register, then glide to the next pairing (or to the results)
    setTimeout(function(){
      if(answered===total){
        if(results){results.scrollIntoView({behavior:'smooth',block:'start'});}
      }else if(next){
        next.scrollIntoView({behavior:'smooth',block:'center'});
      }
    },1600);
  });
})();
</script>
JS;

		/* ---- copy blocks ---- */
		$intro = 'Oral rehydration solutions (ORS) can play an important and effective role in managing dehydration.<sup>1</sup> However, because ORS contain glucose, some clinicians may worry whether they are suitable for their patients on low&#8209;sugar diets (e.g., people with diabetes).<sup>2</sup>';

		/* ---- assemble post_content ---- */
		$content = ''
		/* perfected typography carried over from the CAPH0111 (Dr Jonny) article */
		. '<style>.entry-content p{line-height:1.7}.entry-content li{line-height:1.7}.entry-content h2{line-height:1.35}.entry-content h3{line-height:1.35}</style>'
		/* header — eyebrow/date pill; the banner image below carries the title (H1 kept for a11y/SEO) */
		. '<div class="section pb-lg" data-pb-label="Section"><div class="mx-auto max-w-7xl w-full px-4 md:px-6 grid" data-pb-label="Container"><div class="column" data-pb-label="Column"><div class="content-block text-center" data-pb-label="Content Block">'
		. '<div class="flex gap-md items-center justify-center mb-0"><p class="bg-accent-secondary mb-0 px-6 py-2 rounded-full text-heading">Quiz</p><p class="mb-0">13.08.26</p></div>'
		. '<h1 style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0">Guess the Glucose Challenge</h1></div></div></div></div>'
		/* header banner (doubles as the /blog/ featured-card thumbnail) */
		. '<div class="pt-0 section pb-0" data-pb-label="Section"><div class="mx-auto max-w-4xl w-full px-4 md:px-6" data-pb-label="Container"><div class="column" data-pb-label="Column"><div class="content-block" data-pb-label="Content Block">'
		. '<img src="' . $base . '/wp-content/uploads/2026/07/ggq-hero.png" class="h-auto rounded-xl w-full" alt="Guess the Glucose Challenge" /></div></div></div></div>'
		/* intro (wider container so it sits on ~3 lines, not 4) */
		. '<div class="pt-lg section pb-0" data-pb-label="Section"><div class="mx-auto max-w-4xl w-full px-4 md:px-6" data-pb-label="Container"><div class="column" data-pb-label="Column"><div class="content-block text-center py-0 section" data-pb-label="Content Block">'
		. '<p>' . $intro . '</p>'
		. '<p style="font-style:italic;margin-top:1rem">But how much glucose is really in an ORS such as Hydralyte?</p>'
		. '<h2 style="margin-top:2.75rem;margin-bottom:.75rem">Can you correctly pick which item contains <em>less</em> sugar per serving?</h2>'
		. '</div></div></div></div>'
		/* quiz (HCP-gated, like CAPH0111) */
		. '<div class="pt-0 section pb-0 logged_in_users_only" data-pb-label="Section"><div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"><div class="column" data-pb-label="Column"><div class="content-block" data-pb-label="Content Block">'
		. $css
		. '<div id="ggq" data-ors-base="' . esc_attr( $img_base ) . '">'
		. '<div class="ggq-progress"><span class="ggq-progress-count">0 of 8 answered</span><span class="ggq-progress-track"><span class="ggq-progress-fill"></span></span></div>'
		. '<div class="ggq-rounds">' . $rounds . '</div>'
		/* results */
		. '<div id="ggq-results">'
		. '<p class="ggq-score">Your score</p><p class="ggq-score"><span class="ggq-score-num">0 out of 8</span></p>'
		. '<p class="ggq-rank-title">Glucose content per serving</p><div class="ggq-rank-arrow"></div>'
		. $bars
		. '<p class="ggq-notscale">Not to scale. Based on average sugar content per serving, not a direct comparison.</p>'
		. '</div>'
		. '</div>'
		. $js
		. '</div></div></div></div>'
		/* clinical explainer — light-orange panel; subhead centred dark-orange; body + bold callout sit left of the MOA video */
		. '<div class="pt-8 section pb-0 logged_in_users_only" data-pb-label="Section"><div class="mx-auto max-w-5xl w-full px-4 md:px-6" data-pb-label="Container"><div class="column" data-pb-label="Column"><div class="content-block py-0 section" data-pb-label="Content Block">'
		. '<div class="rounded-xl" style="background-color:#FFE8D6;padding:clamp(1.5rem,4vw,2.25rem);margin-bottom:2rem;overflow:hidden">'
		/* top: subhead + body copy left, MOA video right */
		. '<div class="grid md:grid-cols-2 gap-lg items-start mt-0">'
		. '<div>'
		. '<h2 style="color:#FF6B00;margin-top:0;margin-bottom:1.25rem;font-size:clamp(1.4rem,4.8vw,2rem);line-height:1.25">ORS have less sugar than you may think and are suitable for people with diabetes<sup>1,2</sup></h2>'
		. '<p>The World Health Organization (WHO) recommends that ORS formulations contain only 1.35 g of glucose per 100 mL, which is less than many everyday foods.<sup>1,3</sup> With this small amount of glucose, <strong>some ORS may be considered carbohydrate-free, depending on the specific product.</strong>*<sup>2</sup></p>'
		. '<p class="mb-0">Unlike other electrolyte or sports drinks that may contain sugar for energy intake or taste, <strong>the small and precise amount of glucose present in true ORS formulations is designed to activate the sodium&#8209;glucose cotransporter (SGLT1).</strong><sup>1,4</sup> These SGLT1 pumps create an osmotic gradient to facilitate rapid rehydration that&#8217;s faster than water alone.<sup>4-6</sup></p>'
		. '</div>'
		/* MOA video (Vimeo 1122083239) */
		. '<div class="relative rounded-xl overflow-hidden mx-auto" style="aspect-ratio:228/426;width:100%;max-width:300px"><iframe class="absolute inset-0 w-full h-full" src="https://player.vimeo.com/video/1122083239" title="How Hydralyte rehydrates faster than water alone" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>'
		. '</div>'
		/* orange banner: full-bleed to the panel edges (v4 MD feedback — avoid box-in-box);
		   negative margins cancel the panel padding, panel overflow:hidden clips the bottom
		   corners, and matching padding keeps the banner copy on the panel copy's left line */
		. '<div style="background-color:#FF6B00;color:#fff;padding:clamp(1.5rem,4vw,2.25rem);margin-top:clamp(1.5rem,4vw,2rem);margin-left:calc(-1*clamp(1.5rem,4vw,2.25rem));margin-right:calc(-1*clamp(1.5rem,4vw,2.25rem));margin-bottom:calc(-1*clamp(1.5rem,4vw,2.25rem))">'
		. '<div class="grid md:grid-cols-2 gap-lg items-center">'
		. '<div class="text-center"><p class="mb-0" style="color:#fff;font-weight:700">Clinicians can feel confident recommending true ORS formulations based on the WHO guidelines, such as Hydralyte, to patients with diabetes, as well as other at&#8209;risk groups (e.g., older patients) who may struggle with oral fluid&#160;intake.</p></div>'
		. '<div class="text-center">'
		. '<p style="color:#fff;font-weight:700;margin-bottom:.75rem">Glucose content in Hydralyte&#160;products:</p>'
		. '<p style="color:#fff;margin-bottom:.45rem">Powder Sachets <strong>2.9&#160;g&#160;per&#160;200&#160;mL</strong></p>'
		. '<p style="color:#fff;margin-bottom:.45rem">Effervescent Tablets (x2) <strong>3.2&#160;g&#160;per&#160;200&#160;mL</strong></p>'
		. '<p class="mb-0" style="color:#fff">Ice Blocks <strong>1.0&#160;g&#160;per&#160;ice&#160;block</strong></p>'
		. '</div>'
		. '</div>'
		. '</div>'
		. '</div>'
		. '</div></div></div></div>'
		/* related resources — three tiles in one row, then the sample-order blue banner (CAPH0111 pattern) */
		. '<div class="pt-8 section pb-0 logged_in_users_only" data-pb-label="Section"><div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"><div class="column" data-pb-label="Column"><div class="content-block" data-pb-label="Content Block"><h2>Related resources</h2></div>'
		. '<div class="grid md:grid-cols-3 gap-base mt-base">'
		. hcp_ggq_res_card( 'Clinical Bites Series: Diabetes Sick Day Management', 'Bite-sized videos offering practical sick day management advice, featuring CDE Deb Hawthorne.', $base . '/tools-and-videos/', 'Watch videos', '_self', $base . '/wp-content/uploads/2026/07/caph0105-clinical-bites-video-1-thumbnail.png', true )
		. hcp_ggq_res_card( 'Sip to Stand: Why Hydration is Essential in POTS', 'Key information to support hydration management in POTS.', $base . '/blog/sip-to-stand-why-hydration-is-essential-in-pots/', 'Read article', '_self', $base . '/wp-content/uploads/2025/11/iStock-1296209723-768x512.jpg' )
		. hcp_ggq_res_card( 'Hydralyte Patient Leaflet', 'A helpful fact sheet to ensure appropriate ORS use and guide product selection.', $base . '/wp-content/uploads/2025/11/CAPH0051_Hydralyte-Patient-Leaflet_A4_2pp_V3.0_HR.pdf', 'Download', '_blank', $base . '/wp-content/uploads/2025/12/CAPH0051_Hydralyte-Patient-Leaflet_A4_2pp_V3.0_HR_thumbnail.png' )
		. '</div>'
		. '<div class="grid" style="margin-top:2.75rem"><div class="bg-center bg-cover col-span-full column justify-between items-center md:flex md:p-md md:p-xl p-lg rounded-md theme-dark gap-y-0" data-pb-label="Column" style="background-image: url(\'' . $base . '/wp-content/uploads/2025/02/CTA-background-small.png\');"><div class="content-block items-center justify-between md:flex" data-pb-label="Content Block"><div><h2>Order Hydralyte samples</h2><p>Have samples delivered directly to your practice</p></div></div><div class="content-block grid items-center ml-auto" data-pb-label="Content Block"><a class="btn cta mt-md ml-auto" href="/order-samples/" target="_self" rel="noopener">Order samples</a></div></div></div>'
		. '</div></div></div>'
		/* logged-out register/login band */
		. '<div class="py-0 section" data-pb-label="Section">[not_logged_in]<div class="mx-auto max-w-7xl w-full px-4 md:px-6 grid mt-xl" data-pb-label="Container"><div class="bg-center bg-cover col-span-full column justify-between items-center md:flex md:p-md md:p-xl p-lg rounded-md theme-dark gap-y-0" data-pb-label="Column" style="background-image: url(\'' . $base . '/wp-content/uploads/2025/02/CTA-background-small.png\');"><div class="content-block items-center justify-between md:flex" data-pb-label="Content Block"><div><h2>Welcome to Care Connect</h2><p><span style="font-size:1rem">The online portal for healthcare professional resources from Care Pharmaceuticals. Register to take the challenge.</span></p></div></div><div class="content-block flex items-center gap-xl" data-pb-label="Content Block"><a class="btn cta mt-md ml-auto" href="/register">Register</a><a class="btn cta mt-md ml-auto" href="/login">Login</a></div></div></div>[/not_logged_in]</div>'
		/* footnotes / references / mandatories */
		. '<div class="section pt-0" data-pb-label="Section"><div class="mx-auto max-w-7xl w-full px-4 md:px-6" data-pb-label="Container"><div class="column md:col-span-full py-xl" data-pb-label="Column"><div class="content-block" data-pb-label="Content Block">'
		. '<p class="text-sm">*When used as directed. ORS carbohydrate content is dose-dependent and may impact blood glucose levels with additional doses.</p>'
		. '<p class="text-sm">ORS, oral rehydration solutions; POTS, postural orthostatic tachycardia syndrome; SGLT1, sodium-glucose cotransporter 1; WHO, World Health Organization.</p>'
		. '<p class="text-sm"><b>References:</b></p>'
		. '<ol class="text-sm">'
		. '<li>World Health Organization (WHO). Oral rehydration salts. Production of the new ORS. 2006. Available from: https://www.who.int/publications/i/item/WHO-FCH-CAH-06.1 (accessed July 2026).</li>'
		. '<li>Australian Diabetes Educators Association. Clinical guiding principles for sick day management of adults with type 1 diabetes or type 2 diabetes: A guide for health professionals, Version 4.1.0 [updated June 2025]. Available from: https://www.ndss.com.au/wp-content/uploads/clinical-guide-sick-day-mngt.pdf (accessed July 2026).</li>'
		. '<li>The Concise New Zealand Food Composition Tables, 14th Edition 2021. Available from: https://www.foodcomposition.co.nz/foodfiles/concise-tables/ (accessed July 2026).</li>'
		. '<li>Mishra AB. <i>Int J Life Sci Biotechnol Pharm Res</i>. 2025;14(7):516&#8211;517.</li>'
		. '<li>Jeukendrup AE, et al. <i>Nutr Metab (Lond)</i>. 2009;6:9.</li>'
		. '<li>Wright EM, et al. <i>J Intern Med</i>. 2007;261(1):32&#8211;43.</li>'
		. '</ol>'
		. '<p class="text-sm">&#169;Care Pharmaceuticals 2026. Hydralyte&#174; is a registered trademark of Care Pharmaceuticals. All rights reserved. August 2026.</p>'
		. '<p class="text-sm font-bold">This information is intended for use by healthcare professionals only.</p>'
		. '</div></div></div></div>';

		$post_id = wp_insert_post(
			[
				'post_title'   => 'QUIZ: Guess the Glucose Challenge',
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_content' => $content,
				'post_excerpt' => 'Think oral rehydration solutions contain too much glucose for some patients? Take the challenge and see how they really stack up against everyday foods and drinks.',
				'post_date'    => current_time( 'mysql' ), // publish now → newest post → /blog/ featured slot
				'post_author'  => 1,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( 'wp_insert_post failed: ' . $post_id->get_error_message() );
		}

		wp_set_post_categories( $post_id, [ 1 ] );

		// Per-post featured-card button text (read by the {{card_cta}} REST field on /blog/).
		update_post_meta( $post_id, '_hcp_card_cta', 'Take the quiz' );

		/* featured image (placeholder hero; real art swaps in later) — needed for the /blog/ featured card */
		$hero_rel = '2026/07/ggq-hero.png';
		$hero_abs = wp_upload_dir()['basedir'] . '/' . $hero_rel;
		if ( file_exists( $hero_abs ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$thumb_id = wp_insert_attachment(
				[ 'post_title' => 'Guess the Glucose Challenge hero', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ],
				$hero_abs,
				$post_id
			);
			update_post_meta( $thumb_id, '_wp_attached_file', $hero_rel );
			wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $hero_abs ) );
			set_post_thumbnail( $post_id, $thumb_id );
			return "Created post '$slug' (ID $post_id) + featured image (attachment $thumb_id).";
		}

		return "Created post '$slug' (ID $post_id). WARNING: hero image $hero_rel not found — /blog/ featured card will have no image until it is uploaded.";
	},
];

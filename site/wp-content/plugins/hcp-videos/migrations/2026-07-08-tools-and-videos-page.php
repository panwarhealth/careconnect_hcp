<?php
/**
 * Set the Tools & Videos page (slug `tools-and-videos`) to the final Clinical
 * Bites hub layout: hero + episodes carousel (blue bg), Interactive Tools, and
 * the [video_grid] Videos section (excluding the Clinical Bites series).
 *
 * Idempotent: skips if the Clinical Bites hero (hcp-cb-hero) is already present.
 * Image URLs are root-relative so they resolve on any environment.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Set the Tools & Videos page to the Clinical Bites hub layout.',
	'up'          => function () {
		$page = get_page_by_path( 'tools-and-videos', OBJECT, 'page' );
		if ( ! $page ) {
			return 'tools-and-videos page not found';
		}
		if ( strpos( $page->post_content, 'hcp-cb-hero' ) !== false ) {
			return 'already applied (hcp-cb-hero present)';
		}
		$content = <<<'HUBHTML'
 <div class="bg-center bg-cover flex items-center section theme-dark md:h-4xl tbst-bg-image" data-pb-label="Section" style="background-image: url('/wp-content/uploads/2025/02/Medical-equipment.jpg');"> <div class="container" data-pb-label="Container"> <div class="column" data-pb-label="Column"> <div class="content-block max-w-3xl" data-pb-label="Content Block"> <h1 class="">Tools and Videos  </h1> <p class=" ">Explore our unique in-consultation tools and educational videos developed to support patient discussions about their conditions and management plans.  </p> </div> </div> </div> </div> <div class="section pb-0" data-pb-label="Section"> <div class="container grid" data-pb-label="Container"> <div class="column" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block"> <nav class="border-b space-x-lg text-paragraph border-stroke"><a class="active border-b-2 no-underline text-heading" href="#clinicalbites">Clinical Bites</a><a href="#interactivetools" class="no-underline text-paragraph hover:text-heading">Interactive Tools</a><a href="#videos" class="no-underline text-paragraph hover:text-heading">Videos</a> </nav> </div> </div> </div> </div> <div class="bg-secondary section hcp-cb-hero logged_in_users_only" data-pb-label="Section" id="clinicalbites"> <div class="container grid md:grid-cols-12 gap-base items-center" data-pb-label="Container"> <div class="column md:col-span-7 flex items-center" data-pb-label="Column"> <div class="content-block py-lg" data-pb-label="Content Block"> <p class="text-accent font-semibold" style="text-transform:uppercase;letter-spacing:.5px;font-size:.8rem;margin-bottom:.25rem;">4-Part Series</p> <h2 class="">Clinical Bites Series: Diabetes Sick Day Management</h2> <p class="" style="letter-spacing:.2px;line-height:1.75;">Practical guidance on improving the uptake and implementation of sick day action plans for patients with diabetes. Featuring Deborah Hawthorne, Consultant Pharmacist and Credentialled Diabetes Educator.</p><a class="btn cta ico i-arrow-right" href="/video/why-sick-day-planning-is-important-in-diabetes/" target="_self" rel="noopener">Watch now</a> </div> </div> <div class="column md:col-span-5 md:col-start-8 lg:col-span-4 lg:col-start-9" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block">[video_grid topic="clinical-bites-diabetes" columns="1" limit="1"]</div> </div> </div> </div> <div class="bg-secondary section pt-0 logged_in_users_only" data-pb-label="Section" id="clinicalbitesepisodes"> <div class="container" data-pb-label="Container"> <div class="content-block" data-pb-label="Content Block"><h3 class="">Episodes in this series</h3></div> <div class="content-block" data-pb-label="Content Block">[video_grid topic="clinical-bites-diabetes" series_total="4" layout="carousel"]</div> </div> </div> <div class="section logged_in_users_only" data-pb-label="Section" id="interactivetools"> <div class="container grid md:grid-cols-12 gap-base" data-pb-label="Container" style="row-gap:4rem;"> <div class="column flex items-center col-span-full" data-pb-label="Column"> <div class="content-block" data-pb-label="Content Block"> <h2 class="mb-0">Interactive Tools  </h2> </div> </div> <div class="column md:col-span-5 flex items-center" data-pb-label="Column"> <div class="content-block h-full" data-pb-label="Content Block"> <img src="/wp-content/uploads/2025/05/Rectal-model-tool.png" class="h-full mb-0 w-full object-cover" alt="" /> </div> </div> <div class="column md:col-span-6 md:col-start-7 flex items-center" data-pb-label="Column"> <div class="content-block py-lg" data-pb-label="Content Block"> <h3 class="">3D interactive rectum model  </h3> <p class="" style="letter-spacing:.2px;line-height:1.75;">Our innovative 3D rectum consultation tool has been specifically designed to enhance your consultations about rectal issues. Use it to visually guide patients through key anatomical features, discuss possible causes of their symptoms and explore treatment options and administration.  </p><a class="btn cta ico i-arrow-right" href="/rectogesic-3d-model/" target="_self" rel="noopener">Try now</a> </div> </div> <div class="column md:col-span-6 flex items-center" data-pb-label="Column"> <div class="content-block py-lg" data-pb-label="Content Block"> <h3 class="">The Allergy Analyser  </h3> <p class="" style="letter-spacing:.2px;line-height:1.75;">Create a personalised ocular and nasal allergy treatment plan.  </p> <p class="" style="letter-spacing:.2px;line-height:1.75;">The Allergy Analyser generates personalised treatment recommendations based on individual patient triggers, symptoms and the Australasian Society of Clinical Immunology and Allergy (ASCIA) guidelines.  </p> <p class=""> </p><a class="btn cta ico i-arrow-right" href="/allergy-analyser/" target="_self" rel="noopener">Try now</a> </div> </div> <div class="column md:col-span-5 md:col-start-8 flex items-center" data-pb-label="Column"> <div class="content-block h-full" data-pb-label="Content Block"> <img src="/wp-content/uploads/2023/10/registration-hero-img.png" class="h-full mb-0 w-full object-cover" alt="" /> </div> </div> </div> </div> <div class="bg-secondary section logged_in_users_only" data-pb-label="Section" id="videos"> <div class="container" data-pb-label="Container"> <div class="content-block" data-pb-label="Content Block"> <h2 class="">Videos</h2> </div> [video_grid exclude_topic="clinical-bites-diabetes"] </div> </div> <div class="bg-secondary pb-0 pt-0 section" data-pb-label="Section"> <div class="container grid" data-pb-label="Container"> <!-- spinnr:eyJpZCI6InBiLWVsZW1lbnQtaW5saW5lLWNvZGUtMTg5IiwibGFiZWwiOiJpbmxpbmUtY29kZSIsImVsZW1lbnQiOiJpbmxpbmUtY29kZSIsImNoaWxkcmVuIjpbXSwicGJfYXR0cmlidXRlcyI6eyJjbGFzcyI6IiJ9LCJwYl9jb21wb25lbnRfZGF0YSI6eyJkYXRhIjoiPHNjcmlwdD5cclxuICAgIGpRdWVyeShkb2N1bWVudCkucmVhZHkoZnVuY3Rpb24oJCkge1xyXG4gICAgXC9cLyBUYXJnZXQgb25seSBjYXJkcyB3aXRoIHRoZSBjdXN0b20gJ2pzLXZpZGVvLXRyaWdnZXInIGNsYXNzXHJcbiAgICAkKGRvY3VtZW50KS5vbihcImNsaWNrXCIsIFwiLmpzLXZpZGVvLXRyaWdnZXJcIiwgZnVuY3Rpb24oKSB7XHJcbiAgICAgICAgdmFyICRjYXJkID0gJCh0aGlzKTtcclxuICAgICAgICB2YXIgJHZpZGVvQ29udGFpbmVyID0gJGNhcmQuZmluZChcIi52aWQtY2NcIik7XHJcbiAgICAgICAgdmFyICRwbGF5SWNvbiA9ICRjYXJkLmZpbmQoXCIueXQtcFwiKTtcclxuICAgICAgICB2YXIgJGlmcmFtZSA9ICR2aWRlb0NvbnRhaW5lci5maW5kKFwiaWZyYW1lXCIpO1xyXG5cclxuICAgICAgICBcL1wvIE9ubHkgcnVuIGlmIHRoZSB2aWRlbyBpcyBjdXJyZW50bHkgaGlkZGVuXHJcbiAgICAgICAgaWYgKCR2aWRlb0NvbnRhaW5lci5oYXNDbGFzcyhcImhpZGRlblwiKSkge1xyXG4gICAgICAgICAgICBcclxuICAgICAgICAgICAgXC9cLyAxLiBUb2dnbGUgdmlzaWJpbGl0eVxyXG4gICAgICAgICAgICAkdmlkZW9Db250YWluZXIucmVtb3ZlQ2xhc3MoXCJoaWRkZW5cIik7XHJcbiAgICAgICAgICAgICRwbGF5SWNvbi5oaWRlKCk7XHJcblxyXG4gICAgICAgICAgICBcL1wvIDIuIEFkZCBhdXRvcGxheSB0byB0aGUgaWZyYW1lIFVSTFxyXG4gICAgICAgICAgICB2YXIgY3VycmVudFNyYyA9ICRpZnJhbWUuYXR0cihcInNyY1wiKTtcclxuICAgICAgICAgICAgXHJcbiAgICAgICAgICAgIFwvXC8gQ2hlY2sgaWYgVVJMIGFscmVhZHkgaGFzIHBhcmFtZXRlcnMgdG8gZGVjaWRlIGJldHdlZW4gJz8nIGFuZCAnJidcclxuICAgICAgICAgICAgdmFyIGpvaW5DaGFyID0gKGN1cnJlbnRTcmMuaW5kZXhPZignPycpICE9PSAtMSkgPyBcIiZcIiA6IFwiP1wiO1xyXG4gICAgICAgICAgICBcclxuICAgICAgICAgICAgXC9cLyBBcHBlbmQgYXV0b3BsYXkgaWYgaXQncyBub3QgYWxyZWFkeSBpbiB0aGUgc3RyaW5nXHJcbiAgICAgICAgICAgIGlmIChjdXJyZW50U3JjLmluZGV4T2YoXCJhdXRvcGxheT0xXCIpID09PSAtMSkge1xyXG4gICAgICAgICAgICAgICAgJGlmcmFtZS5hdHRyKFwic3JjXCIsIGN1cnJlbnRTcmMgKyBqb2luQ2hhciArIFwiYXV0b3BsYXk9MVwiKTtcclxuICAgICAgICAgICAgfVxyXG4gICAgICAgIH1cclxuICAgIH0pO1xyXG59KTtcclxuPFwvc2NyaXB0PiJ9LCJkcmFnZ2FibGUiOnRydWUsImRyb3BwYWJsZSI6dHJ1ZX0=  --> <div id="pb-element-inline-code-189">      <script>
    jQuery(document).ready(function($) {
    // Target only cards with the custom 'js-video-trigger' class
    $(document).on("click", ".js-video-trigger", function() {
        var $card = $(this);
        var $videoContainer = $card.find(".vid-cc");
        var $playIcon = $card.find(".yt-p");
        var $iframe = $videoContainer.find("iframe");

        // Only run if the video is currently hidden
        if ($videoContainer.hasClass("hidden")) {
            
            // 1. Toggle visibility
            $videoContainer.removeClass("hidden");
            $playIcon.hide();

            // 2. Add autoplay to the iframe URL
            var currentSrc = $iframe.attr("src");
            
            // Check if URL already has parameters to decide between '?' and '&'
            var joinChar = (currentSrc.indexOf('?') !== -1) ? "&" : "?";
            
            // Append autoplay if it's not already in the string
            if (currentSrc.indexOf("autoplay=1") === -1) {
                $iframe.attr("src", currentSrc + joinChar + "autoplay=1");
            }
        }
    });
});
</script> </div> <div class="bg-center bg-cover col-span-full column md:flex md:p-md md:p-xl p-lg rounded-md theme-dark" data-pb-label="Column" style="background-image: url('/wp-content/uploads/2025/02/CTA-background-small.png');"> <div class="content-block grid items-center md:justify-end mx-auto" data-pb-label="Content Block"><a class="border btn cta my-0 px-4xl shadow-xl border-accent-hover" href="/resources" target="_self">Explore Clinical Resources</a> </div> </div> </div> </div>
HUBHTML;
		wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
		return 'tools-and-videos page updated (' . strlen( $content ) . ' chars).';
	},
);

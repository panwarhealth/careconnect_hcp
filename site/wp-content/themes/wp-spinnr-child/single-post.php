<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WP_Spinnr
 */

get_header(); 
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<?php
		while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div class="entry-content relative">
    
                <div class="container flex flex-col lg:flex-row lg:flex-col gap-xs lg:gap-base js-sticky-widget fixed pt-4xl">
					<a href="#" class="mb-0 email-link w-11 h-11 rounded-full flex border border-dark items-center justify-center border-opacity-30 bg-white">
                        <svg class="w-5 h-5 opacity-60" width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g clip-path="url(#clip0_15_829)">
                                <rect width="24" height="24" fill="white"/>
                                <path d="M19.364 5.05026L3.10051 8.58579L10.8787 13.5355M19.364 5.05026L15.8284 21.3137L10.8787 13.5355M19.364 5.05026L10.8787 13.5355" stroke="#000000" stroke-linecap="round" stroke-linejoin="round"/>
                            </g>
                            <defs>
                                <clipPath id="clip0_15_829">
                                    <rect width="24" height="24" fill="white"/>
                                </clipPath>
                            </defs>
                        </svg>
					</a>
					<a href="#" class="mb-0 copy-link w-11 h-11 rounded-full flex border border-dark items-center justify-center border-opacity-30 bg-white">
						<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44" fill="none">
                            <rect x="0.5" y="0.5" width="43" height="43" rx="21.5" stroke="#E6E9E9"/>
                            <g clip-path="url(#clip0_1178_2540)">
                                <path d="M24.2422 26.125H25.3747C27.6397 26.125 29.4997 24.2725 29.4997 22C29.4997 19.735 27.6472 17.875 25.3747 17.875H24.2422" stroke="#041B22" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M19.75 17.875H18.625C16.3525 17.875 14.5 19.7275 14.5 22C14.5 24.265 16.3525 26.125 18.625 26.125H19.75" stroke="#041B22" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M19 22H25" stroke="#041B22" stroke-linecap="round" stroke-linejoin="round"/>
                            </g>
                            <defs>
                                <clipPath id="clip0_1178_2540">
                                    <rect width="18" height="18" fill="white" transform="translate(13 13)"/>
                                </clipPath>
                            </defs>
                        </svg>
					</a>
				</div>
				
                <?php
				if ( is_single() ) :
					the_content();
				else :
					the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'wp-spinnr' ) );
				endif;
				?>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

		<?php endwhile; ?>

	</main><!-- #main -->
</div><!-- #primary -->

<script>
jQuery(document).ready(function ($) {
    const shareURL = encodeURIComponent(window.location.href);

    $('.email-link').on('click', function(e) {
        const subject = 'Check out this blog post from Care Connect';
        const body = 'I thought you might be interested in this article: ' + shareURL;

        window.location.href = `mailto:?subject=${subject}&body=${body}`;
    })

    $('.copy-link').on('click', function(e) {
        e.preventDefault();
        const tempInput = $('<input>');
        
        $('body').append(tempInput);
        tempInput.val(window.location.href).select();
        document.execCommand('copy');
        tempInput.remove();
        
        alert('Link copied to clipboard!');
    });
});
</script>

<?php //get_sidebar(); ?>

<?php
get_footer();

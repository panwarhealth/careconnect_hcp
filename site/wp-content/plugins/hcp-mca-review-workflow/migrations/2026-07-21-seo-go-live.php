<?php
/**
 * SEO go-live: activate hcp-seo + Rank Math, apply Rank Math configuration,
 * and (production only) enable search-engine indexing.
 *
 * PRECONDITIONS on prod, verify BEFORE running:
 *   1. .htaccess has the "HCP SEO" X-Robots-Tag block (curl -sI any uploads PDF).
 *   2. robots.txt is the real file, not 0 bytes.
 *   3. seo-by-rank-math + hcp-seo plugin dirs are on disk.
 *
 * Rollback: wp option update blog_public 0
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'SEO go-live: activate hcp-seo + Rank Math, apply SEO config, set blog_public=1 (prod only).',
	'up'          => function (): string {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		foreach ( [ 'hcp-seo/hcp-seo.php', 'seo-by-rank-math/rank-math.php' ] as $plugin ) {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . dirname( $plugin ) ) ) {
				throw new \RuntimeException( "{$plugin} is not on disk — deploy plugin files first." );
			}
			if ( ! is_plugin_active( $plugin ) ) {
				$err = activate_plugin( $plugin );
				if ( is_wp_error( $err ) ) {
					throw new \RuntimeException( "Activating {$plugin} failed: " . $err->get_error_message() );
				}
			}
		}

		update_option( 'rank_math_modules', [ 'sitemap', 'rich-snippet', 'instant-indexing', 'local-seo' ] );
		update_option( 'rank_math_registration_skip', true );
		update_option( 'rank_math_wizard_completed', true );

		$g                       = (array) get_option( 'rank-math-options-general', [] );
		$g['usage_tracking']     = 'off';
		$g['frontend_seo_score'] = 'off';
		update_option( 'rank-math-options-general', $g );

		$t                             = (array) get_option( 'rank-math-options-titles', [] );
		$t['title_separator']          = '|';
		$t['capitalize_titles']        = 'off';
		$t['homepage_title']           = 'HCP Care Connect Portal | Clinical Resources for Healthcare Professionals';
		$t['homepage_description']     = 'Free clinical education, in-consultation tools and product samples for Australian GPs, pharmacists and nurses. Register with your AHPRA number for access.';
		$t['pt_post_title']            = '%title% %sep% %sitename%';
		$t['pt_post_default_rich_snippet'] = 'article';
		$t['pt_post_default_article_type'] = 'Article';
		$t['pt_page_title']            = '%title% %sep% %sitename%';
		$t['pt_page_default_rich_snippet'] = 'off';
		$t['disable_author_archives'] = 'on';
		$t['disable_date_archives']   = 'on';
		$t['knowledgegraph_type']     = 'company';
		$t['website_name']            = 'Care Connect';
		$t['knowledgegraph_name']     = 'Care Pharmaceuticals';
		$t['local_business_type']     = 'MedicalOrganization';
		$t['organization_description'] = "Care Connect is Care Pharmaceuticals' portal for Australian healthcare professionals: product information, clinical education and free sample ordering.";
		$t['url']                     = home_url( '/' );
		// Square 512x512 logo for Google knowledge panel, bundled with hcp-seo.
		// Sideload into the media library once; reuse on re-runs.
		$logo_file = WP_PLUGIN_DIR . '/hcp-seo/assets/care-pharmaceuticals-logo-square-512.png';
		$logo_id   = 0;
		$existing  = get_posts( [
			'post_type'   => 'attachment',
			'name'        => 'care-pharmaceuticals-logo-square-512',
			'numberposts' => 1,
			'fields'      => 'ids',
		] );
		if ( $existing ) {
			$logo_id = (int) $existing[0];
		} elseif ( file_exists( $logo_file ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$tmp = wp_tempnam( 'care-pharmaceuticals-logo-square-512.png' );
			copy( $logo_file, $tmp );
			$result = media_handle_sideload( [
				'name'     => 'care-pharmaceuticals-logo-square-512.png',
				'tmp_name' => $tmp,
			], 0, 'Care Pharmaceuticals logo (square)' );
			if ( is_wp_error( $result ) ) {
				@unlink( $tmp );
				throw new \RuntimeException( 'Logo sideload failed: ' . $result->get_error_message() );
			}
			$logo_id = (int) $result;
		}
		if ( $logo_id ) {
			$t['knowledgegraph_logo']    = wp_get_attachment_url( $logo_id );
			$t['knowledgegraph_logo_id'] = $logo_id;
		}
		$noindex_post_types = [ 'attachment', 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'ld-exam', 'sfwd-certificates', 'groups', 'brands', 'resources', 'qsm_quiz', 'frm_display' ];
		foreach ( $noindex_post_types as $pt ) {
			$t[ "pt_{$pt}_custom_robots" ] = 'on';
			$t[ "pt_{$pt}_robots" ]        = [ 'noindex', 'nofollow' ];
		}
		foreach ( [ 'category', 'post_tag' ] as $tax ) {
			$t[ "tax_{$tax}_custom_robots" ] = 'on';
			$t[ "tax_{$tax}_robots" ]        = [ 'noindex' ];
		}
		update_option( 'rank-math-options-titles', $t );

		$s                    = (array) get_option( 'rank-math-options-sitemap', [] );
		$s['authors_sitemap'] = 'off';
		$s['items_per_page']  = 200;
		$s['include_images'] = 'on';
		$s['pt_post_sitemap'] = 'on';
		$s['pt_page_sitemap'] = 'on';
		foreach ( array_merge( $noindex_post_types, [ 'dlm_download' ] ) as $pt ) {
			$s[ "pt_{$pt}_sitemap" ] = 'off';
		}
		foreach ( [ 'category', 'post_tag' ] as $tax ) {
			$s[ "tax_{$tax}_sitemap" ] = 'off';
		}
		update_option( 'rank-math-options-sitemap', $s );

		// Only production may become indexable; staging/local keep blog_public=0
		// unless flipped deliberately by hand.
		$is_prod = false !== strpos( home_url(), 'hcp.carepharma.com.au' );
		if ( $is_prod ) {
			update_option( 'blog_public', '1' );
		}

		flush_rewrite_rules();

		return 'hcp-seo + Rank Math active, SEO config applied'
			. ( $is_prod ? ', blog_public=1 (production)' : ', blog_public untouched (non-production host)' )
			. ', rewrites flushed.';
	},
];

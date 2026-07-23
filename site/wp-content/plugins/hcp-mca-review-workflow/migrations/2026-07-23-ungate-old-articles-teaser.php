<?php
/**
 * Convert the six page-level-gated (302-to-register) articles to the newer
 * teaser pattern: title + intro sections public, body sections blurred via
 * logged_in_users_only (overlay injected sitewide by the child footer).
 *
 * Five articles mention OTC products only: their [restrict] wrappers are
 * removed so the blurred body is in the DOM and indexable.
 *
 * 5-ways-to-help-ease-the-pain-of-anal-fissures (63079) mentions Rectogesic /
 * glyceryl trinitrate (prescription): its last two sections are wrapped
 * wholesale in [restrict] so that text never reaches public HTML. Blur classes
 * still apply for the visual treatment.
 *
 * Removing the RCP page metas makes hcp-seo flip these posts to index,follow
 * and into the sitemap automatically.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'SEO teaser: ungate 6 old articles (302 -> blur), keep 63079 prescription content server-stripped.',
	'up'          => function (): string {

		$otc_posts      = [ 31222, 32054, 34038, 25894, 64151 ];
		$rx_post        = 63079; // prescription mentions; keep server-side gating
		$public_leading = 2;     // sections kept public: hero/title + image
		$gate_class     = 'logged_in_users_only';

		// Offsets of every top-level builder section opening tag.
		$find_sections = function ( string $c ): array {
			preg_match_all( '/<div class="[^"]*"[^>]*data-pb-label="Section"/', $c, $m, PREG_OFFSET_CAPTURE );
			return array_map( fn( $x ) => $x[1], $m[0] );
		};

		// End offset (past the closing </div>) of the div starting at $start.
		$section_end = function ( string $c, int $start ): int {
			$depth = 0;
			$pos   = $start;
			$len   = strlen( $c );
			while ( $pos < $len ) {
				$open  = stripos( $c, '<div', $pos );
				$close = stripos( $c, '</div>', $pos );
				if ( false === $close ) {
					return $len;
				}
				if ( false !== $open && $open < $close ) {
					$depth++;
					$pos = $open + 4;
				} else {
					$depth--;
					$pos = $close + 6;
					if ( 0 === $depth ) {
						return $pos;
					}
				}
			}
			return $len;
		};

		$strip_restrict = fn( string $c ): string => preg_replace( '/\[\/?restrict[^\]]*\]/', '', $c );

		$add_gate_class = function ( string $c ) use ( $find_sections, $public_leading, $gate_class ): string {
			$offsets = $find_sections( $c );
			// Walk backwards so earlier offsets stay valid after insertion.
			for ( $i = count( $offsets ) - 1; $i >= $public_leading; $i-- ) {
				$off = $offsets[ $i ];
				if ( ! preg_match( '/^<div class="([^"]*)"/', substr( $c, $off, 400 ), $m ) ) {
					continue;
				}
				if ( str_contains( $m[1], $gate_class ) ) {
					continue;
				}
				$c = substr_replace( $c, '<div class="' . $m[1] . ' ' . $gate_class . '"', $off, strlen( $m[0] ) );
			}
			return $c;
		};

		// Wrap the full inner content of the section starting at $start in [restrict].
		$wrap_restrict = function ( string $c, int $start ) use ( $section_end ): string {
			$open_end = strpos( $c, '>', $start ) + 1;
			$end      = $section_end( $c, $start );
			$close    = strrpos( substr( $c, 0, $end ), '</div>' );
			$inner    = substr( $c, $open_end, $close - $open_end );
			if ( str_starts_with( ltrim( $inner ), '[restrict' ) ) {
				return $c;
			}
			return substr_replace( $c, '[restrict subscription=22 message="."]' . $inner . '[/restrict]', $open_end, $close - $open_end );
		};

		$log = [];

		foreach ( array_merge( $otc_posts, [ $rx_post ] ) as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'post' !== $post->post_type ) {
				throw new \RuntimeException( "Post {$post_id} not found." );
			}

			$c = $post->post_content;

			if ( $rx_post === $post_id ) {
				// Whole-section server-side gate for the two drug-mentioning
				// sections (treatment options + references), replacing the
				// partial inner [restrict] so shortcodes don't nest.
				$offsets = $find_sections( $c );
				if ( count( $offsets ) < 8 ) {
					throw new \RuntimeException( "Post {$post_id}: expected 8 sections, found " . count( $offsets ) );
				}
				// Strip then re-wrap: idempotent by construction.
				$c       = $strip_restrict( $c );
				$offsets = $find_sections( $c );
				// Backwards so the S7 offset survives the S8 edit.
				$c = $wrap_restrict( $c, $offsets[7] );
				$c = $wrap_restrict( $c, $offsets[6] );
			} else {
				$c = $strip_restrict( $c );
			}

			$c = $add_gate_class( $c );

			if ( $c !== $post->post_content ) {
				kses_remove_filters();
				$updated = wp_update_post( [ 'ID' => $post_id, 'post_content' => $c ], true );
				kses_init_filters();
				if ( is_wp_error( $updated ) ) {
					throw new \RuntimeException( "Post {$post_id}: " . $updated->get_error_message() );
				}
				$log[] = "{$post_id}:content";
			}

			foreach ( [ 'rcp_user_level', 'rcp_subscription_level', 'rcp_access_level', '_is_paid' ] as $meta ) {
				if ( metadata_exists( 'post', $post_id, $meta ) ) {
					delete_post_meta( $post_id, $meta );
					$log[] = "{$post_id}:{$meta}";
				}
			}
		}

		// The Rectal Health category carries a term-level RCP restriction whose
		// only member is 63079 — it would keep the 302 alive after the post
		// metas are gone.
		$term = get_term_by( 'name', 'Rectal Health', 'category' );
		if ( $term && metadata_exists( 'term', $term->term_id, 'rcp_restricted_meta' ) ) {
			delete_term_meta( $term->term_id, 'rcp_restricted_meta' );
			$log[] = "term-{$term->term_id}:rcp_restricted_meta";
		}

		// Rebuild the sitemap so the six join it immediately.
		if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		// Verify prescription strings cannot reach public HTML: everything
		// outside [restrict]...[/restrict] must be drug-free.
		$outside = preg_replace( '/\[restrict[^\]]*\].*?\[\/restrict\]/s', '', get_post( $rx_post )->post_content );
		if ( preg_match( '/Rectogesic|glyceryl/i', $outside ) ) {
			throw new \RuntimeException( "Post {$rx_post}: prescription mention outside [restrict] after transform." );
		}

		return 'Ungated: ' . implode( ', ', $log );
	},
];

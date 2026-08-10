<?php
/**
 * Series/episode helpers.
 *
 * A "series" is a `video_topic` term holding an ordered run of videos. Episode
 * order is the same order the listing shortcode uses, so the number a reader
 * sees on a card matches the number on the video page.
 *
 * `video_topic` does double duty: most terms are plain subject/brand groupings
 * (Hydralyte, FESS, Sleep) that must never be numbered, and a few are ordered
 * runs. Only the slugs declared in HCP_VIDEOS_SERIES_TOPICS get episode numbers
 * and the "Video N of M" lead-in — add a slug there to turn a topic into a
 * series, and nothing else needs changing.
 *
 * Two optional term meta values tune a declared series:
 *   _series_total   — episode count to advertise before every episode is live,
 *                     so "Video 1 of 5" is honest during a staggered release.
 *                     Falls back to the number of published episodes.
 *   _series_landing — permalink of the series landing page, used by the
 *                     "Video N of M" link on each video page.
 */

defined( 'ABSPATH' ) || exit;

const HCP_VIDEOS_SERIES_TAXONOMY = 'video_topic';

/**
 * The video_topic slugs that are ordered series.
 */
const HCP_VIDEOS_SERIES_TOPICS = array(
	'clinical-bites-diabetes',
	'clinical-bites-allergic-rhinitis',
);

/**
 * Slugs treated as series. Filterable so a series can be added without a code
 * change if one is ever needed mid-campaign.
 *
 * @return string[]
 */
function hcp_videos_series_topics(): array {
	return (array) apply_filters( 'hcp_videos_series_topics', HCP_VIDEOS_SERIES_TOPICS );
}

/**
 * The series term a video belongs to, or null when it is in none. A video may
 * carry several topics; the declared series wins over any plain grouping, so
 * tagging an episode with a brand topic never costs it its episode number.
 */
function hcp_videos_series_term( int $post_id ): ?WP_Term {
	$terms = get_the_terms( $post_id, HCP_VIDEOS_SERIES_TAXONOMY );

	if ( ! is_array( $terms ) ) {
		return null;
	}

	$series = hcp_videos_series_topics();

	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term && in_array( $term->slug, $series, true ) ) {
			return $term;
		}
	}

	return null;
}

/**
 * Published, listed episode IDs in running order.
 */
function hcp_videos_series_episode_ids( int $term_id ): array {
	static $cache = array();

	if ( isset( $cache[ $term_id ] ) ) {
		return $cache[ $term_id ];
	}

	$cache[ $term_id ] = get_posts( array(
		'post_type'      => 'video',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
		'meta_query'     => hcp_videos_listed_meta_query(),
		'tax_query'      => array( array(
			'taxonomy' => HCP_VIDEOS_SERIES_TAXONOMY,
			'field'    => 'term_id',
			'terms'    => $term_id,
		) ),
	) );

	return $cache[ $term_id ];
}

/**
 * 1-based position of a video within its series. 0 when it is not in one, or is
 * unlisted and so has no place in the running order.
 */
function hcp_videos_episode_number( int $post_id ): int {
	$term = hcp_videos_series_term( $post_id );
	if ( ! $term ) {
		return 0;
	}

	$position = array_search( $post_id, hcp_videos_series_episode_ids( $term->term_id ), true );

	return false === $position ? 0 : (int) $position + 1;
}

/**
 * Episodes the series advertises: the configured total, else however many are
 * published. Never reports fewer than are actually live.
 */
function hcp_videos_series_total( int $term_id ): int {
	$published = count( hcp_videos_series_episode_ids( $term_id ) );
	$declared  = (int) get_term_meta( $term_id, '_series_total', true );

	return max( $published, $declared );
}

function hcp_videos_series_landing_url( int $term_id ): string {
	return (string) get_term_meta( $term_id, '_series_landing', true );
}

/**
 * The episodes that follow this one, wrapping past the end back to the first.
 * Episode 4 of 5 gives 5, 1, 2, 3 — so the run never dead-ends on the last
 * episode, and every episode offers somewhere to go next.
 *
 * @return int[]|null Null when the video is not part of a series, which is the
 *                    signal for callers to fall back to their own ordering.
 */
function hcp_videos_series_following_ids( int $post_id ): ?array {
	$term = hcp_videos_series_term( $post_id );
	if ( ! $term ) {
		return null;
	}

	$episodes = hcp_videos_series_episode_ids( $term->term_id );
	$position = array_search( $post_id, $episodes, true );

	// In a series but not in the running order (unlisted, or draft): still scope
	// to the series, just with no meaningful place to continue from.
	if ( false === $position ) {
		return array_values( array_diff( $episodes, array( $post_id ) ) );
	}

	$after  = array_slice( $episodes, $position + 1 );
	$before = array_slice( $episodes, 0, $position );

	return array_merge( $after, $before );
}

/**
 * "Ep 3: " prefix for a title, or '' for a video outside a series. Used at
 * display time so the stored titles stay clean and re-ordering a series does
 * not require re-titling every episode.
 */
function hcp_videos_episode_prefix( int $post_id ): string {
	$n = hcp_videos_episode_number( $post_id );

	return $n ? 'Ep ' . $n . ': ' : '';
}

function hcp_videos_episode_title( int $post_id ): string {
	return hcp_videos_episode_prefix( $post_id ) . get_the_title( $post_id );
}

/**
 * "Video 3 of 5:" lead-in for an episode's description, linked back to the
 * series landing page so a reader who arrived on one episode can find the rest.
 * Returns '' for a video outside a series or with no landing page recorded.
 */
function hcp_videos_episode_intro( int $post_id ): string {
	$term = hcp_videos_series_term( $post_id );
	if ( ! $term ) {
		return '';
	}

	$n = hcp_videos_episode_number( $post_id );
	if ( ! $n ) {
		return '';
	}

	$label   = sprintf( 'Video %d of %d:', $n, hcp_videos_series_total( $term->term_id ) );
	$landing = hcp_videos_series_landing_url( $term->term_id );

	// Styled to match the Read more toggle below the description, so the
	// description doesn't mix a third font treatment (client feedback).
	return $landing
		? '<a class="hcp-series-link" href="' . esc_url( $landing ) . '">' . esc_html( $label ) . '</a> '
		: '<span class="hcp-series-link">' . esc_html( $label ) . '</span> ';
}

/**
 * Place the lead-in inside the first paragraph of the description so it reads as
 * the opening words of the sentence rather than a line of its own. Falls back to
 * prepending when the content does not start with a paragraph.
 */
function hcp_videos_prepend_episode_intro( string $content, int $post_id ): string {
	$intro = hcp_videos_episode_intro( $post_id );
	if ( '' === $intro ) {
		return $content;
	}

	$position = strpos( $content, '<p>' );

	return false === $position
		? $intro . $content
		: substr_replace( $content, '<p>' . $intro, $position, strlen( '<p>' ) );
}

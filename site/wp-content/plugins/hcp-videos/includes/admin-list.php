<?php
/**
 * Extra columns on Videos > All Videos: where each video sits in its series,
 * and whether its end card autoplays.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'manage_video_posts_columns', function ( array $columns ): array {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['hcp_episode']  = 'Series / Episode';
			$out['hcp_autoplay'] = 'Autoplay';
		}
	}
	return $out;
} );

add_action( 'manage_video_posts_custom_column', function ( string $column, int $post_id ) {
	if ( 'hcp_episode' === $column ) {
		$term = hcp_videos_series_term( $post_id );
		if ( ! $term ) {
			echo '&mdash;';
			return;
		}
		$n = hcp_videos_episode_number( $post_id );
		echo esc_html( $term->name );
		echo $n ? '<br><strong>' . esc_html( sprintf( 'Ep %d of %d', $n, hcp_videos_series_total( $term->term_id ) ) ) . '</strong>' : '';
	}

	if ( 'hcp_autoplay' === $column ) {
		$card = hcp_videos_endcard( $post_id );
		echo $card && ! empty( $card['autoplay'] ) ? 'On' : 'Off';
	}
}, 10, 2 );

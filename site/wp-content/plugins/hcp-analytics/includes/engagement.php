<?php
/**
 * First-party record of what each logged-in HCP looks at.
 *
 * One row per HCP, page and campaign: how many times they viewed it, first and
 * last time, and which campaign brought them (empty when none). Therapy areas
 * and brands are not copied in; they are read from the page's own terms when
 * a profile is needed, so they can never drift from the content.
 *
 * Staff accounts are skipped, and nothing is written unless tracking is active
 * for the request (see hcp_analytics_is_active()).
 */

defined( 'ABSPATH' ) || exit;

const HCP_ANALYTICS_DB_VERSION = '1';
const HCP_ANALYTICS_DB_OPTION  = 'hcp_analytics_db_version';

function hcp_analytics_engagement_table(): string {
	global $wpdb;

	return $wpdb->prefix . 'hcp_engagement';
}

function hcp_analytics_engagement_install(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = hcp_analytics_engagement_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			utm_source VARCHAR(100) NOT NULL DEFAULT '',
			utm_medium VARCHAR(100) NOT NULL DEFAULT '',
			utm_campaign VARCHAR(100) NOT NULL DEFAULT '',
			views INT UNSIGNED NOT NULL DEFAULT 1,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_object_source (user_id,object_id,utm_source,utm_campaign),
			KEY object_id (object_id),
			KEY utm_campaign (utm_campaign)
		) {$collate};"
	);

	update_option( HCP_ANALYTICS_DB_OPTION, HCP_ANALYTICS_DB_VERSION );
}

add_action( 'plugins_loaded', 'hcp_analytics_engagement_maybe_install' );
function hcp_analytics_engagement_maybe_install(): void {
	if ( get_option( HCP_ANALYTICS_DB_OPTION ) !== HCP_ANALYTICS_DB_VERSION ) {
		hcp_analytics_engagement_install();
	}
}

/**
 * Content types whose views are recorded.
 *
 * @return string[]
 */
function hcp_analytics_engagement_post_types(): array {
	return (array) apply_filters(
		'hcp_analytics_engagement_post_types',
		array( 'post', 'page', 'resources', 'video', 'brands', 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' )
	);
}

function hcp_analytics_engagement_record( int $user_id, int $object_id, array $source ): void {
	global $wpdb;

	$now = current_time( 'mysql' );
	$wpdb->query(
		$wpdb->prepare(
			'INSERT INTO ' . hcp_analytics_engagement_table() . ' (user_id, object_id, utm_source, utm_medium, utm_campaign, views, first_seen, last_seen)
			VALUES (%d, %d, %s, %s, %s, 1, %s, %s)
			ON DUPLICATE KEY UPDATE views = views + 1, last_seen = VALUES(last_seen), utm_medium = VALUES(utm_medium)',
			$user_id,
			$object_id,
			$source['utm_source'],
			$source['utm_medium'],
			$source['utm_campaign'],
			$now,
			$now
		)
	);
}

// Late, so access checks and redirects (which exit) run first.
add_action( 'template_redirect', 'hcp_analytics_engagement_track', 99 );
function hcp_analytics_engagement_track(): void {
	$user_id = get_current_user_id();
	if ( ! $user_id || ! is_singular( hcp_analytics_engagement_post_types() ) || ! hcp_analytics_is_active() || hcp_analytics_is_staff( $user_id ) ) {
		return;
	}

	hcp_analytics_engagement_record( $user_id, get_queried_object_id(), hcp_analytics_visit_source() );
}

/**
 * PDFs open straight from resource cards and article links, so the page view
 * above never sees them. The browser reports the click here instead, and it is
 * recorded against the resource that holds the PDF (its "download" field), or
 * the PDF itself when no resource does.
 */
add_action( 'rest_api_init', 'hcp_analytics_engagement_route' );
function hcp_analytics_engagement_route(): void {
	register_rest_route(
		'hcp-analytics/v1',
		'/pdf',
		array(
			'methods'             => 'POST',
			'callback'            => 'hcp_analytics_engagement_pdf',
			'permission_callback' => function () {
				return is_user_logged_in() && hcp_analytics_is_active() && ! hcp_analytics_is_staff( get_current_user_id() );
			},
			'args'                => array(
				'url' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ),
			),
		)
	);
}

function hcp_analytics_engagement_pdf( WP_REST_Request $request ): WP_REST_Response {
	$object_id = hcp_analytics_pdf_object( (string) $request['url'] );
	if ( $object_id ) {
		hcp_analytics_engagement_record( get_current_user_id(), $object_id, hcp_analytics_visit_source() );
	}

	return new WP_REST_Response( null, 204 );
}

/**
 * The resource that offers a PDF, else the PDF's attachment; 0 when unknown.
 */
function hcp_analytics_pdf_object( string $url ): int {
	global $wpdb;

	$attachment_id = attachment_url_to_postid( strtok( $url, '?#' ) );
	if ( ! $attachment_id ) {
		return 0;
	}
	$resource_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = 'download' AND pm.meta_value = %s AND p.post_type = 'resources' LIMIT 1",
			(string) $attachment_id
		)
	);

	return $resource_id ?: $attachment_id;
}

/**
 * An HCP's interests: views per therapy area and per brand, most viewed first.
 * The data foundation for personalised pages and emails.
 *
 * @return array{therapy_area: array<string, int>, brand: array<string, int>}
 */
function hcp_analytics_engagement_profile( int $user_id ): array {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.taxonomy, t.slug, SUM(e.views) AS views
			FROM " . hcp_analytics_engagement_table() . " e
			JOIN {$wpdb->term_relationships} tr ON tr.object_id = e.object_id
			JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy IN ('therapy_area', 'brand')
			JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE e.user_id = %d
			GROUP BY tt.taxonomy, t.slug
			ORDER BY views DESC",
			$user_id
		)
	);

	$profile = array( 'therapy_area' => array(), 'brand' => array() );
	foreach ( $rows as $row ) {
		$profile[ $row->taxonomy ][ $row->slug ] = (int) $row->views;
	}

	return $profile;
}

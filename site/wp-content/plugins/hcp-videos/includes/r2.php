<?php
/**
 * Cloudflare R2 storage for video files, posters and captions.
 *
 * Files are uploaded by the browser straight to R2 with presigned PUT URLs
 * (AWS Signature V4, query-string form), so large videos never pass through
 * this server. Public reads go through the bucket's custom domain.
 *
 * Credentials come from the environment (putenv in wp-config.php on prod,
 * docker-compose locally): HCP_R2_ACCESS_KEY_ID, HCP_R2_SECRET_ACCESS_KEY.
 */

defined( 'ABSPATH' ) || exit;

const HCP_VIDEOS_R2_ACCOUNT_ID  = '7ac10451a7a82babea847a7b08b8ac81';
const HCP_VIDEOS_R2_BUCKET      = 'careconnect-media';
const HCP_VIDEOS_R2_PUBLIC_BASE = 'https://cdn-careconnect.panwarhealth.com.au/';

function hcp_videos_r2_configured(): bool {
	return '' !== (string) getenv( 'HCP_R2_ACCESS_KEY_ID' ) && '' !== (string) getenv( 'HCP_R2_SECRET_ACCESS_KEY' );
}

function hcp_videos_r2_public_url( string $key ): string {
	return HCP_VIDEOS_R2_PUBLIC_BASE . $key;
}

/**
 * A presigned URL allowing one PUT of $key for $expires seconds.
 */
function hcp_videos_r2_presign_put( string $key, int $expires = 3600 ): string {
	$access = (string) getenv( 'HCP_R2_ACCESS_KEY_ID' );
	$secret = (string) getenv( 'HCP_R2_SECRET_ACCESS_KEY' );
	$host   = HCP_VIDEOS_R2_ACCOUNT_ID . '.r2.cloudflarestorage.com';
	$region = 'auto';
	$now    = time();
	$date   = gmdate( 'Ymd', $now );
	$stamp  = gmdate( 'Ymd\THis\Z', $now );
	$scope  = $date . '/' . $region . '/s3/aws4_request';

	$path  = '/' . HCP_VIDEOS_R2_BUCKET . '/' . str_replace( '%2F', '/', rawurlencode( $key ) );
	$query = array(
		'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
		'X-Amz-Credential'    => $access . '/' . $scope,
		'X-Amz-Date'          => $stamp,
		'X-Amz-Expires'       => (string) $expires,
		'X-Amz-SignedHeaders' => 'host',
	);
	ksort( $query );
	$canonical_query = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );

	$canonical_request = implode( "\n", array( 'PUT', $path, $canonical_query, 'host:' . $host, '', 'host', 'UNSIGNED-PAYLOAD' ) );
	$string_to_sign    = implode( "\n", array( 'AWS4-HMAC-SHA256', $stamp, $scope, hash( 'sha256', $canonical_request ) ) );

	$signing_key = hash_hmac( 'sha256', 'aws4_request',
		hash_hmac( 'sha256', 's3',
			hash_hmac( 'sha256', $region,
				hash_hmac( 'sha256', $date, 'AWS4' . $secret, true ), true ), true ), true );

	return 'https://' . $host . $path . '?' . $canonical_query
		. '&X-Amz-Signature=' . hash_hmac( 'sha256', $string_to_sign, $signing_key );
}

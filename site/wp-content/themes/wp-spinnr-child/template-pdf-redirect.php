<?php
/**
 * Template Name: PDF Redirect
 *
 * Sends a GA4 page view, then redirects to the URL stored in _pdf_redirect_url
 * postmeta. Use for tracked asset links (eDM, QR codes).
 */
defined( 'ABSPATH' ) || exit;

$redirect_url = get_post_meta( get_the_ID(), '_pdf_redirect_url', true );
if ( ! $redirect_url ) {
	wp_redirect( home_url( '/' ) );
	exit;
}

// GA4 ID from hcp-analytics; with no ID the page just redirects. Page context
// (logged in, profession, section) is added once the new tracking is active.
$ga4_id      = (string) apply_filters( 'hcp_ga4_measurement_id', '' );
$ga4_context = function_exists( 'hcp_analytics_is_active' ) && hcp_analytics_is_active() ? hcp_analytics_context() : array();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Redirecting&hellip;</title>
<?php if ( '' === $ga4_id ) : ?>
<script>window.location.replace( <?php echo wp_json_encode( $redirect_url ); ?> );</script>
<?php else : ?>
<script src="<?php echo esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga4_id ) ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?php echo wp_json_encode( $ga4_id ); ?>, <?php echo wp_json_encode( array( 'send_page_view' => false ) + $ga4_context ); ?>);

var didRedirect = false;
function doRedirect() {
    if ( ! didRedirect ) {
        didRedirect = true;
        window.location.replace( <?php echo wp_json_encode( $redirect_url ); ?> );
    }
}

gtag('event', 'page_view', { send_to: <?php echo wp_json_encode( $ga4_id ); ?>, event_callback: doRedirect });
setTimeout( doRedirect, 2000 ); // fallback if GA4 is blocked or fails
</script>
<?php endif; ?>
</head>
<body>
<noscript><meta http-equiv="refresh" content="0;url=<?php echo esc_attr( $redirect_url ); ?>"></noscript>
</body>
</html>

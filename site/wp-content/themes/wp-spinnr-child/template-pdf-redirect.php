<?php
/**
 * Template Name: PDF Redirect
 *
 * Fires GA4 via wp_head() then immediately redirects to the URL stored in
 * _pdf_redirect_url postmeta. Use for tracked asset links (eDM, QR codes).
 */
defined( 'ABSPATH' ) || exit;

$redirect_url = get_post_meta( get_the_ID(), '_pdf_redirect_url', true );
if ( ! $redirect_url ) {
	wp_redirect( home_url( '/' ) );
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Redirecting&hellip;</title>
<script src="https://www.googletagmanager.com/gtag/js?id=G-ZM1QH0ZTGW"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-ZM1QH0ZTGW', { send_page_view: false });

var didRedirect = false;
function doRedirect() {
    if ( ! didRedirect ) {
        didRedirect = true;
        window.location.replace( <?php echo wp_json_encode( $redirect_url ); ?> );
    }
}

gtag('event', 'page_view', { send_to: 'G-ZM1QH0ZTGW', event_callback: doRedirect });
setTimeout( doRedirect, 2000 ); // fallback if GA4 is blocked or fails
</script>
</head>
<body>
<noscript><meta http-equiv="refresh" content="0;url=<?php echo esc_attr( $redirect_url ); ?>"></noscript>
</body>
</html>

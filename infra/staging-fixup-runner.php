<?php
/**
 * Staging post-seed fix-up, uploaded and run by staging-fixup.sh, then deleted.
 * __TOKEN__ is replaced with a random value per run. The Formidable licence key
 * arrives by POST and is never written to disk.
 */
if ( ( $_GET['token'] ?? '' ) !== '__TOKEN__' ) {
	http_response_code( 404 );
	exit;
}
define( 'WP_USE_THEMES', false );
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
header( 'Content-Type: text/plain' );
set_time_limit( 300 );
clearstatcache( true );

global $wpdb;
$from = 'http://localhost:8080';
$to   = home_url();

function hcp_fixup_replace( $v, $f, $t ) {
	if ( is_string( $v ) ) {
		return str_replace( $f, $t, $v );
	}
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			$v[ $k ] = hcp_fixup_replace( $x, $f, $t );
		}
		return $v;
	}
	if ( is_object( $v ) && ! ( $v instanceof __PHP_Incomplete_Class ) ) {
		foreach ( get_object_vars( $v ) as $k => $x ) {
			$v->$k = hcp_fixup_replace( $x, $f, $t );
		}
	}
	return $v;
}

function hcp_fixup_value( $raw, $f, $t ) {
	if ( is_serialized( $raw ) ) {
		return maybe_serialize( hcp_fixup_replace( maybe_unserialize( $raw ), $f, $t ) );
	}
	// JSON-encoded values store slashes escaped.
	return str_replace( addcslashes( $f, '/' ), addcslashes( $t, '/' ), str_replace( $f, $t, $raw ) );
}

// 1. Local URLs left in serialized options, post content and postmeta by the seed.
$like = '%' . $wpdb->esc_like( 'localhost:8080' ) . '%';
$n    = 0;
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) ) as $r ) {
	$nv = hcp_fixup_value( $r->option_value, $from, $to );
	if ( $nv !== $r->option_value ) {
		$n += (int) $wpdb->update( $wpdb->options, array( 'option_value' => $nv ), array( 'option_id' => $r->option_id ) );
	}
}
$p = $wpdb->query( $wpdb->prepare(
	"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s), post_excerpt = REPLACE(post_excerpt, %s, %s) WHERE post_content LIKE %s OR post_excerpt LIKE %s",
	$from, $to, $from, $to, $like, $like
) );
$m = 0;
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ) ) as $r ) {
	$nv = hcp_fixup_value( $r->meta_value, $from, $to );
	if ( $nv !== $r->meta_value ) {
		$m += (int) $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $nv ), array( 'meta_id' => $r->meta_id ) );
	}
}
wp_cache_flush();
echo "urls: options=$n posts=$p postmeta=$m\n";

// 2. Formidable Pro licence (the local sanitize blanks it, which silently turns Pro off).
$key = trim( (string) ( $_POST['frm_key'] ?? '' ) );
if ( $key && class_exists( 'FrmProAppHelper' ) ) {
	FrmProAppHelper::get_updater();
	$r = FrmAddon::activate_license_for_plugin( $key, 'formidable_pro' );
	echo 'formidable licence: ' . ( ! empty( $r['success'] ) ? 'active' : 'FAILED ' . wp_strip_all_tags( (string) $r['message'] ) ) . "\n";
} else {
	echo "formidable licence: skipped (no key or Pro missing)\n";
}

// 3. Plugin DB upgrades: the seed comes from the local DB, which can be older than prod's code.
if ( class_exists( 'FrmAppController' ) && FrmAppController::needs_update() ) {
	FrmAppController::install();
}
if ( defined( 'ACF_PATH' ) ) {
	if ( ! function_exists( 'acf_has_upgrade' ) ) {
		require_once ACF_PATH . 'includes/upgrades.php';
	}
	if ( acf_has_upgrade() ) {
		acf_upgrade_all();
	}
}
echo 'db: frm=' . get_option( 'frm_db_version' ) . ' acf=' . get_option( 'acf_version' ) . "\n";

// 4. Rebuild Formidable's generated stylesheet now Pro is on (otherwise it lacks Pro rules).
if ( class_exists( 'FrmStyle' ) ) {
	( new FrmStyle() )->save_settings();
	clearstatcache( true );
	echo 'formidable css: pro=' . var_export( FrmAppHelper::pro_is_installed(), true ) . "\n";
}

// 5. Inventory for the parity check.
echo "core," . get_bloginfo( 'version' ) . "\n";
echo "--plugins\n";
foreach ( get_plugins() as $file => $data ) {
	$slug = '.' === dirname( $file ) ? basename( $file, '.php' ) : dirname( $file );
	echo $slug, ',', ( is_plugin_active( $file ) ? 'active' : 'inactive' ), ',', $data['Version'], "\n";
}

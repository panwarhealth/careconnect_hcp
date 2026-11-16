<?php
/**
 * Builds recipients.csv for the CAPH0135 Terms + consent update eDO.
 *
 * Every account except staff roles, junk addresses and anyone who has already
 * answered the consent question. Run on prod: wp eval-file recipients.php
 */

$dir     = getenv( 'HOME' ) . '/backups/edm-caph0135/';
$staff   = array( 'administrator', 'editor', 'contributor', 'author', 'hcp_mca_reviewer', 'shop_manager' );
$exclude = array( 'testttt@gmail.com' );

global $wpdb;
$rows = array();
$seen = array();
$skip = array( 'staff' => 0, 'answered' => 0, 'bad_email' => 0, 'excluded' => 0, 'dupe' => 0 );

foreach ( $wpdb->get_results( "SELECT ID, user_email FROM {$wpdb->users} ORDER BY ID" ) as $u ) {
	$roles = array_keys( (array) get_user_meta( $u->ID, $wpdb->prefix . 'capabilities', true ) );
	$email = strtolower( trim( $u->user_email ) );

	if ( array_intersect( $roles, $staff ) ) {
		$skip['staff']++;
	} elseif ( null !== hcp_consent_marketing_status( (int) $u->ID ) ) {
		$skip['answered']++;
	} elseif ( ! is_email( $email ) || str_ends_with( $email, '.invalid' ) ) {
		$skip['bad_email']++;
	} elseif ( in_array( $email, $exclude, true ) ) {
		$skip['excluded']++;
	} elseif ( isset( $seen[ $email ] ) ) {
		$skip['dupe']++;
	} else {
		$seen[ $email ] = true;
		$rows[]         = array( $u->ID, $email );
	}
}

$out = fopen( $dir . 'recipients.csv', 'w' );
fputcsv( $out, array( 'user_id', 'email' ) );
foreach ( $rows as $r ) {
	fputcsv( $out, $r );
}
fclose( $out );

echo 'recipients: ' . count( $rows ) . "\n";
foreach ( $skip as $k => $n ) {
	echo "skipped {$k}: {$n}\n";
}

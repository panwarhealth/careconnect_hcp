<?php
// Clears a user's consent answers so the pop-up asks again.
//   php.sh caph0085/reset.php Rob-Panwar
require __DIR__ . '/wp-load.php';
$user = get_user_by( 'login', $argv[1] ?? '' );
if ( ! $user ) {
	exit( "no such user\n" );
}
global $wpdb;
$n = $wpdb->delete( hcp_consent_table(), array( 'user_id' => $user->ID ), array( '%d' ) );
echo "cleared {$n} consent rows for {$user->user_login}\n";

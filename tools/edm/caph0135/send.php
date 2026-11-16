<?php
/**
 * Sends the CAPH0135 Terms + consent update eDO through prod wp_mail.
 *
 * Run on prod from the webroot:
 *   TEST=you@example.com wp eval-file ~/backups/edm-caph0135/send.php   one copy to TEST only
 *   LIMIT=100 DELAY=2 wp eval-file ~/backups/edm-caph0135/send.php       next 100 recipients
 *   LIMIT=0 DELAY=14 wp eval-file ~/backups/edm-caph0135/send.php        everyone left, ~250/hour
 *
 * sent.log is the dedupe record: anyone logged as sent is skipped, so a run
 * can be stopped and restarted. Anyone who answers the consent question
 * before their turn is skipped too.
 */

$dir      = getenv( 'HOME' ) . '/backups/edm-caph0135/';
$log      = $dir . 'sent.log';
$limit    = (int) ( getenv( 'LIMIT' ) ?: 0 );
$delay    = (int) ( getenv( 'DELAY' ) ?: 2 );
$test     = (string) getenv( 'TEST' );
$subject  = "We've updated our Terms of Use and Privacy Policy";
$headers  = array( 'Content-Type: text/html; charset=UTF-8', 'From: Care Connect <info@hcp.carepharma.com.au>' );
$campaign = 'utm_campaign=caph0135-consent-update';

$html = file_get_contents( $dir . 'email.html' );
$text = file_get_contents( $dir . 'email.txt' );
if ( ! $html || ! $text || false === strpos( $html, $campaign ) || false === strpos( $text, $campaign ) ) {
	echo "ABORT: template missing or not tagged\n";
	return;
}

add_action(
	'phpmailer_init',
	static function ( $mailer ) use ( $text ) {
		$mailer->AltBody = $text;
	}
);
$err = '';
add_action(
	'wp_mail_failed',
	static function ( $e ) use ( &$err ) {
		$err = $e->get_error_message();
	}
);

if ( $test ) {
	$ok = wp_mail( $test, $subject, $html, $headers );
	echo ( $ok ? 'TEST sent to ' : 'TEST FAILED to ' ) . $test . ( $err ? " ($err)" : '' ) . "\n";
	return;
}

$done = array();
if ( file_exists( $log ) ) {
	foreach ( file( $log ) as $line ) {
		$p = explode( "\t", trim( $line ) );
		if ( isset( $p[1], $p[2] ) && 'sent' === $p[1] ) {
			$done[ strtolower( $p[2] ) ] = true;
		}
	}
}

$rows = array_map( 'str_getcsv', file( $dir . 'recipients.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) );
array_shift( $rows );

$sent = $skipped = $answered = $failed = 0;
foreach ( $rows as $r ) {
	if ( $limit && $sent + $failed >= $limit ) {
		break;
	}
	[ $uid, $email ] = $r;
	if ( isset( $done[ strtolower( $email ) ] ) ) {
		$skipped++;
		continue;
	}
	if ( null !== hcp_consent_marketing_status( (int) $uid ) ) {
		$answered++;
		continue;
	}

	$err    = '';
	$ok     = wp_mail( $email, $subject, $html, $headers );
	$status = $ok ? 'sent' : 'FAIL';
	file_put_contents( $log, gmdate( 'c' ) . "\t{$status}\t{$email}\t{$uid}\t{$err}\n", FILE_APPEND );
	if ( $ok ) {
		$sent++;
		$done[ strtolower( $email ) ] = true;
	} else {
		$failed++;
	}
	sleep( $delay );
}

$left = count( $rows ) - count( $done );
echo "DONE sent={$sent} failed={$failed} already_sent={$skipped} answered_since={$answered} not_yet_sent~={$left}\n";

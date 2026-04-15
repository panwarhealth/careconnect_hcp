<?php
/**
 * Strip @tbstdigital.com.au addresses from active email configurations.
 *
 * Targets:
 *   - Formidable email actions (frm_form_actions posts) — cc + bcc fields
 *   - WP Mail SMTP Pro `alert_email` connections — system failure alerts
 *
 * Historical send-records in frm_logs are intentionally left intact.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Remove ben@ and jobs@tbstdigital.com.au from Formidable email actions and WP Mail SMTP alert connections.',

	'up' => function (): string {
		global $wpdb;
		$report = [];

		$tbst_addresses = [
			'ben@tbstdigital.com.au',
			'jobs@tbstdigital.com.au',
		];

		$strip_addresses = function ( string $list, array $remove ): string {
			$remove_lc = array_map( 'strtolower', $remove );
			$tokens    = preg_split( '/[,;]+/', $list );
			$kept      = [];
			foreach ( $tokens as $tok ) {
				$tok_clean = trim( $tok );
				if ( $tok_clean === '' ) {
					continue;
				}
				if ( in_array( strtolower( $tok_clean ), $remove_lc, true ) ) {
					continue;
				}
				$kept[] = $tok_clean;
			}
			return implode( ',', $kept );
		};

		// --- Formidable email actions --------------------------------------
		$action_post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_content LIKE %s",
			'frm_form_actions',
			'%@tbstdigital.com.au%'
		) );

		foreach ( $action_post_ids as $post_id ) {
			$post   = get_post( $post_id );
			$config = json_decode( $post->post_content, true );
			if ( ! is_array( $config ) ) {
				$report[] = "skip post $post_id (not JSON)";
				continue;
			}

			$changed = false;
			foreach ( [ 'cc', 'bcc', 'email_to' ] as $field ) {
				if ( empty( $config[ $field ] ) ) {
					continue;
				}
				$cleaned = $strip_addresses( (string) $config[ $field ], $tbst_addresses );
				if ( $cleaned !== $config[ $field ] ) {
					$config[ $field ] = $cleaned;
					$changed          = true;
				}
			}

			if ( ! $changed ) {
				continue;
			}

			$result = wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => wp_slash( wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) ),
				],
				true
			);
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( "wp_update_post($post_id) failed: " . $result->get_error_message() );
			}
			$report[] = "scrubbed action $post_id";
		}

		// --- WP Mail SMTP alert_email connections --------------------------
		$smtp = get_option( 'wp_mail_smtp', [] );
		if ( is_array( $smtp ) && isset( $smtp['alert_email']['connections'] ) && is_array( $smtp['alert_email']['connections'] ) ) {
			$before = $smtp['alert_email']['connections'];
			$after  = [];
			foreach ( $before as $conn ) {
				$send_to = strtolower( trim( $conn['send_to'] ?? '' ) );
				$matches = false;
				foreach ( $tbst_addresses as $addr ) {
					if ( strpos( $send_to, strtolower( $addr ) ) !== false ) {
						$matches = true;
						break;
					}
				}
				if ( ! $matches ) {
					$after[] = $conn;
				}
			}

			if ( count( $after ) !== count( $before ) ) {
				$smtp['alert_email']['connections'] = array_values( $after );
				if ( empty( $after ) ) {
					$smtp['alert_email']['enabled'] = false;
				}
				update_option( 'wp_mail_smtp', $smtp, false );
				$report[] = 'removed ' . ( count( $before ) - count( $after ) ) . ' wp_mail_smtp alert connection(s)';
			}
		}

		return $report ? implode( '; ', $report ) : 'No TBST addresses found in active configs.';
	},
];

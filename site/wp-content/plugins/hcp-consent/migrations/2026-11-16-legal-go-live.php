<?php
/**
 * Go-live: republish the Terms of Use and Privacy Policy with the legal
 * version set to the publish date.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Terms of Use + Privacy Policy: legal version 2026-11-16 (go-live).',
	'up'          => function () {
		return hcp_legal_publish( '2026-11-16' );
	},
);

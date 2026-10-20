<?php
/**
 * Publish the Thomsons Terms of Use and Privacy Policy (finals of 1 Sep 2026)
 * into the existing pages, and set the legal version to this date.
 *
 * Text is word for word from the lawyers' documents (migrations/content/),
 * with only the agreed fact and typo fixes.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Terms of Use + Privacy Policy: lawyers\' final text, legal version 2026-10-20.',
	'up'          => function () {
		return hcp_legal_publish( '2026-10-20' );
	},
);

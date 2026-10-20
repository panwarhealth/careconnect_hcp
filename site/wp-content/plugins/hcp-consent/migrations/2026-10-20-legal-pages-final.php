<?php
/**
 * Republish the Terms of Use and Privacy Policy without the open-question
 * notes for the client: the URLs are confirmed and the rest is handled off
 * the page. Same version; content files are the source.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Terms of Use + Privacy Policy: remove the highlighted client notes.',
	'up'          => function () {
		return hcp_legal_publish( '2026-10-20' );
	},
);

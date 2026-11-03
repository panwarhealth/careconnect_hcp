<?php
/**
 * Privacy Policy 5(d): "on it services" corrected to "on its services".
 * Same version; content files are the source.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Privacy Policy: "on its services" typo fix.',
	'up'          => function () {
		return hcp_legal_publish( '2026-10-20' );
	},
);

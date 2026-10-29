<?php
/**
 * CAPH0150: restore the landing page's register/login pop-up scripts.
 *
 * The 2026-09-08 activity-id-plumbing migration saved page 108753 through
 * KSES, which stripped both inline <script> wrappers (leaving the JS as
 * visible text) and the javascript: scheme from the Register/Login buttons.
 * Idempotent.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'CAPH0150: restore landing page pop-up scripts and button links stripped by KSES.',

	'up' => function (): string {
		$post = get_post( 108753 );
		if ( ! $post ) {
			throw new \RuntimeException( 'landing page not found' );
		}

		$scripts = 0;
		$content = preg_replace_callback(
			'/(<div id="pb-element-inline-code-[\d-]+">\s*)(\n\s*\(function\(\$\) \{.*?\}\)\(jQuery\);\n    ) (<\/div>)/s',
			function ( array $m ) use ( &$scripts ) {
				$scripts++;
				return $m[1] . '<script type="text/javascript">' . $m[2] . '</script> ' . $m[3];
			},
			$post->post_content
		);
		$links   = substr_count( $content, 'href="void(0);"' );
		$content = str_replace( 'href="void(0);"', 'href="javascript:void(0);"', $content );

		if ( $content === $post->post_content ) {
			return false !== strpos( $post->post_content, '<script' ) ? 'already restored' : 'pattern not found';
		}

		kses_remove_filters();
		wp_update_post( [ 'ID' => $post->ID, 'post_content' => wp_slash( $content ) ] );
		kses_init_filters();

		return "restored {$scripts} script blocks and {$links} button links";
	},
];

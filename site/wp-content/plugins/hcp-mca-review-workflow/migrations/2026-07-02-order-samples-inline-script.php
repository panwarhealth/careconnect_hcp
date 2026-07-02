<?php
/**
 * Remove the inline "OR separator" script from the Order Samples page (145).
 * wpautop injects <p> tags into the script when the page renders, producing a
 * SyntaxError for every logged-in visitor. The script now ships as
 * wp-spinnr-child/js/order-samples.js, enqueued on page 145 only.
 *
 * Removes BOTH the rendered element and the SPINNR builder metadata comment
 * (base64 JSON containing the same script) so a builder re-save cannot
 * re-emit it.
 *
 * Idempotency: keyed on the presence of the pb-element-inline-code-40 block.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Order Samples (145): strip the wpautop-broken inline OR-separator script; replaced by child-theme js/order-samples.js.',

	'up' => function (): string {
		$post_id = 145;
		$element = 'pb-element-inline-code-40';

		$post = get_post( $post_id );
		if ( ! $post ) {
			return "Page $post_id not found — skipping.";
		}

		$content = $post->post_content;
		if ( strpos( $content, $element ) === false ) {
			return 'Already at desired state.';
		}

		// SPINNR builder metadata comment whose base64 JSON references the element.
		$content = preg_replace_callback(
			'/<!--\s*spinnr:([A-Za-z0-9+\/=]+)\s*-->/',
			function ( $m ) use ( $element ) {
				$json = base64_decode( $m[1], true );
				return ( $json !== false && strpos( $json, $element ) !== false ) ? '' : $m[0];
			},
			$content
		);

		// The rendered element: <div id="pb-element-inline-code-40"> <script>…</script> </div>
		$content = preg_replace(
			'/<div id="' . preg_quote( $element, '/' ) . '">\s*<script>.*?<\/script>\s*<\/div>/s',
			'',
			$content
		);

		if ( strpos( $content, $element ) !== false ) {
			throw new \RuntimeException( "Element $element still present after rewrite — patterns did not match, aborting without save." );
		}

		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => wp_slash( $content ),
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $result->get_error_message() );
		}

		return "Page $post_id: inline OR-separator script removed.";
	},
];

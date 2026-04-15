<?php
/**
 * Strip the JS button-click hack from Formidable action 116305 (form 161
 * confirmation). The server-side hook in functions.php now handles lesson
 * completion reliably; the prior dev's `.trigger('click')` workaround is
 * dead weight.
 *
 * Idempotency: compares against the canonical desired success_msg and only
 * rewrites if different. Safe to re-run repeatedly.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Replace auto-click JS in form-161 confirmation action 116305 with a clean setTimeout redirect.',

	'up' => function (): string {
		$post_id = 116305;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return "Action $post_id not found — skipping.";
		}

		$config = json_decode( $post->post_content, true );
		if ( ! is_array( $config ) || ! isset( $config['success_msg'] ) ) {
			throw new \RuntimeException( "Action $post_id post_content is not valid JSON or missing success_msg." );
		}

		$new_msg = "Thanks for completing the Retrospective Analysis form. You'll be redirected to the activity evaluation shortly..."
			. "<script>setTimeout(function(){window.location.href='/courses/mini-clinical-audit/quizzes/activity-evaluation/';},3000);</script>";

		if ( $config['success_msg'] === $new_msg ) {
			return 'Already at desired state.';
		}

		$config['success_msg'] = $new_msg;

		// wp_update_post strips one layer of backslashes from post_content; pre-slash to survive.
		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => wp_slash( wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) ),
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $result->get_error_message() );
		}

		return "Action $post_id success_msg updated.";
	},
];

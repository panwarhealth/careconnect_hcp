<?php
/**
 * After completing the "Assessment Questions" quiz (106049) in course 95553,
 * LearnDash's default results page shows a "Return to Course" button that goes
 * back to the course root — leaving the user to manually find and click the
 * "Post-learning survey" (quiz 100865) from the curriculum list.
 *
 * This migration sets a custom return URL on quiz 106049 so the button goes
 * directly to the survey, maintaining linear progression without a detour.
 *
 * Idempotent: re-running detects the URL is already set and exits.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Set quiz 106049 return URL to Post-learning survey so users proceed directly after the assessment.',

	'up' => function (): string {
		$quiz_id     = 106049;
		$survey_path = '/courses/anal-fissures-breaking-the-cycle-and-the-stigma/quizzes/post-learning-survey/';
		$survey_url  = home_url( $survey_path );

		$meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );
		if ( ! is_array( $meta ) ) {
			throw new \RuntimeException( "Quiz {$quiz_id} meta not found or not an array." );
		}

		// Already set — no-op.
		if ( ! empty( $meta['sfwd-quiz_btnReturnURL'] ) ) {
			return "Quiz {$quiz_id} return URL already set to \"{$meta['sfwd-quiz_btnReturnURL']}\". No change.";
		}

		$meta['sfwd-quiz_btnReturn']    = 1;
		$meta['sfwd-quiz_btnReturnURL'] = $survey_url;

		$result = update_post_meta( $quiz_id, '_sfwd-quiz', $meta );
		if ( $result === false ) {
			throw new \RuntimeException( "update_post_meta failed for quiz {$quiz_id}." );
		}

		clean_post_cache( $quiz_id );

		return "Set quiz {$quiz_id} return URL to {$survey_url}.";
	},
];

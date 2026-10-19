<?php
/**
 * One registration form: the MCA landing page swaps Form 129 for Form 2, and
 * Form 129 goes to draft so it can no longer render anywhere. Its entries stay.
 *
 * Form 129 sent new sign-ups to the MCA activity homepage; Form 2 does the same
 * through its popup/current_url return path. RACGP number and CPD reporting
 * consent are still asked before the course starts (hcp-mca-review-workflow's
 * enrol modal).
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'MCA landing page: Form 129 -> Form 2 (returns to the activity homepage); Form 129 to draft.',
	'up'          => function () {
		$page = get_page_by_path( 'anal-fissures-breaking-the-cycle-and-the-stigma-landing', OBJECT, 'page' );
		if ( ! $page ) {
			throw new RuntimeException( 'MCA landing page not found.' );
		}

		$return = home_url( '/anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage/' );
		$form_2 = '[formidable id=2 popupval="1" current_url="' . esc_attr( $return ) . '"]';

		$content = preg_replace( '/\[formidable id=["\']?129["\']?[^\]]*\]/', $form_2, $page->post_content, -1, $count );
		$log     = array();

		if ( $count ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
			$log[] = "page {$page->ID}: {$count} embed(s) swapped";
		} elseif ( false !== strpos( $page->post_content, '[formidable id=2 ' ) ) {
			$log[] = "page {$page->ID}: already on Form 2";
		} else {
			throw new RuntimeException( 'No registration form embed found on the MCA landing page.' );
		}

		if ( class_exists( 'FrmForm' ) ) {
			FrmForm::set_status( 129, 'draft' );
			$log[] = 'Form 129 set to draft';
		}

		return implode( '; ', $log ) . '.';
	},
);

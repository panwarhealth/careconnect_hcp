<?php
/**
 * Publish the Thomsons Terms of Use and Privacy Policy (finals of 1 Sep 2026)
 * into the existing pages, and set the legal version to this date.
 *
 * Text is word for word from the lawyers' documents (migrations/content/),
 * with only the agreed fact and typo fixes. Open questions for the client are
 * marked hcp-legal__query and come out in the go-live migration. Overwrites
 * the page body each run, so re-running is safe; WordPress keeps the old
 * text as a revision.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'description' => 'Terms of Use + Privacy Policy: lawyers\' final text, legal version 2026-10-20.',
	'up'          => function () {
		$pages = array(
			'terms-of-use'   => 'terms-of-use.html',
			'privacy-policy' => 'privacy-policy.html',
		);

		$log = array();
		foreach ( $pages as $slug => $file ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page ) {
				throw new RuntimeException( "Page /{$slug}/ not found." );
			}

			$content = file_get_contents( __DIR__ . '/content/' . $file );
			if ( false === $content || '' === trim( $content ) ) {
				throw new RuntimeException( "Content file {$file} missing." );
			}

			$result = wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => $content,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( $result->get_error_message() );
			}
			$log[] = "/{$slug}/ (page {$page->ID})";
		}

		update_option( HCP_LEGAL_VERSION_OPTION, '2026-10-20' );

		return 'Updated ' . implode( ', ', $log ) . '; legal version 2026-10-20.';
	},
);

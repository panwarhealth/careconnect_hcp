<?php
/**
 * Lets a practice order samples more than once.
 *
 * Form 55 has been limited to one entry per user since 6 Dec 2024. A returning
 * customer's form pre-loaded their previous order and submitting UPDATED it, so
 * no new entry appeared and the fulfilment email, which fires on create only,
 * never sent. The on-update confirmation is worded identically to a new
 * submission, so the customer saw a success message and waited for samples
 * nobody had been told about. 143 orders were lost this way.
 *
 * Two changes, both settings:
 *   - single_entry off, so each order is its own entry and the email fires.
 *   - the email action also listens for update, covering anyone who loaded the
 *     old pre-filled form before this ran and submits after it.
 *
 * unique_email_id stays set but is inert: Formidable returns early on that
 * check when single_entry is off, and the email field's own unique flag is 0.
 *
 * Idempotent. To roll back, set single_entry to "1" and drop "update" from the
 * action's event list.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Order Samples (form 55): allow repeat orders so each one emails and is recorded',
	'up'          => function (): string {
		global $wpdb;

		$form_id   = 55;
		$action_id = 41541;
		$done      = [];

		$forms = $wpdb->prefix . 'frm_forms';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, options FROM {$forms} WHERE id = %d", $form_id ) );

		if ( ! $row ) {
			throw new RuntimeException( "Form {$form_id} not found." );
		}

		$options = maybe_unserialize( $row->options );

		if ( ! is_array( $options ) ) {
			throw new RuntimeException( "Form {$form_id} options did not unserialize to an array." );
		}

		if ( empty( $options['single_entry'] ) ) {
			$done[] = 'single_entry already off';
		} else {
			$options['single_entry'] = 0;

			$updated = $wpdb->update( $forms, [ 'options' => maybe_serialize( $options ) ], [ 'id' => $form_id ] );

			if ( false === $updated ) {
				throw new RuntimeException( "Failed to write options for form {$form_id}: {$wpdb->last_error}" );
			}

			$done[] = 'single_entry off';
		}

		$action = get_post( $action_id );

		if ( ! $action || 'frm_form_actions' !== $action->post_type || (int) $action->menu_order !== $form_id ) {
			throw new RuntimeException( "Post {$action_id} is not an action on form {$form_id}." );
		}

		$settings = json_decode( $action->post_content, true );

		if ( ! is_array( $settings ) || ! isset( $settings['event'] ) || ! is_array( $settings['event'] ) ) {
			throw new RuntimeException( "Action {$action_id} has no readable event list." );
		}

		if ( in_array( 'update', $settings['event'], true ) ) {
			$done[] = 'email action already fires on update';
		} else {
			$settings['event'][] = 'update';

			$encoded = wp_json_encode( $settings );

			if ( false === $encoded ) {
				throw new RuntimeException( "Could not re-encode action {$action_id}." );
			}

			// Written directly: wp_update_post would run the JSON through KSES
			// and slash handling for no benefit here.
			$updated = $wpdb->update( $wpdb->posts, [ 'post_content' => $encoded ], [ 'ID' => $action_id ] );

			if ( false === $updated ) {
				throw new RuntimeException( "Failed to write action {$action_id}: {$wpdb->last_error}" );
			}

			clean_post_cache( $action_id );

			$done[] = 'email action now fires on create and update';
		}

		return implode( '; ', $done );
	},
];

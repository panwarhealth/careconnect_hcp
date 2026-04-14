<?php
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_edit_account_form' ); ?>

<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

	<div class="card">
		<div class="card-body">

			<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

			<div class="grid md:grid-cols-2">
				<label for="account_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="w-full woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" />

				<label for="account_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="w-full woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" />

				<label for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<div>
					<input type="text" class="w-full woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" /> 
					<div><em class="text-md"><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></em></div>
				</div>

				<label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="email" class="w-full woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" />

				<label for="password_current"><?php esc_html_e( 'Current password', 'woocommerce' ); ?><br><span class="text-sm"><?php esc_html_e( '(leave blank to leave unchanged)', 'woocommerce' ); ?></span></label>
				<input type="password" class="w-full woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off" />

				<label for="password_1"><?php esc_html_e( 'New password', 'woocommerce' ); ?><br><span class="text-sm"><?php esc_html_e( '(leave blank to leave unchanged)', 'woocommerce' ); ?></span></label>
				<input type="password" class="w-full woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="off" />

				<label for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
				<input type="password" class="w-full woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="off" />
			
			</div>

			<?php do_action( 'woocommerce_edit_account_form' ); ?>

			<p>
				<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
				<button type="submit" class="woocommerce-Button btn cta m-0 <?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
				<input type="hidden" name="action" value="save_account_details" />
			</p>

			<?php do_action( 'woocommerce_edit_account_form_end' ); ?>

		</div>
	</div>

</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>

<?php
/**
 * Simple product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/simple.php.
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

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

echo wc_get_stock_html( $product ); // WPCS: XSS ok.

if ( $product->is_in_stock() ) : ?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

	<form class="cart flex items-center space-x-base mb-2xl" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<?php
		do_action( 'woocommerce_before_add_to_cart_quantity' );

		woocommerce_quantity_input(
			array(
				'classes'	  => 'mb-0',
				'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
				'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
				'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // WPCS: CSRF ok, input var ok.
			)
		);

		do_action( 'woocommerce_after_add_to_cart_quantity' );
		?>

		<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button btn cta m-0 alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>">
			<svg class="mr-3" width="25" height="24" viewBox="0 0 25 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M7.4375 20.125C6.64906 20.125 6 20.774 6 21.5626C6 22.351 6.64904 23.0001 7.4375 23.0001C8.22597 23.0001 8.87504 22.351 8.87506 21.5626L7.4375 20.125ZM7.4375 20.125C8.22589 20.125 8.875 20.774 8.87506 21.5626L7.4375 20.125ZM2.75 1.4375H1.3125V1H3.64141L4.52824 2.86668L4.79945 3.43756H5.43148H23.4688C23.5607 3.43756 23.6192 3.47018 23.648 3.497C23.6719 3.51929 23.6875 3.54628 23.6875 3.59566C23.6875 3.65114 23.6817 3.69964 23.6743 3.7354C23.6725 3.74407 23.6708 3.75123 23.6693 3.75687L19.3134 11.2492L19.3051 11.2636L19.2972 11.2781C19.042 11.7526 18.5839 12.0313 18.0451 12.0313H8.96574H8.41551L8.12102 12.4961L6.90333 14.418L6.88261 14.4507L6.86448 14.4849L7.74805 14.9532C6.86448 14.4849 6.86446 14.4849 6.86443 14.485L6.86438 14.4851L6.86427 14.4853L6.86401 14.4858L6.86334 14.487L6.86144 14.4906L6.85555 14.5019L6.83661 14.5387C6.82161 14.5682 6.80065 14.6101 6.77885 14.6564C6.75836 14.6998 6.73067 14.7607 6.70602 14.8246C6.69388 14.856 6.67716 14.9018 6.66216 14.9548L6.66206 14.9551C6.65236 14.9893 6.61873 15.108 6.61873 15.2579C6.61873 15.5769 6.7296 15.92 6.99546 16.1858C7.26132 16.4517 7.60442 16.5626 7.92342 16.5626H20.875V16.9063H7.625C7.03603 16.9063 6.63682 16.7493 6.39989 16.5439C6.1841 16.3568 5.99854 16.0411 5.99854 15.4688C5.99854 15.2155 6.07497 14.9702 6.1587 14.8286L7.98969 11.8486L8.27894 11.3779L8.03436 10.8824L3.64668 1.99482L3.37154 1.4375H2.75ZM17.9999 21.5626C17.9999 20.774 18.649 20.125 19.4374 20.125C20.2259 20.125 20.875 20.774 20.875 21.5625C20.875 22.351 20.2259 23.0001 19.4374 23.0001C18.649 23.0001 17.9999 22.351 17.9999 21.5626Z" stroke="currentColor" stroke-width="2"/>
			</svg>
			<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
		</button>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
	</form>

	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>

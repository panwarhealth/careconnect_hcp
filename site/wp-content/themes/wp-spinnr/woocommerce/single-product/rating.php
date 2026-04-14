<?php
/**
 * Single Product Rating
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/rating.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $product;

if ( ! wc_review_ratings_enabled() ) {
	return;
}

$rating_count = $product->get_rating_count();
$review_count = $product->get_review_count();
$average      = $product->get_average_rating();

if ( $rating_count > 0 ) : ?>

<div class="rating flex items-center mb-base">
	<?php
		$rating = floatval($product->get_average_rating());
		for($x=1; $x<=5; $x++){
			if($rating >= $x){
				echo '<span><svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 0.5L7.34708 4.6459H11.7063L8.17963 7.2082L9.52671 11.3541L6 8.7918L2.47329 11.3541L3.82037 7.2082L0.293661 4.6459H4.65292L6 0.5Z" fill="#3C79F5"/></svg></span>';
			}
			else {
				echo '<span><svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 2.11803L6.87156 4.80041L6.98381 5.1459H7.34708H10.1675L7.88573 6.8037L7.59184 7.01722L7.7041 7.36271L8.57565 10.0451L6.29389 8.38729L6 8.17376L5.70611 8.38729L3.42435 10.0451L4.2959 7.36271L4.40816 7.01722L4.11426 6.8037L1.8325 5.1459H4.65292H5.01619L5.12844 4.80041L6 2.11803Z" stroke="#3C79F5"/></svg></span>';
			}
		}
	?>
	<span class="ml-2 text-sm text-accent"><?php echo $product->get_average_rating(); ?></span>
</div>

<?php endif; ?>

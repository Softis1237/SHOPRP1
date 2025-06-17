<?php
/**
 * Dokan Store Single Coupon Template (Overridden by Woodmart Child RPG)
 *
 * This template is called for each coupon in a loop by Dokan.
 * It should output the HTML for a single coupon item.
 * The grid container that holds multiple coupons should be handled by
 * the parent Dokan template or styled via CSS targeting Dokan's existing wrapper.
 */

defined( 'ABSPATH' ) || exit;

// The $coupon (WP_Post object) variable is passed to this template by Dokan.
// $vendor_id may also be available depending on the Dokan version and context.

if ( ! isset( $coupon ) || ! is_a( $coupon, 'WP_Post' ) || 'shop_coupon' !== $coupon->post_type ) {
	// error_log( 'WoodmartChildRPG Dokan Single Coupon Template: Invalid or missing $coupon object or wrong post type.' );
	return;
}

$wc_coupon = new \WC_Coupon( $coupon->ID );
if ( ! $wc_coupon->get_id() ) {
	// error_log( 'WoodmartChildRPG Dokan Single Coupon Template: Could not instantiate WC_Coupon for ID: ' . $coupon->ID );
	return;
}

$theme_instance = null;
if ( class_exists( 'WoodmartChildRPG\Core\Theme' ) ) {
	$theme_instance = \WoodmartChildRPG\Core\Theme::get_instance();
}

if ( $theme_instance && method_exists( $theme_instance, 'get_dokan_integration_manager' ) ) {
	$dokan_integration_manager = $theme_instance->get_dokan_integration_manager();
	if ( $dokan_integration_manager instanceof \WoodmartChildRPG\Integration\Dokan\DokanIntegrationManager ) {
		// The get_single_dokan_coupon_html() method should return the complete HTML 
		// for one coupon item, typically wrapped in a div like <div class="store-coupon-item-rpg">...</div>
		echo $dokan_integration_manager->get_single_dokan_coupon_html( $wc_coupon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		// Fallback: If our integration manager is not available, display basic coupon info.
		// error_log( 'WoodmartChildRPG Dokan Single Coupon Template: Could not get DokanIntegrationManager instance.' );
		// echo '<div class="dokan-coupon-item-fallback" style="border:1px dashed #ccc; padding:10px; margin-bottom:10px;">';
		// echo '<strong>' . esc_html__( 'Coupon Code:', 'woodmart-child' ) . '</strong> ' . esc_html( $wc_coupon->get_code() ) . '<br>';
		// echo esc_html( $wc_coupon->get_description() );
		// echo '</div>';
	}
} else {
	// error_log( 'WoodmartChildRPG Dokan Single Coupon Template: Theme instance or get_dokan_integration_manager method not found.' );
}

?>

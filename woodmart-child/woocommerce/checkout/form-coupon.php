<?php
/**
 * Checkout coupon form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-coupon.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1 // Убедитесь, что эта версия актуальна для вашей установки WooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! wc_coupons_enabled() ) { // @codingStandardsIgnoreLine.
    return;
}

?>
<div class="woocommerce-form-coupon-toggle">
    <?php
        wc_print_notice( apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Have a coupon?', 'woocommerce' ) . ' <a href="#" class="showcoupon">' . esc_html__( 'Click here to enter your code', 'woocommerce' ) . '</a>' ), 'notice' );
    ?>
</div>

<div class="checkout_coupon woocommerce-form-coupon" style="display:none"> <?php // Убран method="post" с div, он не нужен ?>
    <?php
    // Используем наш кастомный HTML для формы купона
    if ( class_exists( 'WoodmartChildRPG\\WooCommerce\\CartCustomizations' ) ) {
        $rpg_cart_customizer = new \WoodmartChildRPG\WooCommerce\CartCustomizations();
        // Метод get_custom_coupon_form_cart_html() из CartCustomizations.php будет использован здесь.
        // Он генерирует input#rpg_cart_coupon_code и button#rpg_apply_cart_coupon_button
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $rpg_cart_customizer->get_custom_coupon_form_cart_html(); 
    } else {
        // error_log('WoodmartChildRPG Error: CartCustomizations class not found for checkout coupon form.');
    }
    ?>
</div>

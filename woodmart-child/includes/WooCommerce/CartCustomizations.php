<?php
/**
 * Файл: woodmart-child/includes/WooCommerce/CartCustomizations.php
 * Управляет кастомизациями страницы корзины, связанными с RPG купонами.
 */

namespace WoodmartChildRPG\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class CartCustomizations {

    /**
     * Конструктор.
     */
    public function __construct() {
        // Конструктор
    }

	/**
     * Удаляет стандартную форму купона WooCommerce со страницы корзины.
     */
    public function remove_default_coupon_form_cart() {
        if ( function_exists( 'is_cart' ) && is_cart() ) {
            remove_action( 'woocommerce_cart_collaterals', array( \WC_Cart_Totals::class, 'coupon_html' ), 10 );
        }
    }

    /**
     * Возвращает HTML для кастомной формы ввода купона продавца на странице корзины.
     * @return string HTML-код формы.
     */
    public function get_custom_coupon_form_cart_html() {
        if ( ! wc_coupons_enabled() ) {
            return '';
        }
        if ( WC()->cart && WC()->cart->is_empty() ) {
            return '';
        }

        ob_start();
        ?>
        <div class="rpg-custom-coupon-form-cart"> 
            <h3><?php esc_html_e( 'Есть купон продавца?', 'woodmart-child' ); ?></h3>
            <p><?php esc_html_e( 'Если у вас есть код купона от одного из продавцов, введите его ниже. Он будет добавлен в ваш инвентарь и активирован.', 'woodmart-child' ); ?></p>
            <div class="rpg-cart-coupon-input-group">
                <label for="rpg_cart_coupon_code" class="screen-reader-text"><?php esc_html_e( 'Код купона', 'woodmart-child' ); ?></label>
                <input type="text" name="rpg_cart_coupon_code" class="input-text" id="rpg_cart_coupon_code" value="" placeholder="<?php esc_attr_e( 'Код купона продавца', 'woodmart-child' ); ?>" />
                <?php // ИЗМЕНЕНИЕ: type="submit" и убран класс "button", оставлен "rpg-apply-button" ?>
                <button type="submit" class="rpg-apply-button" name="rpg_apply_cart_coupon" id="rpg_apply_cart_coupon_button" value="<?php esc_attr_e( 'Применить купон', 'woodmart-child' ); ?>"><?php esc_html_e( 'Применить купон', 'woodmart-child' ); ?></button>
            </div>
            <div class="rpg-cart-coupon-message" style="display:none; margin-top:10px;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

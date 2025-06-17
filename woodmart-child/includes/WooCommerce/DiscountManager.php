<?php
/**
 * Файл: woodmart-child/includes/WooCommerce/DiscountManager.php
 * Менеджер скидок RPG в WooCommerce.
 */

namespace WoodmartChildRPG\WooCommerce;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\RPG\RaceFactory;
use WoodmartChildRPG\RPG\Races\Dwarf;
use WoodmartChildRPG\RPG\Races\Elf;
use WoodmartChildRPG\RPG\Races\Orc;
use WoodmartChildRPG\Integration\Dokan\DokanUserCouponDB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class DiscountManager {

	private $character_manager;
    private $dokan_coupon_db; 

	public function __construct( RPGCharacter $character_manager, DokanUserCouponDB $dokan_coupon_db ) { 
		$this->character_manager = $character_manager;
        $this->dokan_coupon_db = $dokan_coupon_db; 
	}

	public function apply_rpg_cart_discounts( \WC_Cart $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
		// Выходим, если это не страница корзины или оформления заказа, 
        // чтобы избежать попыток применения купонов и вывода уведомлений на других страницах.
        // Однако, пассивные бонусы рас могут быть нужны и для отображения цен где-то еще,
        // но `woocommerce_cart_calculate_fees` в основном для cart/checkout.
        // Оставим эту проверку для более явного контроля.
		if ( ! is_cart() && ! is_checkout() ) {
            // Если мы не в корзине или на странице оформления заказа, возможно, не стоит вообще ничего делать с активными купонами.
            // Но хук woocommerce_cart_calculate_fees может вызываться и для мини-корзины.
            // Пока оставим более тонкую проверку ниже.
        }

		if ( $cart->is_empty() ) return;
		$user_id = get_current_user_id();
		if ( ! $user_id ) return; 

		$user_race_slug = $this->character_manager->get_race( $user_id );
		$user_level     = $this->character_manager->get_level( $user_id );
		$race_object    = RaceFactory::create_race( $user_race_slug );

        // --- Шаг 1: Применяем пассивные расовые и уровневые бонусы ---
        $this->apply_base_race_and_level_discounts( $cart, $user_id, $user_race_slug, $user_level, $race_object );
        $this->apply_dwarf_level_discount( $cart, $user_id, $user_race_slug, $user_level, $race_object );

        // --- Шаг 2: Обработка "активированного" купона Dokan/WooCommerce из RPG-сессии ---
        $active_dokan_details = WC()->session ? WC()->session->get( 'active_rpg_dokan_coupon_details' ) : null;
        $dokan_coupon_code_from_rpg_session = null;
        $dokan_coupon_was_successfully_applied_by_wc = false;

        if ( !empty($active_dokan_details) && isset($active_dokan_details['code']) ) {
            $dokan_coupon_code_from_rpg_session = sanitize_text_field($active_dokan_details['code']);
            
            if ( $cart->has_discount( $dokan_coupon_code_from_rpg_session ) ) {
                $dokan_coupon_was_successfully_applied_by_wc = true;
                if (WC()->session) {
                    WC()->session->set('active_item_coupon', null);
                    WC()->session->set('active_cart_coupon', null);
                }
            } elseif ( is_cart() || is_checkout() ) { // ИЗМЕНЕНИЕ: Пытаемся применить только на страницах корзины/checkout
                error_log("[RPG DiscountManager] Attempting to apply Dokan coupon '{$dokan_coupon_code_from_rpg_session}' from RPG session to WC cart on cart/checkout page.");
                $applied_now = $cart->apply_coupon( $dokan_coupon_code_from_rpg_session );
                
                if ( $applied_now ) {
                    $dokan_coupon_was_successfully_applied_by_wc = true;
                    if (WC()->session) { 
                        WC()->session->set('active_item_coupon', null);
                        WC()->session->set('active_cart_coupon', null);
                    }
                    error_log("[RPG DiscountManager] Dokan coupon '{$dokan_coupon_code_from_rpg_session}' successfully applied to WC cart by DiscountManager.");
                } else {
                    // WooCommerce не смог применить купон. Сообщение об ошибке будет выведено самим WC.
                    // Купон остается "активным" в RPG-сессии.
                    error_log("[RPG DiscountManager] Dokan coupon '{$dokan_coupon_code_from_rpg_session}' from RPG session FAILED to apply to WC cart.");
                    // Мы не очищаем здесь 'active_rpg_dokan_coupon_details', чтобы он оставался "замороженным"
                    // Но если купон перестал существовать, CharacterPage его очистит при загрузке.
                }
            }
            // Если это не cart/checkout, мы не пытаемся применить Dokan купон из RPG сессии здесь,
            // он просто остается "выбранным" в RPG сессии.
        }
        
        // --- Шаг 3: Применяем RPG-купоны из инвентаря, ТОЛЬКО ЕСЛИ Dokan/WC купон НЕ был успешно применен ---
        if ( ! $dokan_coupon_was_successfully_applied_by_wc ) {
            if (WC()->session && $dokan_coupon_code_from_rpg_session && (is_cart() || is_checkout()) ) {
                // Если мы на cart/checkout, и Dokan купон был "выбран" в RPG, но не применился к WC,
                // то RPG купоны из инвентаря тоже не применяем (согласно предположению).
            } else {
                // Либо Dokan купон не был выбран в RPG сессии,
                // Либо мы не на cart/checkout (и Dokan купон не пытались применить к WC).
                // В этих случаях можно применять RPG купоны.
                $active_rpg_item_coupon = WC()->session ? WC()->session->get( 'active_item_coupon' ) : null;
                $active_rpg_cart_coupon = WC()->session ? WC()->session->get( 'active_cart_coupon' ) : null;
                if ($active_rpg_item_coupon || $active_rpg_cart_coupon) {
                    // Если активируем RPG купон, убедимся, что Dokan сессия RPG очищена
                    if (WC()->session && WC()->session->get('active_rpg_dokan_coupon_details')) {
                        WC()->session->set('active_rpg_dokan_coupon_details', null);
                    }
                }
                $this->apply_active_rpg_coupons( $cart, $user_id, $active_rpg_item_coupon, $active_rpg_cart_coupon ); 
            }
        } else {
            // Купон Dokan/WC успешно применен. Убедимся, что RPG-специфичные купоны точно не активны.
            if (WC()->session) {
                if (WC()->session->get('active_item_coupon')) WC()->session->set('active_item_coupon', null);
                if (WC()->session->get('active_cart_coupon')) WC()->session->set('active_cart_coupon', null);
            }
        }
	}

	private function apply_base_race_and_level_discounts( \WC_Cart $cart, $user_id, $race_slug, $level, $race_object ) {
		// Логика без изменений
		if ( $race_object instanceof Elf ) { 
			$race_object->apply_passive_cart_discount( $cart, $user_id );
		} elseif ( $race_object instanceof Orc ) { 
			$race_object->apply_passive_cart_discount( $cart, $user_id );
		}
		if ( 'dwarf' !== $race_slug ) {
			$discount_percent = min( $level, 5 ); 
			if ( $discount_percent > 0 ) {
                $subtotal_for_level_discount = 0;
                foreach ( $cart->get_cart() as $cart_item_key => $values ) {
                    $_product = $values['data'];
                    if ( $_product && $_product->exists() && $values['quantity'] > 0 ) {
                        $subtotal_for_level_discount += (float) $_product->get_price() * $values['quantity'];
                    }
                }
				if ( $subtotal_for_level_discount > 0 ) {
					$discount_amount = ( $subtotal_for_level_discount * $discount_percent ) / 100;
					if ( $discount_amount > 0 ) {
						$cart->add_fee( __( 'Общая скидка за уровень', 'woodmart-child' ) . ' (' . $discount_percent . '%)', - $discount_amount );
					}
				}
			}
		}
	}
	
	private function apply_dwarf_level_discount( \WC_Cart $cart, $user_id, $race_slug, $level, $race_object ) {
		// Логика без изменений
		if ( 'dwarf' !== $race_slug || ! ( $race_object instanceof Dwarf ) ) return;
		$discount_percentage = $race_object->get_level_based_discount_percentage( $level );
		if ( $discount_percentage > 0 ) {
            $base_subtotal_for_dwarf_discount = 0;
            foreach ($cart->get_cart() as $cart_item_key => $values) {
                $_product = $values['data'];
                 if ( $_product && $_product->exists() && $values['quantity'] > 0 ) {
                    $base_subtotal_for_dwarf_discount += (float) $_product->get_price() * $values['quantity'];
                }
            }
			if ( $base_subtotal_for_dwarf_discount > 0 ) {
				$discount_amount = ( $base_subtotal_for_dwarf_discount * $discount_percentage ) / 100;
				if ( $discount_amount > 0 ) {
					$cart->add_fee( __( 'Скидка Дварфа за уровень', 'woodmart-child' ) . ' (' . $discount_percentage . '%)', - $discount_amount );
				}
			}
		}
	}

	private function apply_active_rpg_coupons( \WC_Cart $cart, $user_id, $active_rpg_item_coupon, $active_rpg_cart_coupon ) {
		// Логика без изменений, но помним про TODO для условий RPG купонов
		if ( ! $active_rpg_item_coupon && ! $active_rpg_cart_coupon ) return;
		$subtotal_after_passive_bonuses = (float) $cart->get_subtotal(); 
        foreach ($cart->get_fees() as $fee) { 
            if (strpos($fee->name, __('Общая скидка за уровень', 'woodmart-child')) !== false || 
                strpos($fee->name, __('Скидка Дварфа за уровень', 'woodmart-child')) !== false ||
                strpos($fee->name, __('Пассивная скидка Эльфа', 'woodmart-child')) !== false || 
                strpos($fee->name, __('Пассивная скидка Орка', 'woodmart-child')) !== false ) {
                $subtotal_after_passive_bonuses += $fee->amount; 
            }
        }
        $subtotal_after_passive_bonuses = max(0, $subtotal_after_passive_bonuses);

		if ( $active_rpg_item_coupon && is_array( $active_rpg_item_coupon ) && isset( $active_rpg_item_coupon['value'] ) && $subtotal_after_passive_bonuses > 0 ) {
            $coupon_data = $active_rpg_item_coupon; 
            $apply_this_item_coupon = true;
            // TODO: Проверка условий RPG купона на товар/категорию
            if ($apply_this_item_coupon) {
                $cart_items = $cart->get_cart();
                if ( ! empty( $cart_items ) ) {
                    $first_item_key = array_key_first( $cart_items ); 
                    $_product = $cart_items[$first_item_key]['data'];
                    $item_price_for_rpg_coupon = (float) $_product->get_price(); 
                    $coupon_value = (float) $coupon_data['value'];
                    $discount_amount_for_one_item = ( $item_price_for_rpg_coupon * $coupon_value ) / 100;
                    if ( $discount_amount_for_one_item > 0 ) {
                        $discount_amount_for_one_item = min( $discount_amount_for_one_item, $item_price_for_rpg_coupon * $cart_items[$first_item_key]['quantity'] ); 
                        $cart->add_fee( $coupon_data['description'] ?: (__( 'Скидка по RPG купону на товар', 'woodmart-child' ) . ' (' . $coupon_value . '%)'), - $discount_amount_for_one_item );
                        $subtotal_after_passive_bonuses -= $discount_amount_for_one_item; 
                        $subtotal_after_passive_bonuses = max(0, $subtotal_after_passive_bonuses);
                    }
                }
            } else {
                 if (WC()->session) WC()->session->set('active_item_coupon', null); 
                 wc_add_notice(sprintf(__('RPG купон "%s" не подходит для товаров в вашей корзине и был деактивирован.', 'woodmart-child'), esc_html($coupon_data['description'] ?: __('на товар', 'woodmart-child'))), 'notice');
            }
		}
		if ( $active_rpg_cart_coupon && is_array( $active_rpg_cart_coupon ) && isset( $active_rpg_cart_coupon['value'] ) && $subtotal_after_passive_bonuses > 0 ) {
			$coupon_value = (float) $active_rpg_cart_coupon['value'];
			$discount_amount = ( $subtotal_after_passive_bonuses * $coupon_value ) / 100;
			if ( $discount_amount > 0 ) {
				$discount_amount = min( $discount_amount, $subtotal_after_passive_bonuses ); 
				$cart->add_fee( $active_rpg_cart_coupon['description'] ?: (__( 'Скидка по RPG купону на корзину', 'woodmart-child' ) . ' (' . $coupon_value . '%)'), - $discount_amount );
			}
		}
	}
}

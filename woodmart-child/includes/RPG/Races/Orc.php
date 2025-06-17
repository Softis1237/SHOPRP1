<?php
/**
 * Класс для расы Орк.
 *
 * @package WoodmartChildRPG\RPG\Races
 */

namespace WoodmartChildRPG\RPG\Races;

use WoodmartChildRPG\RPG\Race;
use WoodmartChildRPG\RPG\Character;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class Orc extends Race {

	public function __construct() {
		parent::__construct(
			'orc',
			__( 'Орк', 'woodmart-child' ),
			__( 'Сильные и выносливые, орки известны своей боевой яростью.', 'woodmart-child' )
		);
	}

	public function apply_level_up_bonus( $user_id, $new_level ) {
		// error_log("Orc user {$user_id} reached level {$new_level}. Specific level up bonuses can be added here.");
	}

	/**
	 * Получает описание пассивных бонусов для Орков.
	 * Логика из get_orc_bonuses() вашего файла orcbonuses.php.
	 *
	 * @param int $user_id ID пользователя.
	 * @return string Описание бонусов.
	 */
	public function get_passive_bonus_description( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return __( 'Бонусы орков применяются для авторизованных пользователей.', 'woodmart-child' );
		}

		$character_manager = new Character();
		$level             = $character_manager->get_level( $user_id );
		$level             = max( 1, min( $level, 5 ) );

		// Общая скидка за уровень (из discount-logic.php)
		$general_level_discount = min( $level, 5 );

		// Параметры скидки за количество
		$discount_per_item_map = array( 1 => 1.0, 2 => 1.5, 3 => 2.0, 4 => 2.5, 5 => 3.0 );
		$discount_per_item     = isset( $discount_per_item_map[ $level ] ) ? $discount_per_item_map[ $level ] : 1.0;
		$max_quantity_discount = $discount_per_item * 5;

		$description = sprintf(
			__( 'Общая скидка за уровень: %d%%. Скидка за количество товаров: %.1f%% за товар (максимум %.1f%%).', 'woodmart-child' ),
			$general_level_discount,
			$discount_per_item,
			$max_quantity_discount
		);

		// Описание способности "Ярость"
		$rage_bonus_text = sprintf(
			__( " + Способность 'Ярость': максимальная скидка (%.1f%%) на 1 товар раз в неделю", 'woodmart-child' ),
			$max_quantity_discount
		);
		$description .= $rage_bonus_text;

		return $description;
	}

	/**
	 * Применяет пассивные скидки Орка к корзине (скидка за количество и "Ярость").
	 * Логика из apply_orc_discount() вашего файла orcbonuses.php.
	 *
	 * @param \WC_Cart $cart Объект корзины.
	 * @param int      $user_id ID пользователя.
	 */
	public function apply_passive_cart_discount( \WC_Cart $cart, $user_id ) {
		$character_manager = new Character();
		$level             = $character_manager->get_level( $user_id );
		$level             = max( 1, min( $level, 5 ) );

		$discount_per_item_map     = array( 1 => 1.0, 2 => 1.5, 3 => 2.0, 4 => 2.5, 5 => 3.0 );
		$discount_per_item_percent = isset( $discount_per_item_map[ $level ] ) ? $discount_per_item_map[ $level ] : 1.0;
		$max_quantity_discount_percent = $discount_per_item_percent * 5;

		$rage_product_id = (int) $character_manager->get_meta( $user_id, 'rage_product' ); // Мета-поле для "Ярости"

		$total_quantity_discount_amount = 0;
		$total_rage_discount_amount     = 0;
		$cart_subtotal_ex_tax         = (float) $cart->subtotal_ex_tax; // Используем subtotal_ex_tax для консистентности
		$item_count                   = $cart->get_cart_contents_count();

		// 1. Скидка за количество товаров
		$current_quantity_discount_percent = min( $item_count * $discount_per_item_percent, $max_quantity_discount_percent );
		if ( $current_quantity_discount_percent > 0 && $cart_subtotal_ex_tax > 0 ) {
			$total_quantity_discount_amount = ( $cart_subtotal_ex_tax * $current_quantity_discount_percent ) / 100;
		}

		// 2. Скидка от "Ярости"
		if ( $rage_product_id > 0 ) {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( $cart_item['product_id'] == $rage_product_id ) {
					$_product   = $cart_item['data'];
					if ( $_product && $_product->exists() ) {
						$item_price                 = wc_get_price_excluding_tax( $_product );
						$rage_item_discount_amount  = ( $item_price * $max_quantity_discount_percent / 100 ) * $cart_item['quantity'];
						$total_rage_discount_amount += $rage_item_discount_amount;
						break; 
					}
				}
			}
		}

		if ( $total_quantity_discount_amount > 0 ) {
			$cart->add_fee( __( 'Скидка Орка за количество', 'woodmart-child' ) . ' (' . number_format_i18n( $current_quantity_discount_percent, 1 ) . '%)', - $total_quantity_discount_amount );
		}
		if ( $total_rage_discount_amount > 0 ) {
			$cart->add_fee( __( 'Скидка Орка "Ярость"', 'woodmart-child' ) . ' (' . number_format_i18n( $max_quantity_discount_percent, 1 ) . '%)', - $total_rage_discount_amount );
		}
	}

	/**
	 * Применяет активную способность расы Орк ("Ярость").
	 * Фактическое применение скидки происходит в apply_passive_cart_discount
	 * на основе мета-поля 'rage_product'.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param mixed $context Контекст применения.
	 */
	public function apply_ability( $user_id, $context = null ) {
		// Логика активации "Ярости" (установка флага rage_pending)
		// будет перенесена в AJAX-обработчик.
		// error_log("Orc 'Rage' ability initiated for user {$user_id}. User should now select an item in cart.");
	}
}

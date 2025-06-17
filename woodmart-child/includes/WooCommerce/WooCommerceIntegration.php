<?php
/**
 * Интеграция RPG с WooCommerce.
 *
 * @package WoodmartChildRPG\WooCommerce
 */

namespace WoodmartChildRPG\WooCommerce;

use WoodmartChildRPG\RPG\Character as RPGCharacter; // Используем псевдоним, чтобы избежать конфликта имен

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class WooCommerceIntegration {

	/**
	 * Экземпляр RPG Character manager.
	 *
	 * @var RPGCharacter
	 */
	private $character_manager;

	/**
	 * Конструктор.
	 *
	 * @param RPGCharacter $character_manager Экземпляр Character manager.
	 */
	public function __construct( RPGCharacter $character_manager ) {
		$this->character_manager = $character_manager;
	}

	/**
	 * Обрабатывает завершение заказа.
	 * Обновляет total_spent, добавляет опыт, золото и пересчитывает уровень.
	 * Выдает купоны Людям.
	 *
	 * @param int $order_id ID заказа.
	 */
	public function handle_order_completion( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			// error_log( "WoodmartChildRPG: Заказ $order_id не найден в handle_order_completion." );
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			// error_log( "WoodmartChildRPG: Заказ $order_id не привязан к пользователю." );
			return; // Заказ от гостя
		}

		$order_total = (float) $order->get_total();

		// 1. Обновляем total_spent
		$this->character_manager->add_total_spent( $user_id, $order_total );

		// 2. Добавляем опыт (1 к 1)
		$this->character_manager->add_experience( $user_id, $order_total );
		// error_log( "WoodmartChildRPG: Пользователь $user_id. Заказ $order_id. Добавлено $order_total опыта. Новый опыт: " . $this->character_manager->get_experience( $user_id ) );

		// 3. Начисляем золото (5 за каждый доллар)
		$gold_earned = intval( $order_total * 5 );
		$this->character_manager->add_gold( $user_id, $gold_earned );
		// error_log( "WoodmartChildRPG: Пользователь $user_id получил {$gold_earned} золота. Баланс теперь: " . $this->character_manager->get_gold( $user_id ) );

		// 4. Пересчет уровня уже происходит внутри add_experience через check_for_level_up.
		// error_log( "WoodmartChildRPG: Пользователь $user_id. Пересчитанный уровень: " . $this->character_manager->get_level( $user_id ) );


		// 5. Выдача эксклюзивных купонов для Людей 3+ уровня
		$user_race_slug = $this->character_manager->get_race( $user_id );
		$current_level  = $this->character_manager->get_level( $user_id );

		if ( 'human' === $user_race_slug && $current_level >= 3 ) {
			$last_exclusive_coupon_month = $this->character_manager->get_meta( $user_id, 'last_exclusive_coupon_month' );
			$current_month_year          = date( 'm-Y' );

			if ( $last_exclusive_coupon_month !== $current_month_year ) {
				$coupon_inventory = $this->character_manager->get_coupon_inventory( $user_id );

				// Предполагаем, что максимальный размер инвентаря - 9, как в вашем коде
				if ( count( $coupon_inventory ) < 9 ) { // Оставляем место хотя бы для двух купонов.
					$coupons_to_add = 0;
					if (count( $coupon_inventory ) < 8) $coupons_to_add = 2; // место для двух
					elseif (count( $coupon_inventory ) < 9) $coupons_to_add = 1; // место для одного
					
					if ($coupons_to_add >= 1) {
                        $coupon_inventory[] = array( 'type' => 'exclusive_item', 'value' => 25, 'description' => __('Эксклюзивный купон на товар (25%)', 'woodmart-child') );
                    }
                    if ($coupons_to_add >= 2) {
                        $coupon_inventory[] = array( 'type' => 'exclusive_cart', 'value' => 25, 'description' => __('Эксклюзивный купон на корзину (25%)', 'woodmart-child') );
                    }

					if ($coupons_to_add > 0) {
						$this->character_manager->update_coupon_inventory( $user_id, $coupon_inventory );
						$this->character_manager->update_meta( $user_id, 'last_exclusive_coupon_month', $current_month_year );
						// error_log( "WoodmartChildRPG: Пользователю $user_id (Human, Lvl $current_level) выданы эксклюзивные купоны." );
					}

				} else {
					// error_log( "WoodmartChildRPG: Инвентарь купонов переполнен для пользователя $user_id. Эксклюзивные купоны не выданы." );
				}
			}
		}
	}
}
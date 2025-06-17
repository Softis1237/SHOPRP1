<?php
/**
 * Класс с общими вспомогательными утилитами.
 *
 * @package WoodmartChildRPG\Core
 */

namespace WoodmartChildRPG\Core;

use WoodmartChildRPG\RPG\Character as RPGCharacter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class Utils {

	/**
	 * Проверяет, можно ли активировать еженедельную способность.
	 *
	 * @param int    $user_id  ID пользователя.
	 * @param string $meta_key_suffix Суффикс мета-ключа, хранящего дату последней активации (например, 'last_elf_activation').
	 * Префикс RPGCharacter::META_PREFIX будет добавлен автоматически Character менеджером.
	 * @return bool True, если можно активировать, иначе false.
	 */
	public static function can_activate_weekly_ability( $user_id, $meta_key_suffix ) {
		if ( ! $user_id || empty( $meta_key_suffix ) ) {
			return false;
		}
		// Для вызова get_meta нужен экземпляр Character или статический доступ, если бы он был.
		// Поскольку CharacterManager обычно инстанцируется, передавать его сюда или создавать новый - варианты.
		// Для простоты и учитывая, что CharacterManager уже инстанцирован в вызывающих классах,
		// лучше было бы передавать его экземпляр.
		// Но раз мы делаем статический Utils, создадим экземпляр Character здесь.
		// В более сложных системах это решается через Dependency Injection или Service Locator.
		$character_manager = new RPGCharacter();

		$last_activation_week_year = $character_manager->get_meta( $user_id, $meta_key_suffix );
		$current_week_year         = date( 'W-Y' );
		
		return ( $last_activation_week_year !== $current_week_year );
	}

	/**
	 * Получает иконку для типа купона.
	 * Ранее была в шаблоне character-page-content.php.
	 *
	 * @param string $type Тип купона.
	 * @return string CSS классы для иконки.
	 */
	public static function get_coupon_icon_class( $type ) {
		switch ( strtolower( (string) $type ) ) {
			case 'daily':
				return 'fas fa-calendar-day';
			case 'weekly':
				return 'fas fa-calendar-week';
			case 'exclusive_item':
				return 'fas fa-star';
			case 'exclusive_cart':
				return 'fas fa-shopping-cart';
			case 'common':
				return 'fas fa-tag';
			case 'fixed_cart': // Для WooCommerce купонов
				return 'fas fa-tags';
			case 'fixed_product': // Для WooCommerce купонов
				return 'fas fa-tag';
			case 'percent_product':
			case 'percent': // Для WooCommerce купонов
				return 'fas fa-percentage';
			default:
				return 'fas fa-ticket-alt';
		}
	}

	/**
	 * Получает иконку для расы.
	 * Ранее была в шаблоне character-page-content.php.
	 *
	 * @param string $race_slug Слаг расы.
	 * @return string CSS классы для иконки.
	 */
	public static function get_race_icon_class( $race_slug ) {
		$base_class = 'rpg-race-icon fas ';
		switch ( strtolower( (string) $race_slug ) ) {
			case 'human':
				return $base_class . 'fa-user';
			case 'elf':
				return $base_class . 'fa-leaf';
			case 'orc':
				return $base_class . 'fa-fist-raised';
			case 'dwarf':
				return $base_class . 'fa-gem';
			default:
				return $base_class . 'fa-question-circle';
		}
	}
}

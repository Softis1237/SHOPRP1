<?php
/**
 * Класс для расы Дварф.
 *
 * @package WoodmartChildRPG\RPG\Races
 */

namespace WoodmartChildRPG\RPG\Races;

use WoodmartChildRPG\RPG\Race;
use WoodmartChildRPG\RPG\Character; // Для доступа к уровню в get_passive_bonus_description

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class Dwarf extends Race {

	public function __construct() {
		parent::__construct(
			'dwarf',
			__( 'Дварф', 'woodmart-child' ),
			__( 'Искусные мастера и стойкие воины, дварфы славятся своим ремеслом и любовью к сокровищам.', 'woodmart-child' )
		);
	}

	/**
	 * Применяет бонусы при повышении уровня для Дварфов.
	 * На данный момент, основной бонус - это изменение процента скидки,
	 * который применяется в корзине, а не непосредственно при левел-апе.
	 * Можно добавить сюда начисление золота или другие специфичные бонусы.
	 *
	 * @param int $user_id  ID пользователя.
	 * @param int $new_level Новый уровень.
	 */
	public function apply_level_up_bonus( $user_id, $new_level ) {
		// error_log( "Dwarf user {$user_id} reached level {$new_level}. Specific level up bonuses can be added here." );
		// Например, можно начислить немного золота:
		// $character = new Character();
		// $character->add_gold($user_id, 50 * $new_level); // Пример: 50 золота за каждый новый уровень
	}

	/**
	 * Получает описание пассивных бонусов для Дварфов.
	 * Логика из get_dwarf_bonuses() вашего файла bonuses.php.
	 *
	 * @param int $user_id ID пользователя (необязателен здесь, но может понадобиться для других рас).
	 * @return string Описание бонусов.
	 */
	public function get_passive_bonus_description( $user_id = 0 ) { // <--- ОБНОВЛЕННЫЙ МЕТОД
		$level           = 1;
		$character_manager = new Character(); // Чтобы получить актуальный уровень

		if ( $user_id > 0 ) {
			$level = $character_manager->get_level( $user_id );
		} elseif ( get_current_user_id() > 0 ) {
			$level = $character_manager->get_level( get_current_user_id() );
		}
		$level = max( 1, min( $level, 10 ) ); // Уровень дварфа 1-10

		$discount_percentage = $this->get_level_based_discount_percentage( $level );

		$xp_bonus_text = __( 'Ускоренный набор опыта, собственная прогрессирующая скидка за уровень', 'woodmart-child' );

		return sprintf( '%s: %d%%.', $xp_bonus_text, $discount_percentage );
	}

	/**
	 * Применяет активную способность расы Дварф.
	 * Пока не определена, можно добавить позже.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param mixed $context Контекст применения.
	 */
	public function apply_ability( $user_id, $context = null ) {
		// TODO: Реализовать активную способность Дварфов, если она есть.
		// error_log("Dwarf ability applied for user {$user_id}");
	}

	/**
	 * Возвращает процент скидки для Дварфа на основе его уровня.
	 * Логика из apply_dwarf_discount() вашего файла bonuses.php.
	 *
	 * @param int $level Уровень Дварфа.
	 * @return int Процент скидки.
	 */
	public function get_level_based_discount_percentage( $level ) { // <--- НОВЫЙ МЕТОД
		$level = max( 1, min( (int) $level, 10 ) ); // Уровень дварфа 1-10

		$discount_map = array(
			1  => 2,
			2  => 4,
			3  => 6,
			4  => 8,
			5  => 10,
			6  => 11,
			7  => 12,
			8  => 13,
			9  => 14,
			10 => 15,
		);
		return isset( $discount_map[ $level ] ) ? $discount_map[ $level ] : 2; // Значение по умолчанию 2%
	}
}
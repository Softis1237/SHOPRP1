<?php
/**
 * Менеджер уровней и опыта.
 *
 * @package WoodmartChildRPG\RPG
 */

namespace WoodmartChildRPG\RPG;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class LevelManager {

	/**
	 * Пороги опыта для уровней (для не-дварфов).
	 * Ключ: уровень (начиная с 1).
	 * Значение: общий опыт, необходимый для достижения этого уровня.
	 * Данные из helpers.php/get_level_thresholds()
	 * @var array<int, int>
	 */
	private static $default_level_thresholds = array(
		1 => 0,    // XP для уровня 1
		2 => 100,  // XP для уровня 2
		3 => 200,  // XP для уровня 3
		4 => 300,  // XP для уровня 4
		5 => 400,  // XP для уровня 5
	);

	/**
	 * Максимальный уровень для не-дварфов.
	 * @var int
	 */
	private static $default_max_level = 5;

	/**
	 * Пороги опыта для уровней Дварфов.
	 * Данные из helpers.php/get_level_thresholds('dwarf')
	 * @var array<int, int>
	 */
	private static $dwarf_level_thresholds = array(
		1  => 0,    // XP для уровня 1
		2  => 100,  // XP для уровня 2
		3  => 200,  // XP для уровня 3
		4  => 300,  // XP для уровня 4
		5  => 400,  // XP для уровня 5
		6  => 500,  // XP для уровня 6
		7  => 600,  // XP для уровня 7
		8  => 700,  // XP для уровня 8
		9  => 800,  // XP для уровня 9
		10 => 900,  // XP для уровня 10
	);

	/**
	 * Максимальный уровень для Дварфов.
	 * @var int
	 */
	private static $dwarf_max_level = 10;


	/**
	 * Возвращает количество опыта, необходимое для достижения указанного уровня (для не-дварфов).
	 * @param int $level Уровень.
	 * @return int Количество опыта.
	 */
	public static function get_xp_for_level( $level ) {
		if ( isset( self::$default_level_thresholds[ $level ] ) ) {
			return self::$default_level_thresholds[ $level ];
		}
		// Если уровень выше максимального, возвращаем очень большое число или порог максимального
		if ( $level > self::$default_max_level && isset( self::$default_level_thresholds[ self::$default_max_level ] ) ) {
			return self::$default_level_thresholds[ self::$default_max_level ] + 1; // Или PHP_INT_MAX
		}
		return PHP_INT_MAX;
	}

	/**
	 * Возвращает максимальный уровень (для не-дварфов).
	 * @return int
	 */
	public static function get_max_level() {
		return self::$default_max_level;
	}

	/**
	 * Возвращает количество опыта, необходимое для достижения указанного уровня Дварфа.
	 * @param int $level Уровень дварфа.
	 * @return int Количество опыта.
	 */
	public static function get_xp_for_dwarf_level( $level ) {
		if ( isset( self::$dwarf_level_thresholds[ $level ] ) ) {
			return self::$dwarf_level_thresholds[ $level ];
		}
		if ( $level > self::$dwarf_max_level && isset( self::$dwarf_level_thresholds[ self::$dwarf_max_level ] ) ) {
			return self::$dwarf_level_thresholds[ self::$dwarf_max_level ] + 1; // Или PHP_INT_MAX
		}
		return PHP_INT_MAX;
	}

	/**
	 * Возвращает максимальный уровень для Дварфов.
	 * @return int
	 */
	public static function get_max_dwarf_level() {
		return self::$dwarf_max_level;
	}

	/**
	 * Рассчитывает уровень Дварфа на основе его опыта.
	 * @param int $user_id ID пользователя-дварфа.
	 * @return int Рассчитанный уровень.
	 */
	public static function calculate_dwarf_level( $user_id ) {
		$character_manager = new Character();
		$experience_points = $character_manager->get_experience( $user_id );
		$current_level     = 1;

		foreach ( self::$dwarf_level_thresholds as $level_num => $threshold ) {
			if ( $experience_points >= $threshold ) {
				$current_level = $level_num;
			} else {
				break;
			}
		}
		return min( $current_level, self::$dwarf_max_level );
	}
}

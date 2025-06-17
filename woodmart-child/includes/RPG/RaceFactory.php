<?php
/**
 * Фабрика для создания объектов Рас.
 *
 * @package WoodmartChildRPG\RPG
 */

namespace WoodmartChildRPG\RPG;

// Подключаем конкретные классы рас.
use WoodmartChildRPG\RPG\Races\Human;
use WoodmartChildRPG\RPG\Races\Elf;
use WoodmartChildRPG\RPG\Races\Orc;
use WoodmartChildRPG\RPG\Races\Dwarf;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

/**
 * Class RaceFactory
 * Позволяет создавать объекты рас по их идентификатору (слагу).
 */
class RaceFactory {

	/**
	 * Создает и возвращает объект расы по ее слагу.
	 *
	 * @param string $race_slug Слаг расы (например, 'human', 'elf').
	 * @return Race|null Объект расы или null, если раса не найдена.
	 */
	public static function create_race( $race_slug ) {
		switch ( $race_slug ) {
			case 'human':
				return new Human();
			case 'elf':
				return new Elf();
			case 'orc':
				return new Orc();
			case 'dwarf':
				return new Dwarf();
			default:
				// Можно возвращать расу по умолчанию или null/выбрасывать исключение.
				// error_log( "WoodmartChildRPG Фабрика Рас: Неизвестный слаг расы '{$race_slug}'." );
				return null;
		}
	}

	/**
	 * Получает массив всех доступных рас.
	 * Ключ - слаг, значение - отображаемое имя.
	 *
	 * @return array<string, string>
	 */
	public static function get_available_races() {
		return array(
			'human' => __( 'Человек', 'woodmart-child' ),
			'elf'   => __( 'Эльф', 'woodmart-child' ),
			'orc'   => __( 'Орк', 'woodmart-child' ),
			'dwarf' => __( 'Дварф', 'woodmart-child' ),
		);
	}
}
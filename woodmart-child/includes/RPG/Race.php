<?php
/**
 * Абстрактный класс для RPG Рас.
 *
 * @package WoodmartChildRPG\RPG
 */

namespace WoodmartChildRPG\RPG;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

/**
 * Abstract Class Race
 * Базовый класс для всех игровых рас.
 */
abstract class Race {

	/**
	 * Слаг расы (уникальный идентификатор).
	 * Например: 'human', 'elf', 'orc', 'dwarf'.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Отображаемое имя расы.
	 * Например: 'Человек', 'Эльф'.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Описание расы.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Конструктор.
	 *
	 * @param string $slug Слаг расы.
	 * @param string $name Имя расы.
	 * @param string $description Описание расы.
	 */
	public function __construct( $slug, $name, $description = '' ) {
		$this->slug        = $slug;
		$this->name        = $name;
		$this->description = $description;
	}

	/**
	 * Получает слаг расы.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Получает имя расы.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Получает описание расы.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Абстрактный метод для применения бонусов при повышении уровня.
	 * Должен быть реализован в каждом классе конкретной расы.
	 *
	 * @param int $user_id  ID пользователя.
	 * @param int $new_level Новый уровень.
	 * @return void
	 */
	abstract public function apply_level_up_bonus( $user_id, $new_level );

	/**
	 * Абстрактный метод для получения описания пассивных бонусов расы.
	 * Должен быть реализован в каждом классе конкретной расы.
	 *
	 * @return string Описание бонусов.
	 */
	abstract public function get_passive_bonus_description();

	/**
	 * Абстрактный метод для применения способностей расы.
	 * Например, при использовании в корзине.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param mixed $context Контекст применения (например, объект WC_Cart).
	 * @return void
	 */
	abstract public function apply_ability( $user_id, $context = null );

}

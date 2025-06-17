<?php
/**
 * Класс для регистрации хуков WordPress (actions и filters).
 *
 * @package WoodmartChildRPG\Core
 */

namespace WoodmartChildRPG\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

/**
 * Class Loader
 * Отвечает за регистрацию всех хуков (действий и фильтров) в WordPress.
 */
class Loader {

	/**
	 * Массив действий, зарегистрированных в WordPress.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $actions;

	/**
	 * Массив фильтров, зарегистрированных в WordPress.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $filters;

	/**
	 * Инициализация коллекций хуков.
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Добавляет новое действие в коллекцию для регистрации в WordPress.
	 *
	 * @param string $hook          Название WordPress хука.
	 * @param object $component     Ссылка на экземпляр объекта, на котором определен метод.
	 * @param string $callback      Название метода, который должен быть вызван.
	 * @param int    $priority      Опционально. Приоритет выполнения. По умолчанию 10.
	 * @param int    $accepted_args Опционально. Количество принимаемых аргументов. По умолчанию 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add_hook( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Добавляет новый фильтр в коллекцию для регистрации в WordPress.
	 *
	 * @param string $hook          Название WordPress хука.
	 * @param object $component     Ссылка на экземпляр объекта, на котором определен метод.
	 * @param string $callback      Название метода, который должен быть вызван.
	 * @param int    $priority      Опционально. Приоритет выполнения. По умолчанию 10.
	 * @param int    $accepted_args Опционально. Количество принимаемых аргументов. По умолчанию 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add_hook( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Вспомогательный метод для добавления хука (действия или фильтра) в коллекцию.
	 *
	 * @param array<int, array<string, mixed>> $hooks         Коллекция хуков.
	 * @param string                           $hook          Название WordPress хука.
	 * @param object                           $component     Ссылка на экземпляр объекта.
	 * @param string                           $callback      Название метода.
	 * @param int                              $priority      Приоритет.
	 * @param int                              $accepted_args Количество аргументов.
	 * @return array<int, array<string, mixed>> Обновленная коллекция хуков.
	 */
	private function add_hook( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return $hooks;
	}

	/**
	 * Регистрирует все фильтры и действия в WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}

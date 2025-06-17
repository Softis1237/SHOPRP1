<?php
/**
 * Woodmart Child Theme Functions and Definitions
 *
 * @package woodmart-child
 */

// Запрещаем прямой доступ.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Определяем константу для пути к директории дочерней темы.
 */
if ( ! defined( 'WOODMART_CHILD_RPG_DIR_PATH' ) ) {
	define( 'WOODMART_CHILD_RPG_DIR_PATH', trailingslashit( get_stylesheet_directory() ) );
}

/**
 * Определяем константу для URL к директории дочерней темы.
 */
if ( ! defined( 'WOODMART_CHILD_RPG_DIR_URI' ) ) {
	define( 'WOODMART_CHILD_RPG_DIR_URI', trailingslashit( get_stylesheet_directory_uri() ) );
}

// 1. Подключаем автозагрузчик классов.
$wcrpg_autoloader_path = WOODMART_CHILD_RPG_DIR_PATH . 'includes/autoload.php';
if ( file_exists( $wcrpg_autoloader_path ) ) {
       require_once $wcrpg_autoloader_path;
       if ( defined( 'WP_CLI' ) && WP_CLI ) {
               require_once WOODMART_CHILD_RPG_DIR_PATH . 'includes/CLI/AbilityCheckCommand.php';
       }
} else {
	if ( is_admin() ) {
		add_action(
			'admin_notices',
			function() {
				?>
				<div class="notice notice-error is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Woodmart Child RPG Theme Error:', 'woodmart-child' ); ?></strong>
						<?php esc_html_e( 'Autoloader file not found at includes/autoload.php. The RPG system will not work.', 'woodmart-child' ); ?>
					</p>
				</div>
				<?php
			}
		);
	}
	return; // Прерываем выполнение, если автозагрузчик не найден.
}

// 2. Хуки активации/деактивации темы для Installer.
if ( class_exists( 'WoodmartChildRPG\\Core\\Installer' ) ) {
	register_activation_hook( __FILE__, array( 'WoodmartChildRPG\\Core\\Installer', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'WoodmartChildRPG\\Core\\Installer', 'deactivate' ) );
} else {
	// Уведомление, если класс Installer не найден (может случиться, если автозагрузчик не сработал)
	if ( is_admin() ) {
		add_action(
			'admin_notices',
			function() {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Woodmart Child RPG Theme Warning:', 'woodmart-child' ); ?></strong>
						<?php esc_html_e( 'Installer class not found. Theme activation/deactivation hooks might not run.', 'woodmart-child' ); ?>
					</p>
				</div>
				<?php
			}
		);
	}
}

// 3. Подключаем стили родительской и дочерней темы.
// Эту функцию можно оставить здесь или перенести в AssetManager, если требуется более сложная логика.
if ( ! function_exists( 'wcrpg_enqueue_theme_styles' ) ) {
	/**
	 * Enqueue parent and child theme stylesheets.
	 */
	function wcrpg_enqueue_theme_styles() {
		// Подключение стилей родительской темы.
		wp_enqueue_style(
			'woodmart-parent-style',
			get_template_directory_uri() . '/style.css',
			array(),
			woodmart_get_theme_info( 'Version' )
		);

		// Подключение стилей дочерней темы.
		wp_enqueue_style(
			'woodmart-child-style',
			get_stylesheet_uri(), // Это подключит style.css из корня дочерней темы.
			array( 'woodmart-parent-style' ),
			wp_get_theme()->get( 'Version' )
		);
	}
	add_action( 'wp_enqueue_scripts', 'wcrpg_enqueue_theme_styles', 10 ); // Стандартный приоритет для стилей
}


// 4. Инициализируем и запускаем нашу RPG тему.
if ( class_exists( 'WoodmartChildRPG\\Core\\Theme' ) ) {
	/**
	 * Основная функция для запуска RPG темы.
	 */
	function woodmart_child_rpg_run_theme() {
		// Получаем экземпляр и запускаем
		$theme_instance = WoodmartChildRPG\Core\Theme::get_instance();
		$theme_instance->run();
	}
	// Запускаем после базовой настройки темы WordPress, но до большинства плагинов.
	add_action( 'after_setup_theme', 'woodmart_child_rpg_run_theme', 5 );
} else {
	// Уведомление, если основной класс темы не найден.
	if ( is_admin() ) {
		add_action(
			'admin_notices',
			function() {
				?>
				<div class="notice notice-error is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Woodmart Child RPG Theme Error:', 'woodmart-child' ); ?></strong>
						<?php esc_html_e( 'Main theme class WoodmartChildRPG\\Core\\Theme not found. The RPG system will not work.', 'woodmart-child' ); ?>
					</p>
				</div>
				<?php
			}
		);
	}
}



// Все require_once для файлов из папки /inc/ должны быть УДАЛЕНЫ отсюда,
// так как их логика перенесена в ООП классы и загружается через автозагрузчик.
// Старые процедурные функции, если они где-то остались, также должны быть удалены или перенесены.
?>
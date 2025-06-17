<?php
/**
 * PSR-4 Autoloader
 *
 * @package WoodmartChildRPG
 */

spl_autoload_register(
	function ( $class ) {
		// Базовое пространство имен проекта.
		$prefix = 'WoodmartChildRPG\\';

		// Директория, где лежат классы, относительно этого файла.
		// Так как autoload.php находится в includes/, а классы в поддиректориях includes/,
		// то базовая директория для классов - это текущая директория autoload.php.
		$base_dir = __DIR__ . '/';

		// Проверяем, принадлежит ли класс этому пространству имен.
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			// Нет, переходим к следующему зарегистрированному автозагрузчику.
			return;
		}

		// Получаем относительное имя класса.
		$relative_class = substr( $class, $len );

		// Формируем путь к файлу:
		// Заменяем разделители пространства имен на разделители директорий,
		// добавляем .php в конце.
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// Если файл существует, подключаем его.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
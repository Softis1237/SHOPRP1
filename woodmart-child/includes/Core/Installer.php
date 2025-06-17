<?php
/**
 * Установщик темы: создание таблиц и другие задачи при активации.
 *
 * @package WoodmartChildRPG\Core
 */

namespace WoodmartChildRPG\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class Installer {

	/**
	 * Выполняется при активации темы.
	 */
	public static function activate() {
		error_log( 'WoodmartChildRPG Installer: Activate hook called.' ); // DEBUG
		self::create_dokan_user_coupons_table();
		flush_rewrite_rules(); // Важно после добавления эндпоинтов
		error_log( 'WoodmartChildRPG Installer: Activation complete.' ); // DEBUG
	}

	/**
	 * Выполняется при деактивации темы (опционально).
	 */
	public static function deactivate() {
		error_log( 'WoodmartChildRPG Installer: Deactivate hook called.' ); // DEBUG
		wp_clear_scheduled_hook( 'wcrpg_assign_elf_items_weekly_event' );
		flush_rewrite_rules();
		error_log( 'WoodmartChildRPG Installer: Deactivation complete.' ); // DEBUG
	}

	/**
	 * Создает или обновляет кастомную таблицу для отслеживаемых купонов Dokan.
	 */
	private static function create_dokan_user_coupons_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'rpg_user_dokan_coupons';
		$charset_collate = $wpdb->get_charset_collate();

		error_log( "WoodmartChildRPG Installer: Attempting to create/update table '{$table_name}'." ); // DEBUG

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            coupon_id BIGINT UNSIGNED NOT NULL,
            vendor_id BIGINT UNSIGNED NOT NULL,
            original_code VARCHAR(255) NOT NULL,
            added_timestamp INT UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY coupon_id (coupon_id),
            KEY vendor_id (vendor_id),
            KEY added_timestamp (added_timestamp),
            UNIQUE KEY user_coupon (user_id, coupon_id)
        ) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		
		error_log( "WoodmartChildRPG Installer: SQL for table creation: " . $sql ); // DEBUG
		$dbdelta_results = dbDelta( $sql );
		error_log( "WoodmartChildRPG Installer: dbDelta results for {$table_name}: " . print_r( $dbdelta_results, true ) ); // DEBUG

		// Проверка существования таблицы после попытки создания
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
		if ( $table_exists ) {
			error_log( "WoodmartChildRPG Installer: Table '{$table_name}' checked/created successfully." );
		} else {
			error_log( "WoodmartChildRPG Installer: FAILED to create/verify table '{$table_name}'. Last DB error: " . $wpdb->last_error );
		}
	}
}
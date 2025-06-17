<?php
/**
 * Обработчик AJAX-запросов для административной части RPG системы.
 *
 * @package WoodmartChildRPG\Admin
 */

namespace WoodmartChildRPG\Admin;

use WoodmartChildRPG\RPG\Character as RPGCharacter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class AdminAJAXHandler {

	/**
	 * Экземпляр RPG Character manager.
	 * @var RPGCharacter
	 */
	private $character_manager;

	/**
	 * Конструктор.
	 * @param RPGCharacter $character_manager Экземпляр Character manager.
	 */
	public function __construct( RPGCharacter $character_manager ) {
		$this->character_manager = $character_manager;
	}

	/**
	 * Обрабатывает добавление RPG купона пользователю администратором.
	 * (Аналог rpg_handle_admin_add_coupon_ajax)
	 */
	public function handle_admin_add_rpg_coupon() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'woodmart-child' ) ) );
		}
		check_ajax_referer( 'rpg_admin_ajax_nonce', '_ajax_nonce' );

		$user_id      = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$coupon_type  = isset( $_POST['coupon_type'] ) ? sanitize_text_field( $_POST['coupon_type'] ) : '';
		$coupon_value = isset( $_POST['coupon_value'] ) ? intval( $_POST['coupon_value'] ) : 0;

		if ( ! $user_id || empty( $coupon_type ) || $coupon_value <= 0 || $coupon_value > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Некорректные данные RPG купона.', 'woodmart-child' ) ) );
		}
		// Расширьте этот список при необходимости
		$valid_types = array( 'common', 'daily', 'weekly', 'exclusive_item', 'exclusive_cart' );
		if ( ! in_array( $coupon_type, $valid_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Недопустимый тип RPG купона.', 'woodmart-child' ) ) );
		}

		$coupon_data = array(
			'type'        => $coupon_type,
			'value'       => $coupon_value,
			'description' => sprintf( __( 'Купон %s на %d%% (выдан админом)', 'woodmart-child' ), $coupon_type, $coupon_value ),
			// Можно добавить дату выдачи или другие поля
		);

		// Предполагаемый лимит инвентаря, можно вынести в настройки
		$inventory_limit = apply_filters( 'wcrpg_coupons_inventory_limit', 10 );

		if ( $this->character_manager->add_rpg_coupon_to_inventory( $user_id, $coupon_data, $inventory_limit ) ) {
			wp_send_json_success( array( 'message' => __( 'RPG купон успешно добавлен.', 'woodmart-child' ) ) );
		} else {
			// add_rpg_coupon_to_inventory должен вернуть false, если инвентарь полон
			$inventory = $this->character_manager->get_coupon_inventory( $user_id );
			if ( count( $inventory ) >= $inventory_limit ) {
				wp_send_json_error( array( 'message' => __( 'Инвентарь RPG купонов пользователя полон.', 'woodmart-child' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Ошибка при добавлении RPG купона.', 'woodmart-child' ) ) );
			}
		}
	}

	/**
	 * Обрабатывает удаление RPG купона у пользователя администратором.
	 * (Аналог rpg_handle_admin_delete_coupon_ajax)
	 */
	public function handle_admin_delete_rpg_coupon() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'woodmart-child' ) ) );
		}
		check_ajax_referer( 'rpg_admin_ajax_nonce', '_ajax_nonce' );

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$index   = isset( $_POST['coupon_index'] ) ? intval( $_POST['coupon_index'] ) : -1;

		if ( ! $user_id || $index < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Некорректные данные.', 'woodmart-child' ) ) );
		}

		$coupons = $this->character_manager->get_coupon_inventory( $user_id );
		if ( ! is_array( $coupons ) || ! isset( $coupons[ $index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'RPG купон с таким индексом не найден.', 'woodmart-child' ) ) );
		}

		unset( $coupons[ $index ] );
		// Переиндексируем массив
		$updated_coupons = array_values( $coupons );

		if ( $this->character_manager->update_coupon_inventory( $user_id, $updated_coupons ) ) {
			wp_send_json_success( array( 'message' => __( 'RPG купон успешно удален.', 'woodmart-child' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Ошибка при удалении RPG купона.', 'woodmart-child' ) ) );
		}
	}

	/**
	 * Обрабатывает сброс кулдауна способности пользователя администратором.
	 * (Аналог rpg_handle_admin_reset_ability_ajax)
	 */
	public function handle_admin_reset_ability_cooldown() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'woodmart-child' ) ) );
		}
		check_ajax_referer( 'rpg_admin_ajax_nonce', '_ajax_nonce' );

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$ability = isset( $_POST['ability'] ) ? sanitize_key( $_POST['ability'] ) : '';

		if ( ! $user_id || ! in_array( $ability, array( 'sense', 'rage' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Некорректные данные.', 'woodmart-child' ) ) );
		}

		$meta_key_last_activation = ( 'sense' === $ability ) ? 'last_elf_activation' : 'last_rage_activation';
		$meta_key_pending         = ( 'sense' === $ability ) ? 'elf_sense_pending' : 'rage_pending';
		$meta_key_product_choice  = ( 'sense' === $ability ) ? 'elf_items' : 'rage_product';

		$this->character_manager->delete_meta( $user_id, $meta_key_last_activation );
		$this->character_manager->delete_meta( $user_id, $meta_key_pending );
		$this->character_manager->delete_meta( $user_id, $meta_key_product_choice );

		wp_send_json_success( array( 'message' => __( 'Кулдаун способности успешно сброшен.', 'woodmart-child' ) ) );
	}
}
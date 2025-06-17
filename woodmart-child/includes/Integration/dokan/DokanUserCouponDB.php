<?php
/**
 * Файл: woodmart-child/includes/Integration/dokan/DokanUserCouponDB.php
 * Управляет взаимодействием с базой данных для пользовательских купонов Dokan.
 */

namespace WoodmartChildRPG\Integration\Dokan;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class DokanUserCouponDB {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'rpg_user_dokan_coupons';
	}

	public function add_coupon_to_inventory( $user_id, $coupon_id, $vendor_id, $original_code, $limit = 20 ) {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
				$user_id,
				$coupon_id
			)
		);
		if ( $exists ) {
			return new \WP_Error( 'dokan_coupon_already_in_inventory', __( 'Этот купон продавца уже есть в вашем инвентаре.', 'woodmart-child' ) );
		}

		$current_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table_name} WHERE user_id = %d",
				$user_id
			)
		);
		if ( $current_count >= $limit ) {
			return new \WP_Error( 'dokan_inventory_full', __( 'Инвентарь купонов продавцов полон.', 'woodmart-child' ) );
		}

		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'         => $user_id,
				'coupon_id'       => $coupon_id,
				'vendor_id'       => $vendor_id,
				'original_code'   => $original_code,
				'added_timestamp' => current_time( 'timestamp' ),
			),
			array( '%d', '%d', '%d', '%s', '%d' )
		);

		if ( ! $inserted ) {
			error_log( "WoodmartChildRPG DB Error: Failed to insert Dokan coupon for user {$user_id}, coupon {$coupon_id}. DB Error: " . $wpdb->last_error );
			return new \WP_Error( 'dokan_coupon_add_db_error', __( 'Не удалось добавить купон продавца в инвентарь (ошибка БД).', 'woodmart-child' ) );
		}
		return true;
	}

	public function user_has_coupon( $user_id, $coupon_id ) {
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
				$user_id,
				$coupon_id
			)
		);
		return $count > 0;
	}

	/**
	 * Удаляет купон Dokan из инвентаря пользователя.
	 *
	 * @param int $user_id ID пользователя. Если 0, удаляет купон для всех пользователей.
	 * @param int $coupon_id ID купона.
	 * @return bool True если удалено хотя бы одна строка, иначе false.
	 */
	public function remove_coupon_from_inventory( $user_id, $coupon_id ) {
		global $wpdb;
		
		$where = array( 'coupon_id' => $coupon_id );
		$where_format = array( '%d' );

		if ( $user_id > 0 ) { // Если указан конкретный пользователь
			$where['user_id'] = $user_id;
			$where_format[] = '%d';
		}
        // Если $user_id = 0, то удаляем купон для всех пользователей (полезно, если сам пост купона удален)

		$deleted_rows = $wpdb->delete(
			$this->table_name,
			$where,
			$where_format
		);

		if ( false === $deleted_rows ) {
			error_log( "WoodmartChildRPG DB Error: Failed to delete Dokan coupon. User ID: {$user_id}, Coupon ID: {$coupon_id}. DB Error: " . $wpdb->last_error );
			return false; 
		}
		
		// error_log( "WoodmartChildRPG DB Log: Attempted to delete Dokan coupon. User ID: {$user_id}, Coupon ID: {$coupon_id}. Rows affected: {$deleted_rows}" );
		return $deleted_rows > 0; 
	}

	public function get_user_coupons( $user_id, $vendor_id_filter = 0, $per_page = 10, $current_page = 1 ) {
		global $wpdb;
		$offset = ( $current_page - 1 ) * $per_page;

		$where_clauses = array( "user_id = %d" );
		$query_params  = array( $user_id );

		if ( $vendor_id_filter > 0 ) {
			$where_clauses[] = "vendor_id = %d";
			$query_params[]  = $vendor_id_filter;
		}
		$where_sql = implode( " AND ", $where_clauses );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT coupon_id, vendor_id, original_code, added_timestamp FROM {$this->table_name} WHERE {$where_sql} ORDER BY added_timestamp DESC LIMIT %d OFFSET %d", // Добавил added_timestamp
				array_merge( $query_params, array( $per_page, $offset ) )
			)
		);
	}

	public function get_user_coupons_count( $user_id, $vendor_id_filter = 0 ) {
		global $wpdb;
		$where_clauses = array( "user_id = %d" );
		$query_params  = array( $user_id );

		if ( $vendor_id_filter > 0 ) {
			$where_clauses[] = "vendor_id = %d";
			$query_params[]  = $vendor_id_filter;
		}
		$where_sql = implode( " AND ", $where_clauses );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->table_name} WHERE {$where_sql}",
				$query_params
			)
		);
	}
    
	public function get_all_user_coupons_for_status_check( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT coupon_id, original_code FROM {$this->table_name} WHERE user_id = %d",
				$user_id
			)
		);
	}

	public function get_specific_user_coupon_data( $user_id, $coupon_id ) { 
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT coupon_id, vendor_id, original_code, added_timestamp FROM {$this->table_name} WHERE user_id = %d AND coupon_id = %d",
				$user_id,
				$coupon_id
			)
		);
	}
}

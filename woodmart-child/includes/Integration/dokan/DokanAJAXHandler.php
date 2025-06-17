<?php
/**
 * Файл: woodmart-child/includes/Integration/dokan/DokanAJAXHandler.php
 * Обработчик AJAX-запросов для интеграции с Dokan.
 */

namespace WoodmartChildRPG\Integration\Dokan;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\Integration\Dokan\DokanUserCouponDB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class DokanAJAXHandler {

	private $character_manager; 
	private $dokan_coupon_db;   

	public function __construct( RPGCharacter $character_manager, DokanUserCouponDB $dokan_coupon_db ) { 
		$this->character_manager = $character_manager;
		$this->dokan_coupon_db   = $dokan_coupon_db; 
	}

    public function handle_woocommerce_removed_coupon( $coupon_code ) {
        if ( WC()->session ) {
            $active_dokan_details = WC()->session->get( 'active_rpg_dokan_coupon_details' );
            if ( ! empty( $active_dokan_details ) && isset( $active_dokan_details['code'] ) && $active_dokan_details['code'] === $coupon_code ) {
                WC()->session->set( 'active_rpg_dokan_coupon_details', null );
                error_log("WoodmartChildRPG Dokan AJAX: Cleared RPG active Dokan coupon session for {$coupon_code} because it was removed by WC.");
            }
        }
    }
    
    // --- Методы для применения купона из кастомной формы (в корзине или checkout) ---
    public function handle_apply_dokan_coupon_in_cart() {
        check_ajax_referer( 'rpg_cart_ajax_nonce', '_ajax_nonce' ); 
        $this->process_rpg_coupon_activation_attempt_direct(); // Используем метод с немедленным применением к WC()->cart
    }

    public function handle_apply_dokan_coupon_on_checkout() {
        check_ajax_referer( 'rpg_checkout_ajax_nonce', '_ajax_nonce' );
        $this->process_rpg_coupon_activation_attempt_direct(); // Используем метод с немедленным применением к WC()->cart
    }
    
    /**
     * Активация купона Dokan из инвентаря (страница персонажа).
     * Просто записывает купон в RPG-сессию, НЕ применяет к корзине WC сразу.
     * Очищает активные RPG-специфичные купоны.
     */
    public function handle_activate_dokan_coupon_from_inventory() {
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' ); 

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();

		$coupon_id_to_activate = isset( $_POST['dokan_coupon_id_to_activate'] ) ? intval( $_POST['dokan_coupon_id_to_activate'] ) : 0;
		if ( $coupon_id_to_activate <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Неверный ID купона продавца для активации.', 'woodmart-child' ) ) );
		}

		if ( ! $this->dokan_coupon_db->user_has_coupon( $user_id, $coupon_id_to_activate ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Купон продавца не найден в вашем инвентаре.', 'woodmart-child' ) ) );
		}

		$wc_coupon = new \WC_Coupon( $coupon_id_to_activate );
		if ( ! $wc_coupon->get_id() ) { 
            $removed_ids_activate = [];
			if ($this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id_to_activate )) {
                $removed_ids_activate[] = $coupon_id_to_activate;
            }
			wp_send_json_error( array( 
                'message' => esc_html__( 'Этот купон больше не действителен и был удален из вашего инвентаря.', 'woodmart-child' ),
                'removed_coupon_ids' => $removed_ids_activate 
            ) );
		}

		$stored_coupon_data = $this->dokan_coupon_db->get_specific_user_coupon_data( $user_id, $coupon_id_to_activate );
		if ( $stored_coupon_data && isset( $stored_coupon_data->original_code ) && $wc_coupon->get_code() !== $stored_coupon_data->original_code ) {
            $removed_ids_code_changed = [];
			if ($this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id_to_activate )) {
                $removed_ids_code_changed[] = $coupon_id_to_activate;
            }
			wp_send_json_error( array( 
                'message' => esc_html__( 'Код этого купона был изменен продавцом. Купон удален из вашего инвентаря.', 'woodmart-child' ),
                'removed_coupon_ids' => $removed_ids_code_changed
            ) );
		}
        
        // Проверка конфликтов с другими активными RPG-купонами
        if ( (WC()->session && WC()->session->get( 'active_item_coupon' )) || (WC()->session && WC()->session->get( 'active_cart_coupon' )) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'У вас уже активирован RPG купон. Пожалуйста, сначала деактивируйте его, чтобы выбрать купон продавца.', 'woodmart-child' ) ) );
        }
        
		// Просто записываем в RPG-сессию. DiscountManager попытается его применить в корзине/checkout.
		if ( WC()->session ) {
			WC()->session->set(
				'active_rpg_dokan_coupon_details',
				array(
					'id'   => $wc_coupon->get_id(),
					'code' => $wc_coupon->get_code(),
				)
			);
            // Очищаем активные RPG-специфичные купоны, так как выбрали Dokan купон
            WC()->session->set( 'active_item_coupon', null );
            WC()->session->set( 'active_cart_coupon', null );
            error_log("[RPG DokanAJAXHandler] User {$user_id} selected Dokan coupon {$wc_coupon->get_code()} (ID: {$wc_coupon->get_id()}) from inventory. Set to RPG session.");
		} else {
			wp_send_json_error( array( 'message' => __( 'Ошибка сессии WooCommerce.', 'woodmart-child' ) ) );
		}

		wp_send_json_success(
			array(
				'message'     => sprintf( esc_html__( 'Купон продавца "%s" выбран. Он будет применен в корзине, если соответствует условиям.', 'woodmart-child' ), esc_html( $wc_coupon->get_code() ) ),
				'coupon_code' => $wc_coupon->get_code(),
                'reload_page' => true // Перезагружаем страницу персонажа, чтобы обновить UI
			)
		);
	}

    /**
     * Общая логика для применения купона Dokan (из корзины/checkout),
     * которая включает немедленную попытку применения к WC()->cart.
     */
    private function process_rpg_coupon_activation_attempt_direct() {
        // Эта логика остается такой же, как в артефакте dokan_ajax_handler_checkout (из предыдущего ответа)
        // Она уже пытается применить купон к WC()->cart и записывает в RPG сессию только в случае успеха.
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему, чтобы применить купон.', 'woodmart-child' ) ) );
        }
        $user_id = get_current_user_id();
        $coupon_code_entered = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
        if ( empty( $coupon_code_entered ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, введите код купона.', 'woodmart-child' ) ) );
        }
        $wc_coupon = new \WC_Coupon( $coupon_code_entered );
        if ( ! $wc_coupon->get_id() ) {
            wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Купон с кодом "%s" не найден.', 'woodmart-child' ), esc_html( $coupon_code_entered ) ) ) );
        }
        
        if ( (WC()->session && WC()->session->get( 'active_item_coupon' )) || (WC()->session && WC()->session->get( 'active_cart_coupon' )) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'У вас уже активирован RPG купон. Нельзя активировать купон продавца одновременно.', 'woodmart-child' ) ) );
        }
        $active_rpg_dokan_details = WC()->session ? WC()->session->get( 'active_rpg_dokan_coupon_details' ) : null;
        if ( $active_rpg_dokan_details && isset($active_rpg_dokan_details['code']) && $active_rpg_dokan_details['code'] !== $wc_coupon->get_code() ) {
            wp_send_json_error( array( 'message' => esc_html__( 'У вас уже активирован другой купон продавца. Пожалуйста, сначала деактивируйте его.', 'woodmart-child' ) ) );
        }

        $added_to_inventory_now = false;
        if ( ! $this->dokan_coupon_db->user_has_coupon( $user_id, $wc_coupon->get_id() ) ) {
            $vendor_id = $wc_coupon->get_meta( 'dokan_coupon_author', true );
            if ( ! $vendor_id ) {
                $coupon_post = get_post( $wc_coupon->get_id() );
                if ( $coupon_post ) $vendor_id = $coupon_post->post_author;
            }
            $inventory_add_status = $this->dokan_coupon_db->add_coupon_to_inventory( $user_id, $wc_coupon->get_id(), (int) $vendor_id, $wc_coupon->get_code() );
            if ( is_wp_error( $inventory_add_status ) ) {
                 wp_send_json_error( array( 'message' => $inventory_add_status->get_error_message() ) );
            }
            $added_to_inventory_now = true;
        }

        if ( ! WC()->cart ) {
             wp_send_json_error( array( 'message' => __( 'Ошибка: Корзина не инициализирована.', 'woodmart-child' ) ) );
        }
        
        if ( WC()->cart->has_discount( $wc_coupon->get_code() ) ) {
            if (WC()->session) {
                WC()->session->set('active_rpg_dokan_coupon_details', array('id' => $wc_coupon->get_id(), 'code' => $wc_coupon->get_code()));
                WC()->session->set('active_item_coupon', null); WC()->session->set('active_cart_coupon', null);
            }
            $message = $added_to_inventory_now 
                ? sprintf( esc_html__( 'Купон "%s" добавлен в инвентарь и уже был применен к корзине.', 'woodmart-child' ), esc_html( $wc_coupon->get_code() ) )
                : sprintf( esc_html__( 'Купон "%s" уже применен к вашей корзине.', 'woodmart-child' ), esc_html( $wc_coupon->get_code() ) );
            wp_send_json_success( array( 'message' => $message, 'coupon_code_applied' => $wc_coupon->get_code() ) );
        }

        $applied_successfully = WC()->cart->apply_coupon( $wc_coupon->get_code() );
            
        if ( ! $applied_successfully ) {
            $notices = wc_get_notices('error');
            $error_message_from_wc = esc_html__( 'Не удалось применить купон.', 'woodmart-child' );
            if (!empty($notices)) {
                $last_notice = end($notices);
                if (isset($last_notice['notice'])) $error_message_from_wc = wp_strip_all_tags($last_notice['notice']);
                wc_clear_notices(); 
            }
            wp_send_json_error( array( 'message' => $error_message_from_wc ) );
        }

        if ( WC()->session ) {
            WC()->session->set('active_rpg_dokan_coupon_details', array('id' => $wc_coupon->get_id(), 'code' => $wc_coupon->get_code()));
            WC()->session->set('active_item_coupon', null); 
            WC()->session->set('active_cart_coupon', null);
        } else {
             wp_send_json_error( array( 'message' => __( 'Ошибка сессии WooCommerce.', 'woodmart-child' ) ) );
        }

        $message = $added_to_inventory_now 
            ? sprintf( esc_html__( 'Купон "%s" добавлен в инвентарь и успешно применен!', 'woodmart-child' ), esc_html( $wc_coupon->get_code() ) )
            : sprintf( esc_html__( 'Купон "%s" успешно применен!', 'woodmart-child' ), esc_html( $wc_coupon->get_code() ) );

        wp_send_json_success( array( 
            'message' => $message,
            'coupon_code_applied' => $wc_coupon->get_code() 
        ) );
    }
    
    // Остальные методы (handle_take_dokan_coupon, handle_add_dokan_coupon_by_code, 
    // handle_refresh_dokan_coupons_status, handle_clear_invalid_dokan_coupons)
    // остаются такими же, как в артефакте dokan_ajax_handler_checkout.
    public function handle_take_dokan_coupon() { 
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему, чтобы взять купон.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();
		$dokan_coupon_id = isset( $_POST['coupon_id'] ) ? intval( $_POST['coupon_id'] ) : 0;
		if ( ! $dokan_coupon_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Неверный ID купона.', 'woodmart-child' ) ) );
		}
		$coupon_obj = new \WC_Coupon( $dokan_coupon_id );
		if ( ! $coupon_obj->get_id() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Не удалось получить данные купона.', 'woodmart-child' ) ) );
		}
		$current_coupon_code = $coupon_obj->get_code();
		$vendor_id = $coupon_obj->get_meta( 'dokan_coupon_author', true ); 
        if ( ! $vendor_id && $coupon_obj->get_id() ) { 
            $coupon_post = get_post( $coupon_obj->get_id() );
            if ( $coupon_post ) $vendor_id = $coupon_post->post_author;
        }
		$result = $this->dokan_coupon_db->add_coupon_to_inventory( $user_id, $dokan_coupon_id, (int) $vendor_id, $current_coupon_code );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array( 'message' => esc_html__( 'Купон продавца успешно добавлен в ваш инвентарь!', 'woodmart-child' ), 'reload_page' => true ) );
		}
	}

	public function handle_add_dokan_coupon_by_code() { 
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();
		$coupon_code_entered = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		if ( empty( $coupon_code_entered ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, введите код купона.', 'woodmart-child' ) ) );
		}
		$coupon_obj = new \WC_Coupon( $coupon_code_entered );
		if ( ! $coupon_obj->get_id() ) {
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Купон с кодом "%s" не найден или недействителен.', 'woodmart-child' ), esc_html( $coupon_code_entered ) ) ) );
		}
		$vendor_id = $coupon_obj->get_meta( 'dokan_coupon_author', true );
        if ( ! $vendor_id ) {
            $coupon_post = get_post( $coupon_obj->get_id() );
            if ( $coupon_post ) $vendor_id = $coupon_post->post_author;
        }
		$result = $this->dokan_coupon_db->add_coupon_to_inventory( $user_id, $coupon_obj->get_id(), (int) $vendor_id, $coupon_obj->get_code() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array( 'message' => sprintf( esc_html__( 'Купон продавца "%s" успешно добавлен в ваш инвентарь!', 'woodmart-child' ), esc_html( $coupon_code_entered ) ), 'reload_page' => true ) );
		}
	}

    public function handle_refresh_dokan_coupons_status() {
		check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();
        error_log("WoodmartChildRPG Dokan AJAX: handle_refresh_dokan_coupons_status for user {$user_id}"); 

		$user_watched_coupons = $this->dokan_coupon_db->get_all_user_coupons_for_status_check( $user_id );
		if ( empty( $user_watched_coupons ) ) {
            error_log("WoodmartChildRPG Dokan AJAX: No watched coupons for user {$user_id}."); 
			wp_send_json_success(
				array(
					'message'     => esc_html__( 'Ваш инвентарь купонов продавцов пуст.', 'woodmart-child' ),
					'reload_page' => false,
                    'removed_coupon_ids' => []
				)
			);
		}

		$removed_count = 0;
        $removed_ids = []; 
        $cart_for_check = WC()->cart ? WC()->cart : new \WC_Cart(); 
        $discounts_context = new \WC_Discounts( $cart_for_check );
        error_log("WoodmartChildRPG Dokan AJAX: Checking " . count($user_watched_coupons) . " coupons for user {$user_id}."); 

		foreach ( $user_watched_coupons as $watched_coupon_entry ) {
			$coupon_id     = intval( $watched_coupon_entry->coupon_id );
			$original_code = isset( $watched_coupon_entry->original_code ) ? $watched_coupon_entry->original_code : null;
			$wc_coupon     = new \WC_Coupon( $coupon_id );
            error_log("WoodmartChildRPG Dokan AJAX: Checking coupon ID {$coupon_id}, Original Code: {$original_code}, Current Code: " . $wc_coupon->get_code() ); 

			$should_remove = false;
            $reason_for_removal = ''; 

			if ( ! $wc_coupon->get_id() ) { 
				$should_remove = true;
                $reason_for_removal = 'Coupon post does not exist.';
			} elseif ( $original_code !== null && $wc_coupon->get_code() !== $original_code ) { 
				$should_remove = true;
                $reason_for_removal = 'Coupon code changed by vendor.';
			} else {
                $validity_check = $discounts_context->is_coupon_valid( $wc_coupon );
                if(is_wp_error($validity_check)){ 
                    $error_code = $validity_check->get_error_code();
                    $reason_for_removal = "Invalid by WC_Discounts: {$error_code} - " . $validity_check->get_error_message();
                    $critical_error_codes = array('coupon_is_expired', 'coupon_usage_limit_reached', 'woocommerce_coupon_not_found', 'woocommerce_coupon_invalid_removed', 'invalid_coupon');
                    if (in_array($error_code, $critical_error_codes, true)) {
                        $should_remove = true;
                    }
                }
            }
            error_log("WoodmartChildRPG Dokan AJAX: Coupon ID {$coupon_id} - Should remove: " . ($should_remove ? 'YES' : 'NO') . ". Reason: " . $reason_for_removal); 

			if ( $should_remove ) {
                error_log("WoodmartChildRPG Dokan AJAX: Attempting to remove coupon ID {$coupon_id} from DB for user {$user_id}."); 
				if ( $this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id ) ) {
					$removed_count++;
                    $removed_ids[] = $coupon_id; 
                    error_log("WoodmartChildRPG Dokan AJAX: Successfully removed coupon ID {$coupon_id} from DB for user {$user_id}."); 
				} else {
                    error_log("WoodmartChildRPG Dokan AJAX: FAILED to remove coupon ID {$coupon_id} from DB for user {$user_id}. (remove_coupon_from_inventory returned false)"); 
                }
			}
		}
        error_log("WoodmartChildRPG Dokan AJAX: Finished checking. Total removed: {$removed_count}. Removed IDs: " . implode(', ', $removed_ids)); 

		if ( $removed_count > 0 ) {
			wp_send_json_success(
				array(
					'message'     => sprintf( esc_html__( 'Статус купонов продавцов обновлен. Удалено недействительных или измененных купонов: %d.', 'woodmart-child' ), $removed_count ),
					'reload_page' => false, 
                    'removed_coupon_ids' => $removed_ids 
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message'     => esc_html__( 'Все купоны продавцов в вашем инвентаре актуальны.', 'woodmart-child' ),
					'reload_page' => false,
                    'removed_coupon_ids' => []
				)
			);
		}
	}
    
    public function handle_clear_invalid_dokan_coupons() {
        check_ajax_referer( 'rpg_ajax_nonce', '_ajax_nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, войдите в систему.', 'woodmart-child' ) ) );
		}
		$user_id = get_current_user_id();
        error_log("WoodmartChildRPG Dokan AJAX: handle_clear_invalid_dokan_coupons for user {$user_id}"); 

        $all_user_dokan_coupons = $this->dokan_coupon_db->get_all_user_coupons_for_status_check( $user_id );
        if ( empty( $all_user_dokan_coupons ) ) {
            error_log("WoodmartChildRPG Dokan AJAX: No coupons in inventory to clear for user {$user_id}."); 
            wp_send_json_success( array( 
                'message' => __( 'Ваш инвентарь купонов продавцов уже пуст.', 'woodmart-child' ), 
                'removed_count' => 0,
                'removed_coupon_ids' => [] 
            ));
        }

        $removed_count = 0;
        $removed_ids = []; 
        $cart_for_check = WC()->cart ? WC()->cart : new \WC_Cart();
        $discounts_context = new \WC_Discounts( $cart_for_check );
        error_log("WoodmartChildRPG Dokan AJAX: Clearing " . count($all_user_dokan_coupons) . " coupons for user {$user_id}."); 


        foreach ( $all_user_dokan_coupons as $coupon_entry ) {
            $coupon_id = (int) $coupon_entry->coupon_id;
            $original_code = $coupon_entry->original_code;
            $wc_coupon = new \WC_Coupon( $coupon_id );
            error_log("WoodmartChildRPG Dokan AJAX: Clearing - Checking coupon ID {$coupon_id}, Original Code: {$original_code}, Current Code: " . $wc_coupon->get_code() ); 
            
            $should_remove = false;
            $reason_for_removal_clear = '';

            if ( ! $wc_coupon->get_id() ) {
                $should_remove = true;
                $reason_for_removal_clear = 'Coupon post does not exist.';
            } elseif ( $wc_coupon->get_code() !== $original_code ) { 
                $should_remove = true;
                $reason_for_removal_clear = 'Coupon code changed by vendor (original: '.$original_code.', current: '.$wc_coupon->get_code().').';
            } else {
                $validity_check = $discounts_context->is_coupon_valid( $wc_coupon );
                if ( is_wp_error( $validity_check ) ) {
                    $error_code = $validity_check->get_error_code();
                    $reason_for_removal_clear = "Invalid by WC_Discounts: {$error_code} - " . $validity_check->get_error_message();
                    $critical_error_codes = array('coupon_is_expired', 'coupon_usage_limit_reached', 'woocommerce_coupon_not_found', 'woocommerce_coupon_invalid_removed', 'invalid_coupon');
                    if (in_array($error_code, $critical_error_codes, true)) {
                        $should_remove = true; 
                    }
                }
            }
            error_log("WoodmartChildRPG Dokan AJAX: Clearing - Coupon ID {$coupon_id} - Should remove: " . ($should_remove ? 'YES' : 'NO') . ". Reason: " . $reason_for_removal_clear); 

            if ( $should_remove ) {
                error_log("WoodmartChildRPG Dokan AJAX: Clearing - Attempting to remove coupon ID {$coupon_id} from DB for user {$user_id}."); 
                if ( $this->dokan_coupon_db->remove_coupon_from_inventory( $user_id, $coupon_id ) ) {
                    $removed_count++;
                    $removed_ids[] = $coupon_id; 
                    error_log("WoodmartChildRPG Dokan AJAX: Clearing - Successfully removed coupon ID {$coupon_id} from DB for user {$user_id}."); 
                } else {
                     error_log("WoodmartChildRPG Dokan AJAX: Clearing - FAILED to remove coupon ID {$coupon_id} from DB for user {$user_id}. (remove_coupon_from_inventory returned false)"); 
                }
            }
        }
        error_log("WoodmartChildRPG Dokan AJAX: Clearing finished. Total removed: {$removed_count}. Removed IDs: " . implode(', ', $removed_ids)); 


        if ( $removed_count > 0 ) {
            wp_send_json_success( array( 
                'message' => sprintf( _n( '%d недействительный купон продавца был удален из вашего инвентаря.', '%d недействительных купонов продавцов были удалены из вашего инвентаря.', $removed_count, 'woodmart-child' ), $removed_count ),
                'removed_count' => $removed_count,
                'reload_page' => false, 
                'removed_coupon_ids' => $removed_ids 
            ) );
        } else {
            wp_send_json_success( array( 
                'message' => __( 'Не найдено недействительных купонов продавцов для удаления.', 'woodmart-child' ),
                'removed_count' => 0,
                'reload_page' => false,
                'removed_coupon_ids' => []
            ) );
        }
    }    
}

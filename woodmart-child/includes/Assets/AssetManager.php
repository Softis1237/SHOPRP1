<?php
/**
 * Менеджер ассетов (CSS/JS) для фронтенда и админ-панели.
 *
 * @package WoodmartChildRPG\Assets
 */

namespace WoodmartChildRPG\Assets;

use WoodmartChildRPG\RPG\Character;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Запрещаем прямой доступ.
}

class AssetManager {

    private $character_manager;

    public function __construct( Character $character_manager ) {
        $this->character_manager = $character_manager;
    }

    public function enqueue_frontend_assets() {
        $theme_version = wp_get_theme()->get( 'Version' ) ? wp_get_theme()->get( 'Version' ) : '1.0.0';
        $user_id   = get_current_user_id();
        $user_race = $user_id ? $this->character_manager->get_race( $user_id ) : '';

        $rpg_common_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'user_id'  => $user_id,
            'race'     => $user_race,
            'text'     => array(
                'error_network'        => __( 'Ошибка сети. Пожалуйста, попробуйте еще раз.', 'woodmart-child' ),
                'error_generic'        => __( 'Произошла непредвиденная ошибка.', 'woodmart-child' ),
                'confirm_deactivate'   => __( 'Вы уверены, что хотите деактивировать этот купон?', 'woodmart-child' ),
                'applying_coupon'      => __( 'Применение...', 'woodmart-child' ),
                'error_coupon_empty'   => __( 'Пожалуйста, введите код купона.', 'woodmart-child' ),
                'inventory_empty'      => __( 'Ваш инвентарь купонов продавцов пуст.', 'woodmart-child' ),
                'already_active'       => __( 'Уже активен', 'woodmart-child' ),
                'confirm_clear_invalid_dokan' => __( 'Вы уверены, что хотите удалить все недействительные купоны продавцов из вашего инвентаря? Это действие необратимо.', 'woodmart-child' ),
                'confirm_elf_select'   => __( 'Выберите хотя бы один товар.', 'woodmart-child' ),
                'ability_activated'    => __( 'Способность активирована!', 'woodmart-child' ),
                'ability_select_item'  => __( 'Выберите товар(ы) в корзине для применения способности.', 'woodmart-child' ),
                'ability_already_used' => __( 'Способность уже активирована на этой неделе.', 'woodmart-child' ),
                'ability_level_low'    => __( 'Способность доступна с более высокого уровня.', 'woodmart-child' ),
                'show_details'         => __( 'Подробнее', 'woodmart-child' ),
                'hide_details'         => __( 'Скрыть детали', 'woodmart-child' ),
            ),
        );

        // Страница входа/регистрации (для незалогиненных пользователей)
        if ( is_account_page() && ! is_user_logged_in() ) {
            wp_enqueue_style(
                'rpg-login-register-style',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/css/login-register.css',
                array(),
                $theme_version
            );
            wp_enqueue_script(
                'rpg-login-register-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/login-register.js',
                array( 'jquery' ),
                $theme_version,
                true
            );
            $login_reg_data = [
                'text' => [
                    'error_select_gender' => __( 'Пожалуйста, выберите гендер.', 'woodmart-child' ),
                    'error_select_race'   => __( 'Пожалуйста, выберите расу.', 'woodmart-child' ),
                ]
            ];
            wp_localize_script( 'rpg-login-register-script', 'rpg_settings', array_merge_recursive( $rpg_common_data, $login_reg_data ) );
        }

        // Страница аккаунта (для залогиненных пользователей)
        if ( is_account_page() && is_user_logged_in() ) {
            wp_enqueue_script(
                'rpg-account-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-account.js',
                array( 'jquery' ),
                $theme_version,
                true
            );
            $account_data = $rpg_common_data;
            $account_data['nonce'] = wp_create_nonce( 'rpg_ajax_nonce' );
            wp_localize_script( 'rpg-account-script', 'rpg_settings', $account_data );
        }

        // Страницы корзины и оформления заказа
        if ( is_cart() || ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && ! is_checkout_pay_page() ) ) {
            wp_enqueue_style(
                'rpg-cart-checkout-style',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/css/rpg-cart-checkout.css',
                array(),
                $theme_version
            );
            wp_enqueue_script(
                'rpg-cart-checkout-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-cart-checkout.js',
                array( 'jquery', 'woocommerce' ),
                $theme_version,
                true
            );
            $page_params = $rpg_common_data;
            if ( is_cart() ) {
                $page_params['current_page_nonce'] = wp_create_nonce( 'rpg_cart_ajax_nonce' );
                $page_params['is_checkout_page'] = false;
                $page_params['text']['apply_coupon_btn'] = __( 'Применить купон', 'woodmart-child' );
                if ( $user_id && $this->character_manager->get_race( $user_id ) === 'elf' ) {
                    $level = $this->character_manager->get_level( $user_id );
                    $sense_max_items_map = array( 3 => 1, 4 => 2, 5 => 3 );
                    $page_params['elf_sense_max_items'] = isset( $sense_max_items_map[ $level ] ) ? $sense_max_items_map[ $level ] : 0;
                }
            } elseif ( is_checkout() ) {
                $page_params['current_page_nonce'] = wp_create_nonce( 'rpg_checkout_ajax_nonce' );
                $page_params['is_checkout_page'] = true;
                $page_params['text']['apply_coupon_btn'] = esc_html__( 'Apply coupon', 'woocommerce' );
            }
            wp_localize_script( 'rpg-cart-checkout-script', 'rpg_page_params', $page_params );
        }
    }

    public function enqueue_admin_assets( $hook_suffix ) {
        $theme_version = wp_get_theme()->get( 'Version' ) ? wp_get_theme()->get( 'Version' ) : '1.0.0';
        if ( in_array( $hook_suffix, array( 'profile.php', 'user-edit.php', 'users.php' ), true ) ) {
            wp_enqueue_script(
                'rpg-admin-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-admin-profile.js',
                array( 'jquery' ),
                $theme_version,
                true
            );
            wp_localize_script(
                'rpg-admin-script',
                'rpg_admin_settings',
                array(
                    'nonce'    => wp_create_nonce( 'rpg_admin_ajax_nonce' ),
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'text'     => array(
                        'confirm_delete' => __( 'Вы уверены, что хотите удалить этот купон?', 'woodmart-child' ),
                        'confirm_reset'  => __( 'Вы уверены, что хотите сбросить кулдаун этой способности?', 'woodmart-child' ),
                        'no_activation_yet' => __( 'нет', 'woodmart-child' ),
                    ),
                )
            );
        }
    }

    public function enqueue_dokan_store_assets() {
        if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
            $theme_version = wp_get_theme()->get( 'Version' ) ? wp_get_theme()->get( 'Version' ) : '1.0.0';
            wp_enqueue_script(
                'rpg-account-script',
                WOODMART_CHILD_RPG_DIR_URI . 'assets/js/rpg-account.js',
                array( 'jquery' ),
                $theme_version,
                true
            );
            $user_id   = get_current_user_id();
            $user_race = $user_id ? $this->character_manager->get_race( $user_id ) : '';
            $dokan_store_data = array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'user_id'  => $user_id,
                'race'     => $user_race,
                'nonce'    => wp_create_nonce( 'rpg_ajax_nonce' ),
                'text'     => array(
                    'error_network'        => __( 'Ошибка сети. Пожалуйста, попробуйте еще раз.', 'woodmart-child' ),
                    'error_generic'        => __( 'Произошла непредвиденная ошибка.', 'woodmart-child' ),
                    'confirm_deactivate'   => __( 'Вы уверены, что хотите деактивировать этот купон?', 'woodmart-child' ),
                    'applying_coupon'      => __( 'Применение...', 'woodmart-child' ),
                    'error_coupon_empty'   => __( 'Пожалуйста, введите код купона.', 'woodmart-child' ),
                    'inventory_empty'      => __( 'Ваш инвентарь купонов продавцов пуст.', 'woodmart-child' ),
                    'already_active'       => __( 'Уже активен', 'woodmart-child' ),
                    'confirm_clear_invalid_dokan' => __( 'Вы уверены, что хотите удалить все недействительные купоны продавцов из вашего инвентаря? Это действие необратимо.', 'woodmart-child' ),
                    'confirm_elf_select'   => __( 'Выберите хотя бы один товар.', 'woodmart-child' ),
                    'ability_activated'    => __( 'Способность активирована!', 'woodmart-child' ),
                    'ability_select_item'  => __( 'Выберите товар(ы) в корзине для применения способности.', 'woodmart-child' ),
                    'ability_already_used' => __( 'Способность уже активирована на этой неделе.', 'woodmart-child' ),
                    'ability_level_low'    => __( 'Способность доступна с более высокого уровня.', 'woodmart-child' ),
                    'show_details'         => __( 'Подробнее', 'woodmart-child' ),
                    'hide_details'         => __( 'Скрыть детали', 'woodmart-child' ),
                ),
            );
            wp_localize_script( 'rpg-account-script', 'rpg_settings', $dokan_store_data );
        }
    }
}
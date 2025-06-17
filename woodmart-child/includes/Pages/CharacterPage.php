<?php
/**
 * Управляет логикой и отображением страницы "Персонаж" в "Моем аккаунте".
 *
 * @package WoodmartChildRPG\Pages
 */

namespace WoodmartChildRPG\Pages;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\RPG\RaceFactory;
use WoodmartChildRPG\RPG\LevelManager;
use WoodmartChildRPG\Integration\Dokan\DokanUserCouponDB;
use WoodmartChildRPG\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Запрещаем прямой доступ.
}

class CharacterPage {
    /** @var RPGCharacter */
    private $character_manager;
    /** @var DokanUserCouponDB */
    private $dokan_coupon_db;

    public function __construct( RPGCharacter $character_manager, DokanUserCouponDB $dokan_coupon_db ) {
        $this->character_manager = $character_manager;
        $this->dokan_coupon_db   = $dokan_coupon_db;
    }

    public function render_page_content() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            echo '<p class="woocommerce-info">' . esc_html__( 'Пожалуйста, войдите, чтобы посмотреть информацию о персонаже.', 'woodmart-child' ) . '</p>';
            return;
        }

        $data_for_template = $this->prepare_data_for_template( $user_id );

        $template_path = WOODMART_CHILD_RPG_DIR_PATH . 'templates/myaccount/character-page-content.php';
        if ( file_exists( $template_path ) ) {
            extract( $data_for_template ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            include $template_path;
        } else {
            echo '<p class="woocommerce-error">' . esc_html__( 'Шаблон страницы персонажа не найден.', 'woodmart-child' ) . '</p>';
        }
    }

    private function prepare_data_for_template( $user_id ) {
        $data = [];

        // Основные параметры персонажа
        $data['user_id']           = $user_id;
        $data['user_race_slug']    = $this->character_manager->get_meta( $user_id, 'race' );
        $data['user_level']        = $this->character_manager->get_level( $user_id );
        $data['total_spent']       = $this->character_manager->get_total_spent( $user_id );
        $data['experience_points'] = $this->character_manager->get_experience( $user_id );
        $data['user_gold']         = $this->character_manager->get_gold( $user_id );

        $race_object = RaceFactory::create_race( $data['user_race_slug'] );
        $data['user_race_name'] = $race_object ? $race_object->get_name() : __( 'Не выбрана', 'woodmart-child' );
        $data['race_bonuses_description'] = $race_object ? $race_object->get_passive_bonus_description() : __( 'Описание бонусов не найдено.', 'woodmart-child' );

        // Прогресс-бар уровня
        if ( 'dwarf' === $data['user_race_slug'] ) {
            $data['max_level']         = LevelManager::get_max_dwarf_level();
            $data['xp_for_next_level'] = ( $data['user_level'] < $data['max_level'] ) ? LevelManager::get_xp_for_dwarf_level( $data['user_level'] + 1 ) : $data['experience_points'];
            $xp_for_current_level = ( $data['user_level'] > 0 ) ? LevelManager::get_xp_for_dwarf_level( $data['user_level'] ) : 0;
        } else {
            $data['max_level']         = LevelManager::get_max_level();
            $data['xp_for_next_level'] = ( $data['user_level'] < $data['max_level'] ) ? LevelManager::get_xp_for_level( $data['user_level'] + 1 ) : $data['experience_points'];
            $xp_for_current_level = ( $data['user_level'] > 0 ) ? LevelManager::get_xp_for_level( $data['user_level'] ) : 0;
        }
        $xp_needed = $data['xp_for_next_level'] - $xp_for_current_level;
        $xp_gained = $data['experience_points'] - $xp_for_current_level;

        if ( $data['user_level'] >= $data['max_level'] ) {
            $data['progress_percent'] = 100;
        } elseif ( $xp_needed > 0 ) {
            $data['progress_percent'] = round( ( $xp_gained / $xp_needed ) * 100, 2 );
            $data['progress_percent'] = max( 0, min( $data['progress_percent'], 100 ) );
        } else {
            $data['progress_percent'] = ( $data['experience_points'] > $xp_for_current_level && $xp_for_current_level > 0 ) ? 100 : 0;
            if ( $data['user_level'] === 1 && $data['experience_points'] === 0 && $xp_for_current_level === 0 && $xp_needed > 0) {
                $data['progress_percent'] = 0;
            } elseif ( $data['user_level'] === 1 && $xp_needed > 0) {
                $data['progress_percent'] = round( ( $data['experience_points'] / $xp_needed ) * 100, 2 );
                $data['progress_percent'] = max( 0, min( $data['progress_percent'], 100 ) );
            }
        }

        // RPG купоны
        $data['rpg_coupons']            = $this->character_manager->get_coupon_inventory( $user_id );
        $data['active_rpg_item_coupon'] = WC()->session ? WC()->session->get( 'active_item_coupon' ) : null;
        $data['active_rpg_cart_coupon'] = WC()->session ? WC()->session->get( 'active_cart_coupon' ) : null;

        // Ревалидация активного Dokan купона из сессии
        $active_dokan_details_from_session = WC()->session ? WC()->session->get( 'active_rpg_dokan_coupon_details' ) : null;
        if ( !empty($active_dokan_details_from_session) && isset($active_dokan_details_from_session['id'], $active_dokan_details_from_session['code']) ) {
            $coupon_id_from_session = (int) $active_dokan_details_from_session['id'];
            $expected_code_from_session = $active_dokan_details_from_session['code'];
            $wc_coupon = new \WC_Coupon( $coupon_id_from_session );

            $is_still_valid = true;
            if ( ! $wc_coupon->get_id() || $wc_coupon->get_code() !== $expected_code_from_session ) {
                $is_still_valid = false;
            } else {
                if ( $wc_coupon->get_date_expires() && $wc_coupon->get_date_expires()->getTimestamp() < current_time( 'timestamp', true ) ) {
                    $is_still_valid = false;
                } elseif ( $wc_coupon->get_usage_limit() > 0 && $wc_coupon->get_usage_count() >= $wc_coupon->get_usage_limit() ) {
                    $is_still_valid = false;
                }
            }

            if ( ! $is_still_valid ) {
                WC()->session->set( 'active_rpg_dokan_coupon_details', null );
                $this->dokan_coupon_db->remove_coupon_from_inventory($user_id, $coupon_id_from_session);
                $active_dokan_details_from_session = null;
            }
        }
        $data['active_dokan_vendor_coupon_details'] = $active_dokan_details_from_session;

        // Купоны продавцов Dokan из инвентаря
        $data['dokan_coupons_per_page']   = apply_filters( 'wcrpg_dokan_coupons_per_page_char', 10 );
        $data['current_dokan_page']       = isset( $_GET['dokan_coupon_page'] ) ? max( 1, intval( $_GET['dokan_coupon_page'] ) ) : 1;
        $data['filter_vendor_id']         = isset( $_GET['filter_vendor_id'] ) ? intval( $_GET['filter_vendor_id'] ) : 0;

        $raw_dokan_coupon_entries = $this->dokan_coupon_db->get_user_coupons(
            $user_id, $data['filter_vendor_id'], $data['dokan_coupons_per_page'], $data['current_dokan_page']
        );
        $data['total_dokan_coupons'] = $this->dokan_coupon_db->get_user_coupons_count(
            $user_id, $data['filter_vendor_id']
        );

        $processed_dokan_coupons = [];
        if (!empty($raw_dokan_coupon_entries)) {
            foreach ($raw_dokan_coupon_entries as $entry) {
                $coupon_obj = new \WC_Coupon( (int) $entry->coupon_id );
                if (!$coupon_obj->get_id()) {
                    continue;
                }

                $coupon_item_data = [
                    'id'                 => $coupon_obj->get_id(),
                    'code'               => $coupon_obj->get_code(),
                    'description'        => $coupon_obj->get_description(),
                    'amount'             => $coupon_obj->get_amount(),
                    'discount_type'      => $coupon_obj->get_discount_type(),
                    'date_expires'       => $coupon_obj->get_date_expires() ? $coupon_obj->get_date_expires()->date_i18n( get_option( 'date_format' ) ) : null,
                    'minimum_amount'     => $coupon_obj->get_minimum_amount(),
                    'maximum_amount'     => $coupon_obj->get_maximum_amount(),
                    'usage_limit'        => $coupon_obj->get_usage_limit(),
                    'usage_count'        => $coupon_obj->get_usage_count(),
                    'individual_use'     => $coupon_obj->get_individual_use(),
                    'product_ids'        => $coupon_obj->get_product_ids(),
                    'excluded_product_ids' => $coupon_obj->get_excluded_product_ids(),
                    'product_categories' => $coupon_obj->get_product_categories(),
                    'excluded_product_categories' => $coupon_obj->get_excluded_product_categories(),
                    'free_shipping'      => $coupon_obj->get_free_shipping(),
                    'vendor_id'          => (int) $entry->vendor_id,
                    'store_name'         => __( 'Магазин не найден', 'woodmart-child' ),
                    'original_db_code'   => $entry->original_code
                ];

                if (function_exists('dokan_get_store_info') && $coupon_item_data['vendor_id'] > 0) {
                    $store_info = dokan_get_store_info($coupon_item_data['vendor_id']);
                    if ($store_info && !empty($store_info['store_name'])) {
                        $coupon_item_data['store_name'] = $store_info['store_name'];
                    } else {
                        $coupon_item_data['store_name'] = sprintf(esc_html__('Магазин (ID: %d)', 'woodmart-child'), $coupon_item_data['vendor_id']);
                    }
                }
                $processed_dokan_coupons[] = $coupon_item_data;
            }
        }
        $data['dokan_coupon_entries'] = $processed_dokan_coupons;

        // Список ID продавцов для фильтра
        $data['vendor_ids_for_filter'] = [];
        if (function_exists('dokan_get_store_info')) {
            global $wpdb;
            $dokan_table_name = $wpdb->prefix . 'rpg_user_dokan_coupons';
            $vendor_ids_raw = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT vendor_id FROM {$dokan_table_name} WHERE user_id = %d AND vendor_id > 0 ORDER BY vendor_id ASC", $user_id ) );
            if ($vendor_ids_raw) {
                foreach ($vendor_ids_raw as $vid) {
                    $store_info_filter = dokan_get_store_info((int)$vid);
                    if ($store_info_filter && !empty($store_info_filter['store_name'])) {
                        $data['vendor_ids_for_filter'][(int)$vid] = $store_info_filter['store_name'];
                    } else {
                        $data['vendor_ids_for_filter'][(int)$vid] = sprintf(esc_html__('Магазин (ID: %d)', 'woodmart-child'), (int)$vid);
                    }
                }
            }
        }

        // Способности рас
        $data['elf_sense_pending']    = ( 'elf' === $data['user_race_slug'] ) ? (bool) $this->character_manager->get_meta( $user_id, 'elf_sense_pending' ) : false;
        $data['can_activate_elf_sense'] = ( 'elf' === $data['user_race_slug'] && $data['user_level'] >= 3 ) ? Utils::can_activate_weekly_ability( $user_id, 'last_elf_activation' ) : false;
        $data['rage_pending']         = ( 'orc' === $data['user_race_slug'] ) ? (bool) $this->character_manager->get_meta( $user_id, 'rage_pending' ) : false;
        $data['can_activate_orc_rage'] = ( 'orc' === $data['user_race_slug'] && $data['user_level'] >= 1 ) ? Utils::can_activate_weekly_ability( $user_id, 'last_rage_activation' ) : false;

        if ( 'elf' === $data['user_race_slug'] && $data['user_level'] >= 3 ) {
            $sense_map = [3 => 1, 4 => 2, 5 => 3];
            $data['elf_sense_max_items'] = $sense_map[ $data['user_level'] ] ?? 1;
        } else {
            $data['elf_sense_max_items'] = 0;
        }

        return $data;
    }
}
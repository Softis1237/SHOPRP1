<?php
/**
 * Управляет интеграцией RPG-системы с плагином Dokan.
 *
 * @package WoodmartChildRPG\Integration\Dokan
 */

namespace WoodmartChildRPG\Integration\Dokan;
use WoodmartChildRPG\Core\Utils;
use WoodmartChildRPG\Integration\Dokan\DokanUserCouponDB;
// Позже может понадобиться RPGCharacter, если будем проверять лимиты RPG инвентаря и т.д.
use WC_Discounts;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Запрещаем прямой доступ.
}

class DokanIntegrationManager {

    private $dokan_coupon_db;

    public function __construct( DokanUserCouponDB $dokan_coupon_db ) {
        $this->dokan_coupon_db = $dokan_coupon_db;
    }

    /**
     * Генерирует HTML для отображения одного купона Dokan на странице магазина продавца.
     * Аналог вашей функции rpg_get_single_dokan_coupon_rpg_html().
     *
     * @param \WC_Coupon $wc_coupon Объект купона WooCommerce.
     * @return string HTML для купона.
     */
    public function get_single_dokan_coupon_html( \WC_Coupon $wc_coupon ) {
        if ( ! $wc_coupon || ! $wc_coupon->get_id() ) {
            return '<p class="dokan-error">' . esc_html__( 'Ошибка отображения купона.', 'woodmart-child' ) . '</p>';
        }

        $coupon_id   = $wc_coupon->get_id();
        $user_id     = get_current_user_id();
        $button_text = esc_html__( 'Взять купон', 'woodmart-child' );
        $is_disabled = false;
        $message     = '';

        if ( $user_id ) {
            if ( $this->dokan_coupon_db->user_has_coupon( $user_id, $coupon_id ) ) {
                $button_text = esc_html__( 'Уже в инвентаре', 'woodmart-child' );
                $is_disabled = true;
            }

            // Лимит можно вынести в настройки или фильтр
            $inventory_limit        = apply_filters( 'wcrpg_dokan_vendor_coupons_inventory_limit', 20 );
            $current_inventory_size = $this->dokan_coupon_db->get_user_coupons_count( $user_id );

            if ( $current_inventory_size >= $inventory_limit && ! $is_disabled ) {
                $message     = '<small class="rpg-dokan-coupon-limit-msg">'
                            . sprintf( esc_html__( 'Лимит купонов продавцов (%d шт.) в инвентаре достигнут.', 'woodmart-child' ), $inventory_limit )
                            . '</small>';
                $is_disabled = true;
                $button_text = esc_html__( 'Лимит инвентаря', 'woodmart-child' );
            }
        } else {
            $button_text = esc_html__( 'Войдите, чтобы взять', 'woodmart-child' );
            $is_disabled = true;
        }

        // Проверка валидности купона
        if ( ! $is_disabled ) {
            $discounts = new WC_Discounts( WC()->cart ); // Инициализируем WC_Discounts с текущей корзиной
            $result = $discounts->is_coupon_valid( $wc_coupon );

            if ( is_wp_error( $result ) ) {
                $error_code = $result->get_error_code();
                if ( $wc_coupon->get_date_expires() && $wc_coupon->get_date_expires()->getTimestamp() < current_time( 'timestamp', true ) ) {
                    $button_text = esc_html__( 'Срок истек', 'woodmart-child' );
                } elseif ( $error_code === 'coupon_usage_limit_reached' ) {
                    $button_text = esc_html__( 'Лимит исчерпан', 'woodmart-child' );
                } else {
                    $button_text = esc_html__( 'Недействителен', 'woodmart-child' );
                }
                $is_disabled = true;
            }
        }

        $coupon_amount_formatted = $wc_coupon->get_discount_type() === 'percent'
            ? $wc_coupon->get_amount() . '%'
            : wc_price( $wc_coupon->get_amount(), array( 'decimals' => 0 ) );

        $expiry_date_obj = $wc_coupon->get_date_expires();
        $icon_class = method_exists( Utils::class, 'get_coupon_icon_class' )
            ? Utils::get_coupon_icon_class( $wc_coupon->get_discount_type() )
            : 'fas fa-tag';

        $html_output = '<div class="coupon-item store-coupon-item-rpg dokan-coupon-item" data-coupon-id="' . esc_attr( $coupon_id ) . '">';
        $html_output .= '  <span class="coupon-icon ' . esc_attr( $icon_class ) . '" title="' . esc_attr( $wc_coupon->get_discount_type() ) . '"></span>';
        $html_output .= '  <div class="coupon-text">';

        $coupon_post_obj = get_post( $coupon_id );
        $vendor_id       = $coupon_post_obj ? (int) $coupon_post_obj->post_author : 0;
        // Dokan также может хранить ID вендора в мета-поле _dokan_vendor_id
        if ( ! $vendor_id ) {
            $dokan_meta_vendor = $wc_coupon->get_meta( '_dokan_vendor_id', true );
            if ( ! empty( $dokan_meta_vendor ) ) {
                $vendor_id = (int) $dokan_meta_vendor;
            }
        }

        if ( $vendor_id && function_exists( 'dokan_get_store_info' ) ) {
            $store_info = dokan_get_store_info( $vendor_id );
            if ( ! empty( $store_info['store_name'] ) ) {
                $html_output .= '<span class="dokan-coupon-store-name"><strong>' . esc_html( $store_info['store_name'] ) . '</strong></span>';
            }
        }

        $description = $wc_coupon->get_description();
        if ( ! empty( $description ) ) {
            $html_output .= '      ' . wp_kses_post( wp_trim_words( $description, 10, '...' ) ) . '<br>';
        } else {
            $html_output .= '      ' . esc_html__( 'Скидка', 'woodmart-child' ) . ' ' . $coupon_amount_formatted . '<br>';
        }
        $html_output .= '      <strong>' . $coupon_amount_formatted . '</strong><br>';

        $min_amount = $wc_coupon->get_minimum_amount();
        if ( $min_amount > 0 ) {
            $html_output .= '      <small>'
                          . esc_html__( 'Мин. заказ', 'woodmart-child' ) . ': '
                          . wc_price( $min_amount, array( 'decimals' => 0 ) )
                          . '</small><br>';
        }

        if ( $expiry_date_obj ) {
            $html_output .= '      <small>' . esc_html__( 'Истекает', 'woodmart-child' ) . ': '
                          . esc_html( date_i18n( get_option( 'date_format' ), $expiry_date_obj->getTimestamp() ) )
                          . '</small>';
        } else {
            $html_output .= '      <small>' . esc_html__( 'Срок не ограничен', 'woodmart-child' ) . '</small>';
        }

        $html_output .= '  </div>';
        $html_output .= '  <button type="button" class="button rpg-take-dokan-coupon-btn" data-coupon-id="' . esc_attr( $coupon_id ) . '" '
                      . ( $is_disabled ? 'disabled="disabled"' : '' ) . '>'
                      . esc_html( $button_text ) . '</button>';

        if ( $message ) {
            $html_output .= $message;
        }
        $html_output .= '  <div class="rpg-dokan-coupon-message" style="font-size:0.9em; margin-top:5px; display:none;"></div>';
        $html_output .= '</div>';

        return $html_output;
    }

    /**
     * Удаляет купоны Dokan из пользовательского инвентаря при удалении поста купона.
     * Аналог rpg_remove_deleted_dokan_coupon_from_custom_table().
     *
     * @param int $post_id ID удаляемого поста.
     */
    public function handle_deleted_dokan_coupon_post( $post_id ) {
        if ( get_post_type( $post_id ) !== 'shop_coupon' ) {
            return;
        }
        $this->dokan_coupon_db->remove_coupon_from_inventory( 0, $post_id ); // Удаляем для всех пользователей, если купон удален
        // error_log( "WoodmartChildRPG DokanIntegration: Attempted to remove entries for deleted coupon ID {$post_id}." );
    }
}
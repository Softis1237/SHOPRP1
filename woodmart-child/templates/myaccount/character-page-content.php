<?php
/**
 * Шаблон контента страницы "Персонаж"
 * Файл: woodmart-child/templates/myaccount/character-page-content.php
 * Использует переменные, переданные из CharacterPage::render_page_content()
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Переменные из CharacterPage::prepare_data_for_template() уже доступны здесь
// $user_id, $user_race_slug, $user_level, $total_spent, $experience_points, $user_gold,
// $user_race_name, $race_bonuses_description, $max_level, $xp_for_next_level, $progress_percent,
// $rpg_coupons, $active_rpg_item_coupon, $active_rpg_cart_coupon,
// $dokan_coupons_per_page, $current_dokan_page, $filter_vendor_id,
// $dokan_coupon_entries, $total_dokan_coupons, 
// $active_dokan_vendor_coupon_details, 
// $elf_sense_pending, $can_activate_elf_sense, $elf_sense_max_items,
// $rage_pending, $can_activate_orc_rage, $vendor_ids_for_filter (опционально)
?>
<div class="woocommerce-MyAccount-content rpg-character-page">
    <h2><?php esc_html_e( 'Персонаж', 'woodmart-child' ); ?></h2>
    <div class="rpg-message-box глобальное-сообщение" style="display: none; margin-bottom: 20px;"></div>

    <?php if ( $active_rpg_item_coupon || $active_rpg_cart_coupon || !empty($active_dokan_vendor_coupon_details) ) : ?>
        <div class="rpg-section active-coupons-section">
            <h3><?php esc_html_e( 'Активные купоны', 'woodmart-child' ); ?></h3>
            <?php if ( $active_rpg_item_coupon && is_array( $active_rpg_item_coupon ) && isset( $active_rpg_item_coupon['type'], $active_rpg_item_coupon['value'] ) ) : ?>
                <div class="active-coupon-notice rpg-active-notice">
                    <i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $active_rpg_item_coupon['type'] ) ); ?> coupon-icon"></i>
                    <?php printf( esc_html__( 'Активен RPG купон на товар: %s%% (%s)', 'woodmart-child' ), '<strong>' . esc_html( $active_rpg_item_coupon['value'] ) . '</strong>', esc_html( $active_rpg_item_coupon['type'] ) ); ?>
                    <button type="button" class="button rpg-deactivate-coupon-btn" data-coupon-type="rpg_item"><?php esc_html_e( 'Вернуть в инвентарь', 'woodmart-child' ); ?></button>
                </div>
            <?php endif; ?>
            <?php if ( $active_rpg_cart_coupon && is_array( $active_rpg_cart_coupon ) && isset( $active_rpg_cart_coupon['type'], $active_rpg_cart_coupon['value'] ) ) : ?>
                <div class="active-coupon-notice rpg-active-notice">
                    <i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $active_rpg_cart_coupon['type'] ) ); ?> coupon-icon"></i>
                    <?php printf( esc_html__( 'Активен RPG купон на корзину: %s%% (%s)', 'woodmart-child' ), '<strong>' . esc_html( $active_rpg_cart_coupon['value'] ) . '</strong>', esc_html( $active_rpg_cart_coupon['type'] ) ); ?>
                    <button type="button" class="button rpg-deactivate-coupon-btn" data-coupon-type="rpg_cart"><?php esc_html_e( 'Вернуть в инвентарь', 'woodmart-child' ); ?></button>
                </div>
            <?php endif; ?>
            <?php
            if ( !empty($active_dokan_vendor_coupon_details) && isset($active_dokan_vendor_coupon_details['id'], $active_dokan_vendor_coupon_details['code']) ) :
                $active_dokan_coupon_obj = new \WC_Coupon( (int) $active_dokan_vendor_coupon_details['id'] );
                $is_active_dokan_still_valid = $active_dokan_coupon_obj->get_id() && $active_dokan_coupon_obj->get_code() === $active_dokan_vendor_coupon_details['code'];
                if ($is_active_dokan_still_valid) {
                    $discounts_context_check = new \WC_Discounts( WC()->cart ? WC()->cart : new \WC_Cart() );
                    if (is_wp_error($discounts_context_check->is_coupon_valid( $active_dokan_coupon_obj ))) {
                        $is_active_dokan_still_valid = false;
                    }
                }
                if ( $is_active_dokan_still_valid ) : ?>
                    <div class="active-coupon-notice dokan-active-notice">
                        <i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $active_dokan_coupon_obj->get_discount_type() ) ); ?> coupon-icon"></i>
                        <?php
                            $value_label_active = $active_dokan_coupon_obj->get_discount_type() === 'percent'
                                ? $active_dokan_coupon_obj->get_amount() . '%'
                                : wc_price( $active_dokan_coupon_obj->get_amount(), array( 'decimals' => 0 ) );
                            printf(
                                esc_html__( 'Активен купон продавца (%1$s): %2$s', 'woodmart-child' ),
                                esc_html( $active_dokan_vendor_coupon_details['code'] ), 
                                '<strong>' . $value_label_active . '</strong>'
                            );
                        ?>
                        <button type="button" class="button rpg-deactivate-coupon-btn" data-coupon-type="dokan_vendor"><?php esc_html_e( 'Вернуть в инвентарь', 'woodmart-child' ); ?></button>
                    </div>
                <?php else : ?>
                    <?php if (WC()->session) WC()->session->set('active_rpg_dokan_coupon_details', null); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="rpg-section rpg-info">
        <h3><?php esc_html_e( 'Основная информация', 'woodmart-child' ); ?></h3>
        <p>
            <i class="<?php echo esc_attr( \WoodmartChildRPG\Core\Utils::get_race_icon_class( $user_race_slug ) ); ?>"></i>
            <strong><?php esc_html_e( 'Раса:', 'woodmart-child' ); ?></strong> <?php echo esc_html( $user_race_name ); ?>
        </p>
        <p><strong><?php esc_html_e( 'Уровень:', 'woodmart-child' ); ?></strong> <?php echo esc_html( $user_level ); ?></p>
        <p>
            <strong><?php esc_html_e( 'Опыт:', 'woodmart-child' ); ?></strong> <?php echo esc_html( number_format_i18n( $experience_points, 0 ) ); ?> / <?php echo ( $user_level < $max_level ) ? esc_html( number_format_i18n( $xp_for_next_level, 0 ) ) : esc_html__( 'Максимум', 'woodmart-child' ); ?>
            <br>
            <strong><?php esc_html_e( 'Прогресс до следующего уровня:', 'woodmart-child' ); ?></strong> <?php echo round( $progress_percent, 2 ); ?>%
            <?php if ( $user_level < $max_level && $progress_percent >= 0 ) : ?>
                <progress value="<?php echo esc_attr( round( $progress_percent, 2 ) ); ?>" max="100" title="<?php echo esc_attr( round( $progress_percent, 2 ) ); ?>%"></progress>
            <?php endif; ?>
        </p>
        <p><strong><?php esc_html_e( 'Всего потрачено:', 'woodmart-child' ); ?></strong> <?php echo wc_price( $total_spent ); ?></p>
        <?php if (isset($user_gold)) : ?>
            <p><strong><?php esc_html_e( 'Золото:', 'woodmart-child' ); ?></strong> <?php echo esc_html( number_format_i18n( $user_gold, 0 ) ); ?></p>
        <?php endif; ?>
        <p><strong><?php esc_html_e( 'Бонусы расы:', 'woodmart-child' ); ?></strong> <?php echo wp_kses_post( $race_bonuses_description ); ?></p>
    </div>

    <div class="rpg-section rpg-abilities">
        <h3><?php esc_html_e( 'Способности', 'woodmart-child' ); ?></h3>
        <?php if ( ( 'elf' !== $user_race_slug || $user_level < 3 ) && ( 'orc' !== $user_race_slug || $user_level < 1 ) ) : ?>
            <p><?php esc_html_e( 'У вашей расы нет активных способностей или они доступны на более высоких уровнях.', 'woodmart-child' ); ?></p>
        <?php else : ?>
            <?php if ( 'elf' === $user_race_slug && $user_level >= 3 ) : ?>
                <div class="ability-block">
                    <h4><?php esc_html_e( 'Способность "Чутье"', 'woodmart-child' ); ?></h4>
                    <?php if ( $elf_sense_pending ) : ?>
                        <p><i><?php esc_html_e( 'Статус: Ожидает выбора товаров в корзине. Перейдите в корзину для выбора.', 'woodmart-child' ); ?></i></p>
                    <?php else : ?>
                        <p><?php printf( esc_html__( 'Активируйте, чтобы выбрать до %d товар(а/ов) в корзине и получить на них эльфийскую скидку. Действует раз в неделю.', 'woodmart-child' ), esc_html( $elf_sense_max_items ) ); ?></p>
                        <button type="button" id="activate-elf-sense" class="button" <?php disabled( ! $can_activate_elf_sense ); ?>>
                            <?php echo $can_activate_elf_sense ? esc_html__( 'Активировать "Чутье"', 'woodmart-child' ) : esc_html__( 'Уже активировано на этой неделе', 'woodmart-child' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ( 'orc' === $user_race_slug && $user_level >= 1 ) : ?>
                <div class="ability-block">
                    <h4><?php esc_html_e( 'Способность "Ярость"', 'woodmart-child' ); ?></h4>
                    <?php if ( $rage_pending ) : ?>
                        <p><i><?php esc_html_e( 'Статус: Ожидает выбора товара в корзине. Перейдите в корзину для выбора.', 'woodmart-child' ); ?></i></p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'Активируйте, чтобы выбрать 1 товар в корзине и получить на него максимальную скидку орка. Действует раз в неделю.', 'woodmart-child' ); ?></p>
                        <button type="button" id="activate-orc-rage" class="button" <?php disabled( ! $can_activate_orc_rage ); ?>>
                            <?php echo $can_activate_orc_rage ? esc_html__( 'Активировать "Ярость"', 'woodmart-child' ) : esc_html__( 'Уже активировано на этой неделе', 'woodmart-child' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="rpg-section rpg-coupons rpg-inventory-магазина">
        <h3><?php esc_html_e( 'Инвентарь Магазина (RPG Купоны)', 'woodmart-child' ); ?></h3>
        <div class="rpg-message-box rpg-coupons-msg" style="display: none;"></div>
        <?php if ( ! empty( $rpg_coupons ) ) : ?>
            <div class="coupon-inventory-grid">
                <?php foreach ( $rpg_coupons as $index => $coupon_data_rpg ) : ?>
                    <?php
                        $type_rpg        = $coupon_data_rpg['type'] ?? 'common';
                        $value_rpg       = $coupon_data_rpg['value'] ?? 0;
                        $description_rpg = $coupon_data_rpg['description'] ?? '';
                        $icon_class_rpg  = \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $type_rpg );
                        $is_rpg_item_type = in_array( $type_rpg, array( 'daily', 'exclusive_item', 'common' ), true );
                        $is_rpg_cart_type = in_array( $type_rpg, array( 'weekly', 'exclusive_cart' ), true );
                        $can_activate_this_rpg_coupon = false;
                        if ( $is_rpg_item_type && ! $active_rpg_item_coupon && empty($active_dokan_vendor_coupon_details) ) {
                            $can_activate_this_rpg_coupon = true;
                        } elseif ( $is_rpg_cart_type && ! $active_rpg_cart_coupon && empty($active_dokan_vendor_coupon_details) ) {
                            $can_activate_this_rpg_coupon = true;
                        }
                        $button_text_rpg = __( 'Активировать', 'woodmart-child' );
                        $button_disabled_reason_rpg = '';
                        if ( !empty($active_dokan_vendor_coupon_details) ) {
                            $button_disabled_reason_rpg = __( 'Купон продавца активен', 'woodmart-child' );
                        } elseif ( $is_rpg_item_type && $active_rpg_item_coupon ) {
                            $button_disabled_reason_rpg = __( 'RPG купон на товар уже активен', 'woodmart-child' );
                        } elseif ( $is_rpg_cart_type && $active_rpg_cart_coupon ) {
                            $button_disabled_reason_rpg = __( 'RPG купон на корзину уже активен', 'woodmart-child' );
                        }
                        if ( ! $can_activate_this_rpg_coupon && ! empty( $button_disabled_reason_rpg ) ) {
                            $button_text_rpg = $button_disabled_reason_rpg;
                        }
                        $type_label_rpg = $description_rpg ?: ucfirst( $type_rpg );
                        switch ( strtolower( $type_rpg ) ) {
                            case 'daily': $type_label_rpg = $description_rpg ?: __( 'Ежедневный', 'woodmart-child' ); break;
                            case 'weekly': $type_label_rpg = $description_rpg ?: __( 'Еженедельный', 'woodmart-child' ); break;
                            case 'exclusive_item': $type_label_rpg = $description_rpg ?: __( 'Эксклюзивный (на товар)', 'woodmart-child' ); break;
                            case 'exclusive_cart': $type_label_rpg = $description_rpg ?: __( 'Эксклюзивный (на корзину)', 'woodmart-child' ); break;
                            case 'common': $type_label_rpg = $description_rpg ?: __( 'Общий', 'woodmart-child' ); break;
                        }
                        $value_label_rpg = $value_rpg . '%';
                    ?>
                    <div class="coupon-item rpg-coupon-item" data-coupon-index="<?php echo esc_attr( $index ); ?>">
                        <i class="<?php echo esc_attr( $icon_class_rpg ); ?> coupon-icon" title="<?php echo esc_attr( $type_rpg ); ?>"></i>
                        <div class="coupon-text">
                            <?php echo esc_html( $type_label_rpg ); ?><br>
                            <strong><?php echo esc_html( $value_label_rpg ); ?></strong>
                        </div>
                        <button type="button" class="activate-rpg-coupon button" data-index="<?php echo intval( $index ); ?>" <?php disabled( ! $can_activate_this_rpg_coupon ); ?>>
                            <?php echo esc_html( $button_text_rpg ); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php esc_html_e( 'Ваш инвентарь RPG купонов пуст.', 'woodmart-child' ); ?></p>
        <?php endif; ?>
    </div>

    <div class="rpg-section rpg-vendor-coupons rpg-inventory-продавцов">
        <h3><?php esc_html_e( 'Инвентарь Купонов Продавцов', 'woodmart-child' ); ?></h3>
        <div class="rpg-message-box dokan-coupons-msg" style="display: none;"></div>
        <div class="add-dokan-coupon-by-code-form">
            <h4><?php esc_html_e( 'Добавить купон продавца по коду', 'woodmart-child' ); ?></h4>
            <input type="text" id="dokan-coupon-code-input" placeholder="<?php esc_attr_e( 'Введите код купона продавца', 'woodmart-child' ); ?>">
            <button type="button" id="rpg-add-dokan-coupon-by-code-btn" class="button"><?php esc_html_e( 'Добавить в инвентарь', 'woodmart-child' ); ?></button>
        </div>
        
        <div class="rpg-dokan-inventory-actions" style="margin-top: 15px; margin-bottom:15px;">
            <button type="button" id="rpg-refresh-dokan-coupons-status" class="button" title="<?php esc_attr_e( 'Проверить актуальность купонов продавцов (удалить недействительные из списка)', 'woodmart-child' ); ?>"><?php esc_html_e( 'Обновить статус списка', 'woodmart-child' ); ?></button>
            <button type="button" id="rpg-clear-invalid-dokan-coupons-btn" class="button button-danger" style="margin-left: 10px; background-color: #f44336; border-color: #d32f2f;" title="<?php esc_attr_e( 'Удалить все недействительные купоны продавцов из вашего инвентаря.', 'woodmart-child' ); ?>"><?php esc_html_e( 'Очистить инвентарь от недействительных', 'woodmart-child' ); ?></button>
        </div>

        <?php if ( $total_dokan_coupons > 0 || !empty($dokan_coupon_entries) ) : ?>
            <form method="get" class="dokan-coupon-filters-form" action="<?php echo esc_url( wc_get_account_endpoint_url( 'character' ) ); ?>">
                <label for="dokan-vendor-filter"><?php esc_html_e( 'Фильтр по магазину:', 'woodmart-child' ); ?></label>
                <select id="dokan-vendor-filter" name="filter_vendor_id">
                    <option value="0"><?php esc_html_e( 'Все магазины', 'woodmart-child' ); ?></option>
                    <?php
                    // Если массив $vendor_ids_for_filter передан из CharacterPage.php, используем его
                    if (!empty($vendor_ids_for_filter)) {
                        foreach ($vendor_ids_for_filter as $vid => $store_name_filter) {
                            echo '<option value="' . esc_attr( $vid ) . '" ' . selected( $filter_vendor_id, $vid, false ) . '>' . esc_html( $store_name_filter ) . '</option>';
                        }
                    } else {
                        // Иначе используем SQL-запрос для получения вендоров
                        global $wpdb;
                        $dokan_table_name = $wpdb->prefix . 'rpg_user_dokan_coupons';
                        $vendor_ids_in_inventory = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT vendor_id FROM {$dokan_table_name} WHERE user_id = %d AND vendor_id > 0 ORDER BY vendor_id ASC", $user_id ) );
                        foreach ( $vendor_ids_in_inventory as $vid ) {
                            $store_name_filter = sprintf( esc_html__( 'Продавец ID: %d', 'woodmart-child' ), $vid );
                            if ( function_exists( 'dokan_get_store_info' ) ) {
                                $store_info = dokan_get_store_info( $vid );
                                if ( $store_info && ! empty( $store_info['store_name'] ) ) {
                                    $store_name_filter = $store_info['store_name'];
                                }
                            }
                            echo '<option value="' . esc_attr( $vid ) . '" ' . selected( $filter_vendor_id, $vid, false ) . '>' . esc_html( $store_name_filter ) . '</option>';
                        }
                    }
                    ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e( 'Фильтр', 'woodmart-child' ); ?></button>
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'character' ) ); ?>" class="button dokan-clear-filters"><?php esc_html_e( 'Сбросить', 'woodmart-child' ); ?></a>
            </form>
            
            <div class="coupon-inventory-grid dokan-coupon-inventory-grid">
                <?php
                $coupons_displayed_on_page = 0;
                if ( ! empty( $dokan_coupon_entries ) ) {
                    foreach ( $dokan_coupon_entries as $coupon_data ) {
                        $coupons_displayed_on_page++;
                        $icon_class_dokan = \WoodmartChildRPG\Core\Utils::get_coupon_icon_class( $coupon_data['discount_type'] );
                        $value_label_dokan = $coupon_data['discount_type'] === 'percent'
                            ? $coupon_data['amount'] . '%'
                            : wc_price( $coupon_data['amount'], array( 'decimals' => 0 ) );
                        $dokan_button_text = __( 'Активировать', 'woodmart-child' );
                        $can_activate_this_specific_dokan_coupon = true;
                        $is_coupon_globally_invalid = false;

                        if ( empty($coupon_data['id']) ) {
                            $is_coupon_globally_invalid = true;
                            $dokan_button_text = __( 'Удален', 'woodmart-child' );
                        } elseif ( $coupon_data['date_expires'] && strtotime($coupon_data['date_expires']) < current_time( 'timestamp', true ) ) {
                            $is_coupon_globally_invalid = true;
                            $dokan_button_text = __( 'Истек', 'woodmart-child' );
                        } elseif ( $coupon_data['usage_limit'] > 0 && $coupon_data['usage_count'] >= $coupon_data['usage_limit'] ) {
                            $is_coupon_globally_invalid = true;
                            $dokan_button_text = __( 'Лимит исчерпан', 'woodmart-child' );
                        }

                        if ($is_coupon_globally_invalid) {
                            $can_activate_this_specific_dokan_coupon = false;
                        }

                        if ( $can_activate_this_specific_dokan_coupon ) {
                            if ( !empty($active_dokan_vendor_coupon_details) && $active_dokan_vendor_coupon_details['code'] === $coupon_data['code'] ) {
                                $dokan_button_text = __( 'Уже активен', 'woodmart-child' );
                                $can_activate_this_specific_dokan_coupon = false;
                            } elseif ( !empty($active_dokan_vendor_coupon_details) ) {
                                $dokan_button_text = __( 'Другой купон продавца активен', 'woodmart-child' );
                                $can_activate_this_specific_dokan_coupon = false;
                            } elseif ( $active_rpg_item_coupon || $active_rpg_cart_coupon ) {
                                $dokan_button_text = __( 'RPG купон активен', 'woodmart-child' );
                                $can_activate_this_specific_dokan_coupon = false;
                            }
                        }
                        // Пропускаем удаленные купоны
                        if ( empty($coupon_data['id']) && $is_coupon_globally_invalid ) {
                            continue;
                        }
                        ?>
                        <div class="coupon-item dokan-coupon-item <?php echo $is_coupon_globally_invalid ? 'coupon-invalid' : ''; ?>"
                            data-vendor-id="<?php echo esc_attr( $coupon_data['vendor_id'] ); ?>"
                            data-coupon-code="<?php echo esc_attr( $coupon_data['code'] ); ?>"
                            data-coupon-id="<?php echo esc_attr( $coupon_data['id'] ); ?>">
                            <i class="<?php echo esc_attr( $icon_class_dokan ); ?> coupon-icon" title="<?php echo esc_attr( $coupon_data['discount_type'] ); ?>"></i>
                            <div class="coupon-text">
                                <span class="dokan-coupon-store-name"><strong><?php echo esc_html( $coupon_data['store_name'] ); ?></strong></span>
                                <?php
                                    $description_dokan = $coupon_data['description'] ?? '';
                                    echo esc_html( $description_dokan ? wp_trim_words( $description_dokan, 10, '...' ) : ( __( 'Скидка', 'woodmart-child' ) . ' ' . $value_label_dokan ) );
                                ?><br>
                                <strong><?php echo $value_label_dokan; ?></strong><br>
                                <?php if ( $coupon_data['date_expires'] ) : ?>
                                    <small><?php esc_html_e( 'Истекает:', 'woodmart-child' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime($coupon_data['date_expires']) ) ); ?></small><br>
                                <?php endif; ?>
                                <?php if ( $coupon_data['minimum_amount'] > 0 ) : ?>
                                    <small><?php esc_html_e( 'Мин. заказ:', 'woodmart-child' ); ?> <?php echo wc_price( $coupon_data['minimum_amount'], array( 'decimals' => 0 ) ); ?></small><br>
                                <?php endif; ?>
                                <?php if ( $coupon_data['maximum_amount'] > 0 ) : ?>
                                    <small><?php esc_html_e( 'Макс. заказ:', 'woodmart-child' ); ?> <?php echo wc_price( $coupon_data['maximum_amount'], array( 'decimals' => 0 ) ); ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="#" class="rpg-coupon-details-toggle" data-coupon-id="<?php echo esc_attr( $coupon_data['id'] ); ?>"><?php esc_html_e('Подробнее', 'woodmart-child'); ?></a>
                            <div class="rpg-coupon-full-details" id="details-<?php echo esc_attr( $coupon_data['id'] ); ?>" style="display:none;">
                                <?php if ( $coupon_data['description'] ) : ?>
                                    <p><strong><?php esc_html_e('Полное описание:', 'woodmart-child'); ?></strong> <?php echo esc_html( $coupon_data['description'] ); ?></p>
                                <?php endif; ?>
                                <?php 
                                $cat_names = [];
                                if (!empty($coupon_data['product_categories'])) {
                                    foreach($coupon_data['product_categories'] as $cat_id) {
                                        $term = get_term($cat_id, 'product_cat');
                                        if ($term && !is_wp_error($term)) $cat_names[] = $term->name;
                                    }
                                }
                                if (!empty($cat_names)): ?>
                                    <p><strong><?php esc_html_e('Категории товаров:', 'woodmart-child'); ?></strong> <?php echo esc_html(implode(', ', $cat_names)); ?></p>
                                <?php endif; ?>
                                <?php 
                                $excl_cat_names = [];
                                if (!empty($coupon_data['excluded_product_categories'])) {
                                    foreach($coupon_data['excluded_product_categories'] as $cat_id) {
                                        $term = get_term($cat_id, 'product_cat');
                                        if ($term && !is_wp_error($term)) $excl_cat_names[] = $term->name;
                                    }
                                }
                                if (!empty($excl_cat_names)): ?>
                                    <p><strong><?php esc_html_e('Исключенные категории:', 'woodmart-child'); ?></strong> <?php echo esc_html(implode(', ', $excl_cat_names)); ?></p>
                                <?php endif; ?>
                                <?php if ( $coupon_data['free_shipping'] ) : ?>
                                    <p><strong><?php esc_html_e('Бесплатная доставка:', 'woodmart-child'); ?></strong> <?php esc_html_e('Да', 'woodmart-child'); ?></p>
                                <?php endif; ?>
                                <?php if ( $coupon_data['usage_limit'] > 0 ) : ?>
                                    <p><strong><?php esc_html_e('Лимит использований (всего):', 'woodmart-child'); ?></strong> <?php echo esc_html($coupon_data['usage_limit']); ?> (<?php printf(esc_html__('использовано: %d', 'woodmart-child'), esc_html($coupon_data['usage_count'])); ?>)</p>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="activate-dokan-coupon button" data-original-text="<?php esc_attr_e( 'Активировать', 'woodmart-child' ); ?>" <?php disabled( ! $can_activate_this_specific_dokan_coupon ); ?>>
                                <?php echo esc_html( $dokan_button_text ); ?>
                            </button>
                        </div>
                    <?php } 
                }

                if ( $coupons_displayed_on_page === 0 && $total_dokan_coupons > 0 && $current_dokan_page > 1 ) {
                    echo '<p>' . esc_html__( 'Все купоны на этой странице были удалены. Попробуйте вернуться на предыдущую страницу.', 'woodmart-child' ) . '</p>';
                } elseif ( $coupons_displayed_on_page === 0 && $total_dokan_coupons === 0 && $filter_vendor_id > 0 ) {
                    echo '<p>' . esc_html__( 'Нет купонов от этого продавца в вашем инвентаре.', 'woodmart-child' ) . '</p>';
                } elseif ( $coupons_displayed_on_page === 0 && $total_dokan_coupons === 0 ) { 
                    echo '<p class="no-coupons-message">' . esc_html__( 'Ваш инвентарь купонов продавцов пуст.', 'woodmart-child' ) . '</p>';
                }
                ?>
            </div>
            <?php
            if ( $total_dokan_coupons > $dokan_coupons_per_page ) {
                $base_paginate_url = wc_get_account_endpoint_url( 'character' );
                $pagination_args = array(
                    'base'      => add_query_arg( 'dokan_coupon_page', '%#%', $base_paginate_url ),
                    'format'    => '',
                    'current'   => $current_dokan_page,
                    'total'     => ceil( $total_dokan_coupons / $dokan_coupons_per_page ),
                    'prev_text' => __( '&laquo; Назад', 'woodmart-child' ),
                    'next_text' => __( 'Вперед &raquo;', 'woodmart-child' ),
                    'add_args'  => ($filter_vendor_id > 0) ? array('filter_vendor_id' => $filter_vendor_id) : array(),
                );
                echo '<nav class="woocommerce-pagination rpg-dokan-pagination">';
                echo paginate_links( $pagination_args );
                echo '</nav>';
            }
            ?>
        <?php elseif ($filter_vendor_id > 0) : ?>
            <p><?php esc_html_e( 'Нет купонов от этого продавца в вашем инвентаре.', 'woodmart-child' ); ?></p>
        <?php else : ?>
            <p class="no-coupons-message"><?php esc_html_e( 'Ваш инвентарь купонов продавцов пуст. Вы можете получить их на страницах магазинов или добавить по коду выше.', 'woodmart-child' ); ?></p> 
        <?php endif; ?>
    </div>
</div>	
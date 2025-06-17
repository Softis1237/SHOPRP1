/**
 * JavaScript для страницы редактирования профиля пользователя в админке.
 * Обрабатывает добавление/удаление купонов и сброс КД способностей.
 * Файл: assets/js/rpg-admin-profile.js
 *
 * Изменения:
 * - Используются локализованные переменные из rpg_admin_settings.
 * - AJAX actions уже должны быть правильными, но проверим.
 */
jQuery(document).ready(function($) {

    // Глобальные настройки из wp_localize_script (переменная rpg_admin_settings)
    const settings = typeof rpg_admin_settings !== 'undefined' ? rpg_admin_settings : {};
    const ajaxUrl = settings.ajax_url || '/wp-admin/admin-ajax.php';
    // Nonce для админских RPG действий
    const nonce = settings.nonce || ''; // Должен быть 'rpg_admin_ajax_nonce'
    const textStrings = settings.text || {};
    const errorNetwork = textStrings.error_network || 'Ошибка сети.';
    const errorGeneric = textStrings.error_generic || 'Произошла ошибка.';
    const confirmDeleteText = textStrings.confirm_delete || 'Вы уверены, что хотите удалить этот купон?';
    const confirmResetText = textStrings.confirm_reset || 'Вы уверены, что хотите сбросить кулдаун этой способности?';


    // Функция для отображения сообщений в админке
    function showAdminMessage(message, isSuccess) {
        const $messageBox = $('#rpg-admin-message-box');
        let $targetForMessageBox = $('#rpg-profile-fields'); // Основной контейнер наших полей

        if ($messageBox.length === 0) {
            if ($targetForMessageBox.length > 0) {
                $targetForMessageBox.before('<div id="rpg-admin-message-box" style="margin-bottom: 15px;"></div>');
            } else {
                // Если нет основного контейнера, ищем h2 и вставляем перед ним
                const $h2 = $('h2:contains("RPG Информация Персонажа")');
                if ($h2.length > 0) {
                    $h2.after('<div id="rpg-admin-message-box" style="margin-bottom: 15px;"></div>');
                } else {
                    // Крайний случай, если ничего не найдено
                    console.error("RPG Admin Message Target not found. Message:", message);
                    alert(message); // Fallback
                    return;
                }
            }
        }
        // Обновляем селектор $messageBox после возможного создания
        const $updatedMessageBox = $('#rpg-admin-message-box');
        const messageClass = isSuccess ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
        // Добавляем кнопку закрытия, если ее нет
        const dismissButton = '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Скрыть это уведомление.</span></button>';
        const messageHtml = `<div class="${messageClass}"><p>${message}</p>${dismissButton}</div>`;
        
        $updatedMessageBox.html(messageHtml).show();
        // Добавляем обработчик для кнопки закрытия, если он еще не был добавлен
        if (!$updatedMessageBox.data('dismiss-handler-added')) {
            $updatedMessageBox.on('click', '.notice-dismiss', function() {
                $(this).closest('.notice').fadeOut();
            });
            $updatedMessageBox.data('dismiss-handler-added', true);
        }
    }


    // Функция для получения иконки купона (копия из PHP Utils для JS)
    function getCouponIconClass(type) {
        switch (String(type).toLowerCase()) {
            case 'daily': return 'dashicons dashicons-calendar-alt'; // Используем Dashicons
            case 'weekly': return 'dashicons dashicons-calendar';
            case 'exclusive_item': return 'dashicons dashicons-star-filled';
            case 'exclusive_cart': return 'dashicons dashicons-cart';
            case 'common': return 'dashicons dashicons-tag';
            default: return 'dashicons dashicons-ticket';
        }
    }
    // Функция для получения метки типа купона
    function getCouponTypeLabel(type) {
        switch (String(type).toLowerCase()) {
            case 'daily': return 'Ежедневный';
            case 'weekly': return 'Еженедельный';
            case 'exclusive_item': return 'Эксклюзивный (на товар)';
            case 'exclusive_cart': return 'Эксклюзивный (на корзину)';
            case 'common': return 'Общий';
            default: return type ? String(type).charAt(0).toUpperCase() + String(type).slice(1) : 'Общий';
        }
    }


    // Добавление RPG купона
    $('#rpg-add-coupon').on('click', function() {
        const $button = $(this);
        const userId = $button.data('userid');
        const couponType = $('#rpg_coupon_type_admin').val(); // Убедитесь, что ID селекта правильный
        const couponValue = $('#rpg_coupon_value_admin').val(); // Убедитесь, что ID инпута правильный

        if (!couponType || !couponValue || parseInt(couponValue, 10) <= 0 || parseInt(couponValue, 10) > 100) {
            showAdminMessage('Пожалуйста, выберите тип и введите корректное значение купона (1-100).', false);
            return;
        }
        $button.prop('disabled', true);
        showAdminMessage('Добавление купона...', true); // Используем isSuccess=true для стиля 'info' или 'updated'

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_admin_add_coupon', // PHP action
                _ajax_nonce: nonce,
                user_id: userId,
                coupon_type: couponType,
                coupon_value: couponValue
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showAdminMessage(response.data?.message || errorGeneric, response.success);
                    if (response.success) {
                        // Динамическое добавление купона в список
                        const $listContainer = $('#coupon-list-container');
                        let $couponList = $listContainer.find('.coupon-list-admin');

                        if ($couponList.length === 0 || $listContainer.find('.no-coupons-message').length > 0) {
                            $listContainer.html('<ul class="coupon-list-admin" style="margin: 0; padding: 0; list-style: none;"></ul>');
                            $couponList = $listContainer.find('.coupon-list-admin');
                        }

                        const newIndex = $couponList.find('li').length;
                        const iconClass = getCouponIconClass(couponType);
                        const typeLabel = getCouponTypeLabel(couponType);
                        const couponDescription = settings.coupon_descriptions && settings.coupon_descriptions[couponType] ?
                                                  settings.coupon_descriptions[couponType] : typeLabel;


                        const newCouponHtml = `
                            <li style="margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; opacity: 0;">
                                <span>
                                    <span class="${iconClass}" style="margin-right: 5px; vertical-align: middle;"></span>
                                    ${couponDescription}: ${couponValue}%
                                </span>
                                <button type="button" class="button button-small rpg-delete-coupon" data-index="${newIndex}" data-userid="${userId}">Удалить</button>
                            </li>`;
                        $(newCouponHtml).appendTo($couponList).animate({ opacity: 1 }, 300);
                        // Очистка полей формы
                        // $('#rpg_coupon_type_admin').val('common');
                        // $('#rpg_coupon_value_admin').val('10');
                    }
                } else {
                    showAdminMessage(errorGeneric + ' (Неверный ответ сервера)', false);
                }
            },
            error: function() {
                showAdminMessage(errorNetwork, false);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Удаление RPG купона
    $('#coupon-list-container').on('click', '.rpg-delete-coupon', function() {
        const $button = $(this);
        const index = $button.data('index');
        const userId = $button.data('userid');

        if (typeof index === 'undefined' || !userId) {
            showAdminMessage(errorGeneric + ' (Отсутствуют данные для удаления)', false);
            return;
        }
        if (!confirm(confirmDeleteText)) {
            return;
        }

        $button.prop('disabled', true);
        const $listItem = $button.closest('li');
        showAdminMessage('Удаление купона...', true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_admin_delete_coupon', // PHP action
                _ajax_nonce: nonce,
                user_id: userId,
                coupon_index: index
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showAdminMessage(response.data?.message || errorGeneric, response.success);
                    if (response.success) {
                        $listItem.fadeOut(300, function() {
                            $(this).remove();
                            const $listContainer = $('#coupon-list-container');
                            if ($listContainer.find('.coupon-list-admin li').length === 0) {
                                if ($listContainer.find('.no-coupons-message').length === 0) {
                                   $listContainer.html('<p class="no-coupons-message" style="margin: 0;">Нет RPG купонов в инвентаре.</p>');
                                }
                            }
                        });
                    }
                } else {
                    showAdminMessage(errorGeneric + ' (Неверный ответ сервера)', false);
                }
            },
            error: function() {
                showAdminMessage(errorNetwork, false);
            },
            complete: function() {
                 // Кнопка уже удалена вместе с li, если успешно
                 if (!$listItem.is(':visible')) { // Проверяем, видим ли элемент (т.е. не удален ли он)
                    $button.prop('disabled', false);
                 }
            }
        });
    });

    // Сброс КД способности
    $('.rpg-reset-ability').on('click', function() {
        const $button = $(this);
        const ability = $button.data('ability');
        const userId = $button.data('userid');

        if (!ability || !userId) {
            showAdminMessage(errorGeneric + ' (Отсутствуют данные для сброса)', false);
            return;
        }
        if (!confirm(confirmResetText)) {
            return;
        }

        $button.prop('disabled', true);
        showAdminMessage('Сброс кулдауна...', true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_admin_reset_ability', // PHP action
                _ajax_nonce: nonce,
                user_id: userId,
                ability: ability
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showAdminMessage(response.data?.message || errorGeneric, response.success);
                    if (response.success) {
                        // Обновляем текст с датой последней активации
                        const $descriptionSpan = $button.next('.description');
                        if ($descriptionSpan.length) {
                           const currentWeekText = $descriptionSpan.text().match(/\(Текущая неделя: [^,]+,/);
                           if(currentWeekText && currentWeekText[0]){
                               $descriptionSpan.text(currentWeekText[0] + " посл. активация: " + (settings.text?.no_activation_yet || 'нет') + ")");
                           }
                        }
                        // Не перезагружаем страницу, чтобы админ мог продолжить работу
                        // location.reload();
                    }
                } else {
                    showAdminMessage(errorGeneric + ' (Неверный ответ сервера)', false);
                }
            },
            error: function() {
                showAdminMessage(errorNetwork, false);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});

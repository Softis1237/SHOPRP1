/**
 * JavaScript для страницы персонажа (/my-account/character)
 * Обрабатывает активацию/деактивацию купонов и способностей, а также toggle для деталей купонов Dokan.
 * Файл: assets/js/rpg-account.js
 */
jQuery(document).ready(function($) {

    const settings = typeof rpg_settings !== 'undefined' ? rpg_settings : {};
    const ajaxUrl = settings.ajax_url || '/wp-admin/admin-ajax.php';
    const nonce = settings.nonce || ''; 
    const textStrings = settings.text || {};
    const errorNetwork = textStrings.error_network || 'Ошибка сети. Пожалуйста, попробуйте еще раз.';
    const errorGeneric = textStrings.error_generic || 'Произошла непредвиденная ошибка.';
    const confirmDeactivateText = textStrings.confirm_deactivate || 'Вы уверены, что хотите деактивировать этот купон?';
    const confirmClearInvalidDokanText = textStrings.confirm_clear_invalid_dokan || 'Вы уверены, что хотите удалить все недействительные купоны продавцов из вашего инвентаря? Это действие необратимо.';

    function showGlobalMessage(message, type = 'info') {
        const $messageBox = $('.rpg-message-box.глобальное-сообщение');
        if ($messageBox.length === 0) {
            console.error("Global message box not found. Message:", message);
            alert(message); 
            return;
        }
        $messageBox.html(message)
            .removeClass('success error info')
            .addClass(type)
            .stop(true, true)
            .slideDown(300);

        if (type !== 'info' && type !== 'error') { 
            setTimeout(function() { $messageBox.slideUp(300); }, 7000);
        } else if (type === 'info') {
            setTimeout(function() { $messageBox.slideUp(300); }, 3000);
        }
    }

    function showSpecificMessage(selector, message, type = 'info') {
        const $messageBox = $(selector);
        if ($messageBox.length === 0) {
            showGlobalMessage(message, type); 
            return;
        }
        $messageBox.html(message)
            .removeClass('success error info')
            .addClass(type)
            .stop(true, true)
            .slideDown(300);
        if (type !== 'info' && type !== 'error') {
            setTimeout(function() { $messageBox.slideUp(300); }, 7000);
        } else if (type === 'info') {
            setTimeout(function() { $messageBox.slideUp(300); }, 3000);
        }
    }

    // Активация RPG купонов
    $('.activate-rpg-coupon').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const index = $button.data('index');

        if (typeof index === 'undefined') {
            showSpecificMessage('.rpg-message-box.rpg-coupons-msg', errorGeneric, 'error');
            return;
        }
        $button.prop('disabled', true).text('Активация...');
        showSpecificMessage('.rpg-message-box.rpg-coupons-msg', 'Активация купона...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'use_rpg_coupon', index: index, _ajax_nonce: nonce },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showSpecificMessage('.rpg-message-box.rpg-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Активировать');
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.rpg-coupons-msg', errorGeneric + ' (Неверный ответ сервера)', 'error');
                    $button.prop('disabled', false).text('Активировать');
                }
            },
            error: function() {
                showSpecificMessage('.rpg-message-box.rpg-coupons-msg', errorNetwork, 'error');
                $button.prop('disabled', false).text('Активировать');
            }
        });
    });

    // Активация способностей (Эльфы и Орки)
    $('#activate-elf-sense, #activate-orc-rage').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const action = $button.attr('id') === 'activate-elf-sense' ? 'activate_elf_sense' : 'activate_orc_rage';
        const originalText = $button.text();
        $button.prop('disabled', true).text('Активация...');
        showGlobalMessage('Активация способности...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: action, _ajax_nonce: nonce },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showGlobalMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $button.prop('disabled', false).text(originalText);
                    }
                } else {
                    showGlobalMessage(errorGeneric + ' (Неверный ответ сервера)', 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                showGlobalMessage(errorNetwork, 'error');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Взять купон Dokan
    $(document).on('click', '.rpg-take-dokan-coupon-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const couponId = $button.data('coupon-id');
        if (!couponId) {
            console.error('RPG Dokan: Coupon ID not found for "take" button.');
            let $msgBoxTarget = $button.closest('.store-coupon-item-rpg, .dokan-coupon-item');
            if ($msgBoxTarget.length) {
                let $msgBox = $msgBoxTarget.find('.rpg-dokan-coupon-message');
                if ($msgBox.length === 0) {
                    $button.after('<div class="rpg-dokan-coupon-message" style="font-size:0.9em; margin-top:5px; display:none;"></div>');
                    $msgBox = $button.siblings('.rpg-dokan-coupon-message');
                }
                $msgBox.text(errorGeneric + ' (ID купона не найден)').addClass('error').slideDown();
            } else {
                showGlobalMessage(errorGeneric + ' (ID купона не найден)', 'error');
            }
            return;
        }
        $button.prop('disabled', true).text('Обработка...');
        let $msgBox = $button.siblings('.rpg-dokan-coupon-message');
        if ($msgBox.length === 0) {
            $msgBox = $button.closest('.store-coupon-item-rpg, .dokan-coupon-item').find('.rpg-dokan-coupon-message');
        }
        if ($msgBox.length === 0) {
            $button.after('<div class="rpg-dokan-coupon-message" style="font-size:0.9em; margin-top:5px; display:none;"></div>');
            $msgBox = $button.siblings('.rpg-dokan-coupon-message');
        }
        $msgBox.hide().removeClass('success error info').text('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'rpg_take_dokan_coupon', coupon_id: couponId, _ajax_nonce: nonce },
            success: function(response) {
                if (response && typeof response === 'object') {
                    $msgBox.text(response.data?.message || errorGeneric).addClass(response.success ? 'success' : 'error').slideDown();
                    if (response.success) {
                        $button.text('Уже в инвентаре');
                        if ($button.closest('.rpg-character-page').length || response.data?.reload_page) {
                            setTimeout(function() { location.reload(); }, 1500);
                        }
                    } else {
                        $button.prop('disabled', false).text('Взять купон');
                    }
                } else {
                    $msgBox.text(errorGeneric + ' (Неверный ответ)').addClass('error').slideDown();
                    $button.prop('disabled', false).text('Взять купон');
                }
            },
            error: function() {
                $msgBox.text(errorNetwork).addClass('error').slideDown();
                $button.prop('disabled', false).text('Взять купон');
            }
        });
    });

    // Добавить купон Dokan по коду
    $('#rpg-add-dokan-coupon-by-code-btn').on('click', function() {
        const $button = $(this);
        const couponCode = $('#dokan-coupon-code-input').val().trim();
        if (!couponCode) {
            showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Пожалуйста, введите код купона.', 'error');
            return;
        }
        $button.prop('disabled', true).text('Добавление...');
        showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Добавление купона...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'rpg_add_dokan_coupon_by_code', coupon_code: couponCode, _ajax_nonce: nonce },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success && response.data?.reload_page) {
                        $('#dokan-coupon-code-input').val('');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else if (response.success) {
                        $('#dokan-coupon-code-input').val('');
                    } else {
                        $button.prop('disabled', false).text('Добавить в инвентарь');
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorGeneric + ' (Неверный ответ)', 'error');
                    $button.prop('disabled', false).text('Добавить в инвентарь');
                }
            },
            error: function() {
                showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorNetwork, 'error');
                $button.prop('disabled', false).text('Добавить в инвентарь');
            }
        });
    });

    // Активировать купон Dokan из инвентаря
    $(document).on('click', '.activate-dokan-coupon', function() {
        const $button = $(this);
        const couponId = $button.closest('.dokan-coupon-item').data('coupon-id');
        const originalButtonText = $button.data('original-text') || 'Активировать';
        if (typeof couponId === 'undefined' || !couponId) {
            showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Не удалось определить ID купона для активации.', 'error');
            return;
        }
        $button.prop('disabled', true).text('Активация...');
        showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Активация купона продавца...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'rpg_activate_dokan_coupon_from_inventory', dokan_coupon_id_to_activate: couponId, _ajax_nonce: nonce },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success && response.data?.reload_page) {
                        setTimeout(function() { location.reload(); }, 1500);
                    } else if (response.success) {
                        $button.text(textStrings.already_active || 'Уже активен');
                    } else {
                        if (response.data?.removed_coupon_ids && Array.isArray(response.data.removed_coupon_ids)) {
                            response.data.removed_coupon_ids.forEach(function(removedId) {
                                $('.dokan-coupon-item[data-coupon-id="' + removedId + '"]').fadeOut(300, function() { $(this).remove(); });
                            });
                        }
                        $button.prop('disabled', false).text(originalButtonText);
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorGeneric + ' (Неверный ответ)', 'error');
                    $button.prop('disabled', false).text(originalButtonText);
                }
            },
            error: function() {
                showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorNetwork, 'error');
                $button.prop('disabled', false).text(originalButtonText);
            }
        });
    });

    // Обновить статус и Очистить невалидные купоны Dokan
    $('#rpg-refresh-dokan-coupons-status, #rpg-clear-invalid-dokan-coupons-btn').on('click', function() {
        const $button = $(this);
        const action = $button.attr('id') === 'rpg-clear-invalid-dokan-coupons-btn' ? 'rpg_clear_invalid_dokan_coupons' : 'rpg_refresh_vendor_coupons_status';
        const originalText = $button.text();
        const confirmText = action === 'rpg_clear_invalid_dokan_coupons' ? confirmClearInvalidDokanText : null;

        if (confirmText && !confirm(confirmText)) {
            return;
        }
        $button.prop('disabled', true).text(action === 'rpg_clear_invalid_dokan_coupons' ? 'Очистка...' : 'Обновление...');
        showSpecificMessage('.rpg-message-box.dokan-coupons-msg', action === 'rpg_clear_invalid_dokan_coupons' ? 'Удаление недействительных купонов продавцов...' : 'Проверка статуса купонов продавцов...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: action, _ajax_nonce: nonce },
            success: function(response) {
                if (response && typeof response === 'object' && typeof response.success !== 'undefined') {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success && response.data?.removed_coupon_ids && Array.isArray(response.data.removed_coupon_ids) && response.data.removed_coupon_ids.length > 0) {
                        response.data.removed_coupon_ids.forEach(function(removedId) {
                            $('.dokan-coupon-item[data-coupon-id="' + removedId + '"]').fadeOut(300, function() { $(this).remove(); });
                        });
                        setTimeout(function() {
                            const $grid = $('.dokan-coupon-inventory-grid');
                            if ($grid.find('.dokan-coupon-item').length === 0 && $grid.find('.no-coupons-message').length === 0) {
                                $grid.html('<p class="no-coupons-message">' + (textStrings.inventory_empty || 'Ваш инвентарь купонов продавцов пуст.') + '</p>');
                            }
                        }, 350);
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorGeneric + ' (Неверный ответ)', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorNetwork, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Деактивация активного купона
    $(document).on('click', '.rpg-deactivate-coupon-btn', function() {
        const $button = $(this);
        const couponType = $button.data('coupon-type');
        if (!couponType) {
            showGlobalMessage('Не удалось определить тип купона для деактивации.', 'error');
            return;
        }
        if (!confirm(confirmDeactivateText)) {
            return;
        }
        $button.prop('disabled', true).text('Деактивация...');
        showGlobalMessage('Деактивация купона...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'deactivate_rpg_coupon', coupon_type: couponType, _ajax_nonce: nonce },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showGlobalMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Вернуть в инвентарь');
                    }
                } else {
                    showGlobalMessage(errorGeneric + ' (Неверный ответ)', 'error');
                    $button.prop('disabled', false).text('Вернуть в инвентарь');
                }
            },
            error: function() {
                showGlobalMessage(errorNetwork, 'error');
                $button.prop('disabled', false).text('Вернуть в инвентарь');
            }
        });
    });

    // Toggle для деталей купона Dokan
    $(document).on('click', '.rpg-coupon-details-toggle', function(e) {
        e.preventDefault();
        const couponId = $(this).data('coupon-id');
        const $detailsDiv = $('#details-' + couponId);
        if ($detailsDiv.length) {
            $detailsDiv.slideToggle(200);
            $(this).text($detailsDiv.is(':visible') ? (textStrings.hide_details || 'Скрыть детали') : (textStrings.show_details || 'Подробнее'));
        }
    });

});
/**
 * JavaScript for the cart and checkout pages (/cart, /checkout)
 * Handles item selection for abilities and the custom coupon input field.
 * File: assets/js/rpg-cart-checkout.js
 */
jQuery(document).ready(function($) {

    // Используем rpg_page_params, который будет разным для cart и checkout
    const params = typeof rpg_page_params !== 'undefined' ? rpg_page_params : {};
    const ajaxUrl = params.ajax_url || '/wp-admin/admin-ajax.php';
    // Nonce теперь берется из current_page_nonce, который устанавливается в PHP
    const currentNonce = params.current_page_nonce || ''; 
    const isCheckoutPage = params.is_checkout_page || false;

    const textStrings = params.text || {};
    const errorNetwork = textStrings.error_network || 'Ошибка сети. Пожалуйста, попробуйте еще раз.';
    const errorGeneric = textStrings.error_generic || 'Произошла непредвиденная ошибка.';
    const errorElfSelect = textStrings.confirm_elf_select || 'Выберите хотя бы один товар.';
    const errorCouponEmpty = textStrings.error_coupon_empty || 'Пожалуйста, введите код купона.';
    const applyingCouponText = textStrings.applying_coupon || 'Применение...';
    // Для кнопки "Применить купон" текст будет разный на cart и checkout из-за стандартного WC
    const applyCouponButtonTextDefault = isCheckoutPage ? 
                                        (textStrings.apply_coupon_btn_checkout || 'Применить купон') : 
                                        (textStrings.apply_coupon_btn || 'Применить купон');


    function showPageMessage(message, type = 'info', $container) {
        let $messageBox;
        // На странице checkout уведомления обычно выводятся в .woocommerce-notices-wrapper наверху
        // На странице cart у нас есть .rpg-cart-coupon-message
        const $checkoutNotices = $('.woocommerce-notices-wrapper').first();
        const $cartCouponMessage = $('.rpg-cart-coupon-message').first();

        if ($container && $container.length) {
            $messageBox = $container;
        } else if (isCheckoutPage && $checkoutNotices.length) {
            $messageBox = $checkoutNotices;
        } else if (!isCheckoutPage && $cartCouponMessage.length) {
            $messageBox = $cartCouponMessage;
        } else if ($checkoutNotices.length) { // Fallback to notices wrapper if specific not found
             $messageBox = $checkoutNotices;
        } else {
            // Если ничего не найдено, создаем .woocommerce-notices-wrapper
            const $formTarget = isCheckoutPage ? $('form.checkout') : $('form.woocommerce-cart-form');
            if ($formTarget.length) {
                $formTarget.before('<div class="woocommerce-notices-wrapper"></div>');
            } else {
                $('body').prepend('<div class="woocommerce-notices-wrapper"></div>');
            }
            $messageBox = $('.woocommerce-notices-wrapper:first');
        }
        
        // Очистка предыдущих сообщений
        if ($messageBox.hasClass('rpg-cart-coupon-message')) {
            $messageBox.empty().removeClass('success error info');
        } else { // Для .woocommerce-notices-wrapper
            $messageBox.find('.woocommerce-message, .woocommerce-error, .woocommerce-info, .rpg-message').remove();
        }

        // Формирование и добавление нового сообщения
        let messageClass = '';
        if ($messageBox.hasClass('rpg-cart-coupon-message')) {
             messageClass = type; // success, error, info
             $messageBox.html(message).addClass(messageClass).stop(true, true).slideDown(300);
        } else { // Для .woocommerce-notices-wrapper
            messageClass = `woocommerce-${type === 'success' ? 'message' : (type === 'error' ? 'error' : 'info')}`;
            // Добавляем класс rpg-message для возможности кастомной стилизации, если нужно
            const messageHtml = `<div class="${messageClass} rpg-message" role="alert">${message}</div>`; 
            $messageBox.prepend(messageHtml); // Prepend, чтобы новые сообщения были сверху
            $messageBox.show();
        }

        if (type !== 'error') { 
            setTimeout(function() { 
                if ($messageBox.hasClass('rpg-cart-coupon-message')) {
                    $messageBox.slideUp(300, function() { $(this).empty(); });
                }
                // Стандартные уведомления WC обычно не нужно скрывать JS, если они не наши кастомные
                // Но если мы добавили .rpg-message, можно его скрыть
                else if ($messageBox.find('.rpg-message').length) {
                     $messageBox.find('.rpg-message').slideUp(300, function() { $(this).remove(); });
                }
            }, 7000);
        }
    }

    // --- Логика для корзины (способности эльфов/орков) ---
    if (!isCheckoutPage) { 
        $('#confirm-elf-sense').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const $form = $('#elf-sense-form'); 
            if ($form.length === 0) {
                showPageMessage('Ошибка: Форма выбора для "Чутья" не найдена.', 'error');
                return;
            }
            const $checkedCheckboxes = $form.find('input[name="elf_products[]"]:checked');
            const productIds = $checkedCheckboxes.map(function() { return $(this).val(); }).get();

            if (productIds.length === 0) {
                showPageMessage(errorElfSelect, 'error');
                return;
            }
            const maxItems = params.elf_sense_max_items || 1; 
            if (productIds.length > maxItems) {
                showPageMessage( (textStrings.error_elf_max_items || 'Превышен лимит товаров для "Чутья" (максимум %d).').replace('%d', maxItems) , 'error');
                return;
            }
            $button.prop('disabled', true).text('Подтверждение...');
            $checkedCheckboxes.prop('disabled', true);
            $.ajax({ /* ... AJAX для select_elf_items ... */ 
                url: ajaxUrl, type: 'POST',
                data: { action: 'select_elf_items', product_ids: productIds, _ajax_nonce: currentNonce },
                success: function(response) {
                    if (response && typeof response === 'object') {
                        showPageMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                        if (response.success) { $(document.body).trigger('wc_update_cart'); $(document.body).trigger('update_checkout'); }
                        else { $button.prop('disabled', false).text('Подтвердить выбор для "Чутья"'); $checkedCheckboxes.prop('disabled', false); }
                    } else { showPageMessage(errorGeneric + ' (Неверный ответ сервера)', 'error'); $button.prop('disabled', false).text('Подтвердить выбор для "Чутья"'); $checkedCheckboxes.prop('disabled', false); }
                },
                error: function() { showPageMessage(errorNetwork, 'error'); $button.prop('disabled', false).text('Подтвердить выбор для "Чутья"'); $checkedCheckboxes.prop('disabled', false); }
            });
        });

        $(document).on('click', '.select-rage-product', function(e) {
            e.preventDefault();
            const $button = $(this);
            const productId = $button.data('product-id');
            if (!productId) { showPageMessage(errorGeneric + ' (ID товара не найден)', 'error'); return; }
            $('.select-rage-product').prop('disabled', true).text('Выбор...');
            $button.text('Выбран!'); 
            $.ajax({ /* ... AJAX для select_orc_rage_product ... */ 
                url: ajaxUrl, type: 'POST',
                data: { action: 'select_orc_rage_product', product_id: productId, _ajax_nonce: currentNonce },
                success: function(response) {
                    if (response && typeof response === 'object') {
                        showPageMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                        if (response.success) { $(document.body).trigger('wc_update_cart'); $(document.body).trigger('update_checkout'); }
                        else { $('.select-rage-product').prop('disabled', false).text('Выбрать для "Ярости"'); }
                    } else { showPageMessage(errorGeneric + ' (Неверный ответ сервера)', 'error'); $('.select-rage-product').prop('disabled', false).text('Выбрать для "Ярости"'); }
                },
                error: function() { showPageMessage(errorNetwork, 'error'); $('.select-rage-product').prop('disabled', false).text('Выбрать для "Ярости"');}
            });
        });
    } // Конец if (!isCheckoutPage)

    // --- Общая логика для применения купона (используется #rpg_apply_cart_coupon_button) ---
    // Этот ID используется и в cart.php и в form-coupon.php (checkout)
    $(document).on('click', '#rpg_apply_cart_coupon_button', function(event) {
        event.preventDefault(); 
        
        console.log('[RPG Coupon Apply] Button clicked. Is checkout:', isCheckoutPage); 
        const $button = $(this); 
        const $input = $('#rpg_cart_coupon_code'); // ID поля ввода одинаковый
        const couponCode = $input.val().trim();
        // Контейнер для сообщений может быть разным
        const $messageContainer = isCheckoutPage ? 
                                  $button.closest('.rpg-custom-coupon-form-cart').find('.rpg-cart-coupon-message') : 
                                  $('.rpg-cart-coupon-message'); // На странице корзины он один

        if ($messageContainer.length === 0) {
            console.error('[RPG Coupon Apply] Message container not found!');
        }
        $messageContainer.hide().empty().removeClass('success error info');

        if (!couponCode) {
            showPageMessage(errorCouponEmpty, 'error', $messageContainer);
            $input.focus();
            return;
        }

        console.log('[RPG Coupon Apply] Disabling button and input.'); 
        $button.prop('disabled', true).text(applyingCouponText);
        $input.prop('disabled', true); 

        const ajaxAction = isCheckoutPage ? 'rpg_apply_dokan_coupon_on_checkout' : 'rpg_apply_dokan_coupon_in_cart';
        console.log('[RPG Coupon Apply] AJAX Action:', ajaxAction, 'Nonce:', currentNonce);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: ajaxAction,
                coupon_code: couponCode,
                _ajax_nonce: currentNonce 
            },
            success: function(response) {
                console.log('[RPG Coupon Apply] AJAX success:', response); 
                if (response && typeof response === 'object') {
                    showPageMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error', $messageContainer);
                    if (response.success) {
                        console.log('[RPG Coupon Apply] Coupon applied successfully. Triggering updates.'); 
                        $input.val(''); 
                        
                        // Обновляем фрагменты и итоги
                        $(document.body).trigger('wc_fragment_refresh'); 
                        $(document.body).trigger('update_checkout'); // Это должно обновить и корзину, и checkout   
                        if (!isCheckoutPage) { // Для корзины дополнительно
                           $(document.body).trigger('wc_update_cart'); 
                        }
                    }
                } else {
                    console.log('[RPG Coupon Apply] AJAX success but invalid response.'); 
                    showPageMessage(errorGeneric + ' (Неверный ответ сервера)', 'error', $messageContainer);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('[RPG Coupon Apply] AJAX error:', textStatus, errorThrown, jqXHR.responseText); 
                showPageMessage(errorNetwork, 'error', $messageContainer);
            },
            complete: function() {
                console.log('[RPG Coupon Apply] AJAX complete. Re-enabling form elements.');
                const $currentButton = $('#rpg_apply_cart_coupon_button'); 
                const $currentInput = $('#rpg_cart_coupon_code');    

                if ($currentButton.length) {
                    $currentButton.prop('disabled', false).text(applyCouponButtonTextDefault);
                }
                if ($currentInput.length) {
                    $currentInput.prop('disabled', false);
                }
            }
        });
    });
});

<?php
/**
 * Custom Login Form for Woodmart Child Theme
 * Overrides the default WooCommerce form-login.php
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 9.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Получаем настройки темы Woodmart
$tabs         = woodmart_get_opt( 'login_tabs' ); // Включены ли вкладки (true/false)
$reg_text     = woodmart_get_opt( 'reg_text' ); // Текст для вкладки регистрации
$login_text   = woodmart_get_opt( 'login_text' ); // Текст для вкладки входа
$account_link = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ); // Ссылка на страницу "Мой аккаунт"

// Формируем классы для основного контейнера
$class  = 'wd-registration-page';
$class .= woodmart_get_old_classes( ' woodmart-registration-page' ); // Поддержка старых классов для обратной совместимости

// Если вкладки включены и регистрация разрешена, подключаем скрипт переключения вкладок
if ( $tabs && get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) {
    woodmart_enqueue_js_script( 'login-tabs' ); // Подключаем скрипт Woodmart для переключения вкладок
    $class .= ' wd-register-tabs';
    $class .= woodmart_get_old_classes( ' woodmart-register-tabs' );
}

// Если регистрация отключена, добавляем класс
if ( get_option( 'woocommerce_enable_myaccount_registration' ) !== 'yes' ) {
    $class .= ' wd-no-registration';
}

// Если есть текст для вкладок, добавляем класс
if ( $login_text && $reg_text ) {
    $class .= ' with-login-reg-info';
}

// Если в URL есть параметр ?action=register, делаем вкладку "Register" активной
if ( isset( $_GET['action'] ) && 'register' === $_GET['action'] && $tabs ) {
    $class .= ' active-register';
}

// Подключаем стили Woodmart для форм
woodmart_enqueue_inline_style( 'woo-mod-login-form' );
woodmart_enqueue_inline_style( 'woo-page-login-register' );

// Хук WooCommerce: выполняется перед формой
do_action( 'woocommerce_before_customer_login_form' ); ?>

<div class="<?php echo esc_attr( $class ); ?>">

<?php if ( get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) : ?>

    <div class="wd-login-container">

        <div class="wd-login-form col-login">
            <h2 class="wd-login-title"><?php esc_html_e( 'Login', 'woocommerce' ); ?></h2>
            <?php
            // Выводим форму входа (функция Woodmart)
            woodmart_login_form( true, add_query_arg( 'action', 'login', $account_link ) );
            ?>

            <?php if ( $tabs ) : ?>
                <?php
                // Заголовки для вкладок из настроек темы
                $reg_title   = woodmart_get_opt( 'reg_title' ) ? woodmart_get_opt( 'reg_title' ) : esc_html__( 'Register', 'woocommerce' );
                $login_title = woodmart_get_opt( 'login_title' ) ? woodmart_get_opt( 'login_title' ) : esc_html__( 'Login', 'woocommerce' );

                // Текст кнопки переключения: "Register" или "Login"
                $button_text = esc_html__( 'Register', 'woocommerce' );

                if ( isset( $_GET['action'] ) && 'register' === $_GET['action'] ) {
                    $button_text = esc_html__( 'Login', 'woocommerce' );
                }
                ?>
                <div class="wd-switch-container">
                    <a href="#" rel="nofollow noopener" class="btn wd-switch-to-register" data-login="<?php esc_html_e( 'Login', 'woocommerce' ); ?>" data-login-title="<?php echo esc_attr( $login_title ); ?>" data-reg-title="<?php echo esc_attr( $reg_title ); ?>" data-register="<?php esc_html_e( 'Register', 'woocommerce' ); ?>"><?php echo esc_html( $button_text ); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="wd-register-form col-register">
            <?php echo do_shortcode('[html_block id="2605"]'); // Вставляем HTML-блок из Elementor для формы регистрации ?>
        </div>

    </div>

<?php endif; ?>

</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); // Хук WooCommerce: выполняется после формы ?>
<?php
/**
 * Шорткод для отображения кастомной формы регистрации WooCommerce.
 *
 * @package WoodmartChildRPG\Shortcodes
 */

namespace WoodmartChildRPG\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class RegisterFormShortcode {

	/**
	 * Рендерит HTML для шорткода [custom_register_form].
	 * Логика взята из вашей функции custom_register_form_shortcode().
	 *
	 * @param array $atts Атрибуты шорткода.
	 * @return string HTML формы регистрации.
	 */
	public static function render( $atts = array() ) {
		if ( is_user_logged_in() ) {
			return '';
		}

		// Валидация на стороне сервера (ваш JS уже должен делать это на клиенте)
		// Эта PHP валидация сработает, если JS отключен или обойден.
		// Она добавляет wc_add_notice, которые WooCommerce обычно отображает.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['register'], $_POST['woocommerce-register-nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-register-nonce'] ) ), 'woocommerce-register' ) ) {
				$selected_gender = isset( $_POST['selected_gender'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_gender'] ) ) : '';
				$selected_race   = isset( $_POST['selected_race'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_race'] ) ) : '';

				if ( empty( $selected_gender ) ) {
					wc_add_notice( __( 'Пожалуйста, выберите гендер.', 'woodmart-child' ), 'error' );
				}
				if ( empty( $selected_race ) ) {
					wc_add_notice( __( 'Пожалуйста, выберите расу.', 'woodmart-child' ), 'error' );
				}
				// WooCommerce сам обработает остальные поля формы регистрации.
				// Если wc_add_notice был вызван, WooCommerce обычно не создает пользователя и показывает ошибки.
			}
		}


		// Получаем заголовки для вкладок из настроек темы Woodmart (если они нужны здесь)
		// Эта часть кода была в вашем старом шорткоде, но она больше относится к шаблону form-login.php,
		// где происходит переключение вкладок. Сам шорткод формы не должен об этом заботиться.
		// $reg_title   = woodmart_get_opt( 'reg_title' ) ? woodmart_get_opt( 'reg_title' ) : esc_html__( 'Register', 'woocommerce' );
		// $login_title = woodmart_get_opt( 'login_title' ) ? woodmart_get_opt( 'login_title' ) : esc_html__( 'Login', 'woocommerce' );


		ob_start();
		?>
		<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >
			<?php do_action( 'woocommerce_register_form_start' ); ?>

			<input type="hidden" name="selected_gender" value="<?php echo esc_attr( isset( $_POST['selected_gender'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_gender'] ) ) : '' ); ?>">
			<input type="hidden" name="selected_race" value="<?php echo esc_attr( isset( $_POST['selected_race'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_race'] ) ) : '' ); ?>">

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required />
				</p>
			<?php endif; ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required />
			</p>

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
					<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required />
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?></p>
			<?php endif; ?>

			<?php do_action( 'woocommerce_register_form' ); ?>

			<p class="woocommerce-form-row form-row">
				<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
				<button type="submit" class="woocommerce-Button woocommerce-button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
			</p>

			<?php do_action( 'woocommerce_register_form_end' ); ?>
		</form>
		<?php
		// Кнопка переключения на логин была в вашем старом шорткоде,
		// но она больше относится к шаблону form-login.php и его JS-логике переключения вкладок.
		// Если ваш HTML блок Elementor не содержит этой кнопки, и она нужна именно здесь,
		// то ее можно оставить. Но обычно она является частью общего UI страницы входа/регистрации.
		// <div class="wd-switch-container">
		// <a href="#" rel="nofollow noopener" class="btn wd-switch-to-register" ... >...</a>
		// </div>
		return ob_get_clean();
	}
}
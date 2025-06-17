<?php
/**
 * Шорткод для отображения выбора пола.
 *
 * @package WoodmartChildRPG\Shortcodes
 */

namespace WoodmartChildRPG\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class GenderSelectShortcode {

	/**
	 * Рендерит HTML для шорткода [gender_select].
	 * Логика взята из вашей функции custom_gender_select_shortcode().
	 *
	 * @param array $atts Атрибуты шорткода.
	 * @return string HTML для выбора пола.
	 */
	public static function render( $atts = array() ) {
		if ( is_user_logged_in() ) {
			// Обычно на странице регистрации неавторизованные пользователи,
			// но на всякий случай.
			return '';
		}

		// Атрибуты по умолчанию и объединение с переданными
		$atts = shortcode_atts(
			array(
				'default_gender' => 'male', // Можно добавить атрибут для шорткода
			),
			$atts,
			'gender_select'
		);

		$male_checked   = checked( $atts['default_gender'], 'male', false );
		// Если default_gender не male, то female будет checked (так как всего два варианта)
		// или можно добавить более сложную логику, если default_gender может быть пустым.
		$female_checked = checked( $atts['default_gender'], 'female', false );
		if ( 'male' !== $atts['default_gender'] && 'female' !== $atts['default_gender'] ) {
			// Если значение по умолчанию некорректно, пусть будет male по умолчанию
			$male_checked = ' checked="checked"';
		}


		$output = '<div class="gender-select">'; // Класс используется в вашем login-register.css и login-register.js
		// Важно: name="gender" используется в вашем login-register.js для обновления скрытого поля selected_gender.
		$output .= '<label class="gender-option"><input type="radio" name="gender" value="male"' . $male_checked . '><span>' . esc_html__( 'Мужчина', 'woodmart-child' ) . '</span></label>';
		$output .= '<label class="gender-option"><input type="radio" name="gender" value="female"' . $female_checked . '><span>' . esc_html__( 'Женщина', 'woodmart-child' ) . '</span></label>';
		$output .= '</div>';

		return $output;
	}
}

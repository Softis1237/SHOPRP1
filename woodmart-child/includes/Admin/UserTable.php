<?php
/**
 * Настраивает таблицу пользователей в админ-панели (колонки, сортировка, фильтры).
 *
 * @package WoodmartChildRPG\Admin
 */

namespace WoodmartChildRPG\Admin;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\RPG\RaceFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class UserTable {

	private $character_manager;

	public function __construct( RPGCharacter $character_manager ) {
		$this->character_manager = $character_manager;
	}

	/**
	 * Добавляет кастомные колонки в таблицу пользователей.
	 * Аналог rpg_add_user_table_columns().
	 *
	 * @param array $columns Существующие колонки.
	 * @return array Обновленные колонки.
	 */
	public function add_columns( $columns ) {
		$columns['wcrpg_gender']       = __( 'Гендер', 'woodmart-child' );
		$columns['wcrpg_race']         = __( 'Раса', 'woodmart-child' );
		$columns['wcrpg_level']        = __( 'Уровень', 'woodmart-child' );
		$columns['wcrpg_total_spent']  = __( 'Потрачено ($)', 'woodmart-child' );
		return $columns;
	}

	/**
	 * Отображает данные для кастомных колонок.
	 * Аналог rpg_show_user_table_column_data().
	 *
	 * @param string $output      HTML для вывода (обычно пустой).
	 * @param string $column_name Имя текущей колонки.
	 * @param int    $user_id     ID пользователя.
	 * @return string HTML для ячейки.
	 */
	public function render_column_data( $output, $column_name, $user_id ) {
		switch ( $column_name ) {
			case 'wcrpg_gender':
				$gender = $this->character_manager->get_gender( $user_id );
				return $gender ? ( 'male' === $gender ? __( 'Муж.', 'woodmart-child' ) : __( 'Жен.', 'woodmart-child' ) ) : '&ndash;';
			case 'wcrpg_race':
				$race_slug = $this->character_manager->get_race( $user_id );
				$race_obj  = RaceFactory::create_race( $race_slug );
				return $race_obj ? esc_html( $race_obj->get_name() ) : ( $race_slug ? ucfirst( $race_slug ) : '&ndash;' );
			case 'wcrpg_level':
				return esc_html( $this->character_manager->get_level( $user_id ) );
			case 'wcrpg_total_spent':
				return esc_html( number_format_i18n( $this->character_manager->get_total_spent( $user_id ), 2 ) );
		}
		return $output;
	}

	/**
	 * Делает кастомные колонки сортируемыми.
	 * Аналог rpg_make_user_table_columns_sortable().
	 *
	 * @param array $columns Массив сортируемых колонок.
	 * @return array Обновленный массив.
	 */
	public function make_columns_sortable( $columns ) {
		$columns['wcrpg_race']        = 'wcrpg_race_sort';
		$columns['wcrpg_level']       = 'wcrpg_level_sort';
		$columns['wcrpg_total_spent'] = 'wcrpg_total_spent_sort';
		return $columns;
	}

	/**
	 * Обрабатывает сортировку по кастомным колонкам.
	 * Аналог rpg_user_table_custom_orderby().
	 *
	 * @param \WP_User_Query $query Объект запроса пользователей.
	 */
	public function custom_orderby( \WP_User_Query $query ) {
		if ( ! is_admin() || ! isset( $query->query_vars['orderby'] ) ) {
			return;
		}
		$orderby = $query->query_vars['orderby'];
		switch ( $orderby ) {
			case 'wcrpg_race_sort':
				$query->set( 'meta_key', RPGCharacter::META_PREFIX . 'race' );
				$query->set( 'orderby', 'meta_value' );
				break;
			case 'wcrpg_level_sort':
				$query->set( 'meta_key', RPGCharacter::META_PREFIX . 'level' );
				$query->set( 'orderby', 'meta_value_num' );
				break;
			case 'wcrpg_total_spent_sort':
				$query->set( 'meta_key', RPGCharacter::META_PREFIX . 'total_spent' );
				$query->set( 'orderby', 'meta_value_num' );
				break;
		}
	}

	/**
	 * Добавляет фильтр по расе в таблицу пользователей.
	 * Аналог rpg_add_race_filter_to_user_table().
	 *
	 * @param string $which Позиция фильтра ('top' или 'bottom').
	 */
	public function display_race_filter( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_race_filter = isset( $_GET['wcrpg_race_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['wcrpg_race_filter'] ) ) : '';
		// phpcs:enable
		?>
		<label for="wcrpg_race_filter" class="screen-reader-text"><?php esc_html_e( 'Фильтр по расе', 'woodmart-child' ); ?></label>
		<select name="wcrpg_race_filter" id="wcrpg_race_filter" style="float:none; margin:0 5px;">
			<option value=""><?php esc_html_e( 'Все расы', 'woodmart-child' ); ?></option>
			<?php
			$available_races = RaceFactory::get_available_races();
			foreach ( $available_races as $slug => $name ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $current_race_filter, $slug, false ), esc_html( $name ) );
			}
			?>
		</select>
		<?php
		submit_button( __( 'Фильтр', 'woodmart-child' ), 'secondary', 'wcrpg_filter_action', false );
	}

	/**
	 * Применяет фильтр по расе к запросу пользователей.
	 * Аналог rpg_apply_race_filter_to_user_query().
	 *
	 * @param \WP_User_Query $query Объект запроса пользователей.
	 */
	public function apply_race_filter( \WP_User_Query $query ) {
		global $pagenow;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! ( is_admin() && 'users.php' === $pagenow && isset( $_GET['wcrpg_race_filter'] ) && ! empty( $_GET['wcrpg_race_filter'] ) ) ) {
			return;
		}
		$race_filter = sanitize_text_field( wp_unslash( $_GET['wcrpg_race_filter'] ) );
		// phpcs:enable

		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}
		$meta_query[] = array(
			'key'     => RPGCharacter::META_PREFIX . 'race',
			'value'   => $race_filter,
			'compare' => '=',
		);
		$query->set( 'meta_query', $meta_query );
	}
}
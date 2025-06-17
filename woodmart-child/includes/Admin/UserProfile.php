<?php
/**
 * Управляет RPG-полями на странице профиля пользователя в админ-панели.
 *
 * @package WoodmartChildRPG\Admin
 */

namespace WoodmartChildRPG\Admin;

use WoodmartChildRPG\RPG\Character as RPGCharacter;
use WoodmartChildRPG\RPG\RaceFactory; // Для получения списка рас
use WoodmartChildRPG\RPG\LevelManager; // Для получения макс. уровня

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class UserProfile {

	private $character_manager;

	public function __construct( RPGCharacter $character_manager ) {
		$this->character_manager = $character_manager;
	}

	/**
	 * Отображает кастомные RPG-поля на странице профиля пользователя.
	 * Аналог rpg_add_custom_user_profile_fields().
	 *
	 * @param \WP_User $user Объект пользователя.
	 */
	public function display_rpg_fields( \WP_User $user ) {
		$user_id   = $user->ID;
		$can_edit  = current_user_can( 'edit_users', $user_id ); // Проверяем права на редактирование этого пользователя

		$gender      = $this->character_manager->get_gender( $user_id );
		$race_slug   = $this->character_manager->get_race( $user_id );
		$level       = $this->character_manager->get_level( $user_id );
		$total_spent = $this->character_manager->get_total_spent( $user_id );
		$rpg_coupons = $this->character_manager->get_coupon_inventory( $user_id );

		$max_level = ( 'dwarf' === $race_slug ) ? LevelManager::get_max_dwarf_level() : LevelManager::get_max_level();
		?>
		<h2><?php esc_html_e( 'RPG Информация Персонажа', 'woodmart-child' ); ?></h2>
		<div id="rpg-admin-message-box" style="display: none; margin-bottom: 15px;"></div> <table class="form-table" id="rpg-profile-fields">
			<tbody>
				<tr>
					<th><label for="wcrpg_gender"><?php esc_html_e( 'Гендер', 'woodmart-child' ); ?></label></th>
					<td>
						<select name="wcrpg_gender" id="wcrpg_gender" <?php disabled( ! $can_edit ); ?>>
							<option value="male" <?php selected( $gender, 'male' ); ?>><?php esc_html_e( 'Муж.', 'woodmart-child' ); ?></option>
							<option value="female" <?php selected( $gender, 'female' ); ?>><?php esc_html_e( 'Жен.', 'woodmart-child' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="wcrpg_race"><?php esc_html_e( 'Раса', 'woodmart-child' ); ?></label></th>
					<td>
						<select name="wcrpg_race" id="wcrpg_race" <?php disabled( ! $can_edit ); ?>>
							<option value=""><?php esc_html_e( '-- Не выбрана --', 'woodmart-child' ); ?></option>
							<?php
							$available_races = RaceFactory::get_available_races(); // Получаем ['slug' => 'Name']
							foreach ( $available_races as $slug => $name ) {
								printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $race_slug, $slug, false ), esc_html( $name ) );
							}
							?>
						</select>
						<?php if ( $can_edit ) : ?>
						<p class="description"><?php esc_html_e( 'Внимание: Смена расы изменит роль пользователя и может повлиять на бонусы/уровни.', 'woodmart-child' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="wcrpg_level"><?php esc_html_e( 'Уровень', 'woodmart-child' ); ?></label></th>
					<td>
						<input type="number" id="wcrpg_level" name="wcrpg_level" value="<?php echo esc_attr( $level ); ?>" class="regular-text" min="1" max="<?php echo esc_attr( $max_level ); ?>" <?php disabled( ! $can_edit ); ?>/>
						<p class="description"><?php printf( esc_html__( 'Макс. %d для текущей расы.', 'woodmart-child' ), esc_html( $max_level ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="wcrpg_total_spent"><?php esc_html_e( 'Потрачено ($)', 'woodmart-child' ); ?></label></th>
					<td>
						<input type="number" step="0.01" id="wcrpg_total_spent" name="wcrpg_total_spent" value="<?php echo esc_attr( $total_spent ); ?>" class="regular-text" <?php disabled( ! $can_edit ); ?>/>
						<?php if ( $can_edit ) : ?>
						<p class="description"><?php esc_html_e( 'Изменение этого значения повлияет на расчет уровня при следующем заказе.', 'woodmart-child' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'RPG Купоны', 'woodmart-child' ); ?></label></th>
					<td>
						<div id="coupon-list-container" style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
							<?php if ( ! empty( $rpg_coupons ) && is_array( $rpg_coupons ) ) : ?>
								<ul class="coupon-list-admin" style="margin: 0; padding: 0; list-style: none;">
									<?php foreach ( $rpg_coupons as $index => $coupon ) : ?>
										<li style="margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center;">
											<span>
											<?php
											$type_label  = $coupon['type'] ?? 'unknown';
											$value_label = isset( $coupon['value'] ) ? $coupon['value'] . '%' : 'N/A';
											$desc        = $coupon['description'] ?? ucfirst( $type_label );
											echo esc_html( $desc ) . ': ' . esc_html( $value_label );
											?>
											</span>
											<?php if ( $can_edit ) : ?>
												<button type="button" class="button button-small rpg-delete-coupon" data-index="<?php echo intval( $index ); ?>" data-userid="<?php echo esc_attr( $user_id ); ?>"><?php esc_html_e( 'Удалить', 'woodmart-child' ); ?></button>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="no-coupons-message" style="margin: 0;"><?php esc_html_e( 'Нет RPG купонов в инвентаре.', 'woodmart-child' ); ?></p>
							<?php endif; ?>
						</div>

						<?php if ( $can_edit ) : ?>
						<div id="add-coupon-form" style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ccc;">
							<h4><?php esc_html_e( 'Добавить RPG купон', 'woodmart-child' ); ?></h4>
							<label for="rpg_coupon_type_admin"><?php esc_html_e( 'Тип:', 'woodmart-child' ); ?></label>
							<select id="rpg_coupon_type_admin" name="rpg_coupon_type_admin" style="margin-right: 10px;">
								<option value="common"><?php esc_html_e( 'Общий (на товар)', 'woodmart-child' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Ежедневный (на товар)', 'woodmart-child' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Еженедельный (на корзину)', 'woodmart-child' ); ?></option>
								<option value="exclusive_item"><?php esc_html_e( 'Эксклюзивный (на товар)', 'woodmart-child' ); ?></option>
								<option value="exclusive_cart"><?php esc_html_e( 'Эксклюзивный (на корзину)', 'woodmart-child' ); ?></option>
							</select>
							<label for="rpg_coupon_value_admin"><?php esc_html_e( 'Значение (%):', 'woodmart-child' ); ?></label>
							<input type="number" id="rpg_coupon_value_admin" name="rpg_coupon_value_admin" value="10" min="1" max="100" style="width: 60px; margin-right: 10px;">
							<button type="button" id="rpg-add-coupon" class="button" data-userid="<?php echo esc_attr( $user_id ); ?>"><?php esc_html_e( 'Добавить купон', 'woodmart-child' ); ?></button>
							<p class="description"><?php printf( esc_html__( 'Максимум %d RPG купонов в инвентаре.', 'woodmart-child' ), esc_html( apply_filters( 'wcrpg_coupons_inventory_limit', 10 ) ) ); ?></p>
						</div>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $can_edit && ( 'elf' === $race_slug || 'orc' === $race_slug ) ) : ?>
				<tr>
					<th><label><?php esc_html_e( 'Сброс КД Способностей', 'woodmart-child' ); ?></label></th>
					<td>
						<?php if ( 'elf' === $race_slug && $level >= 3 ) : ?>
							<button type="button" class="button rpg-reset-ability" data-ability="sense" data-userid="<?php echo esc_attr( $user_id ); ?>"><?php esc_html_e( 'Сбросить КД Чутья', 'woodmart-child' ); ?></button>
							<span class="description"> (<?php printf( esc_html__( 'Текущая неделя: %1$s, посл. активация: %2$s', 'woodmart-child' ), esc_html( date( 'W-Y' ) ), esc_html( $this->character_manager->get_meta( $user_id, 'last_elf_activation' ) ?: __( 'нет', 'woodmart-child' ) ) ); ?>)</span><br>
						<?php endif; ?>
						<?php if ( 'orc' === $race_slug ) : ?>
							<button type="button" class="button rpg-reset-ability" data-ability="rage" data-userid="<?php echo esc_attr( $user_id ); ?>"><?php esc_html_e( 'Сбросить КД Ярости', 'woodmart-child' ); ?></button>
							<span class="description"> (<?php printf( esc_html__( 'Текущая неделя: %1$s, посл. активация: %2$s', 'woodmart-child' ), esc_html( date( 'W-Y' ) ), esc_html( $this->character_manager->get_meta( $user_id, 'last_rage_activation' ) ?: __( 'нет', 'woodmart-child' ) ) ); ?>)</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Сохраняет кастомные RPG-поля при обновлении профиля пользователя.
	 * Аналог rpg_save_custom_user_profile_fields().
	 *
	 * @param int $user_id ID пользователя.
	 */
	public function save_rpg_fields( $user_id ) {
		if ( ! current_user_can( 'edit_users', $user_id ) ) {
			return false;
		}
		// Nonce проверка обычно делается для всей формы профиля WordPress,
		// но если бы это была отдельная форма, мы бы добавили check_admin_referer.

		// Сохраняем гендер
		if ( isset( $_POST['wcrpg_gender'] ) ) { // Используем имена полей с префиксом, если они есть
			$this->character_manager->set_gender( $user_id, sanitize_text_field( wp_unslash( $_POST['wcrpg_gender'] ) ) );
		}

		// Сохраняем расу и роль
		if ( isset( $_POST['wcrpg_race'] ) ) {
			$old_race_slug = $this->character_manager->get_race( $user_id );
			$new_race_slug = sanitize_text_field( wp_unslash( $_POST['wcrpg_race'] ) );

			if ( $new_race_slug !== $old_race_slug ) {
				$this->character_manager->set_race( $user_id, $new_race_slug );
				$user_obj = new \WP_User( $user_id );
				
				// Удаляем старые расовые роли
				$races_slugs = array_keys( RaceFactory::get_available_races() );
				foreach ( $races_slugs as $r_slug ) {
					if ( $user_obj->has_cap( $r_slug ) ) { // Проверка по capability, а не по имени роли, надежнее
						$user_obj->remove_role( $r_slug );
					}
				}
				// Добавляем новую расовую роль
				if ( ! empty( $new_race_slug ) && get_role( $new_race_slug ) ) {
					$user_obj->add_role( $new_race_slug );
				} else {
					// Если новая раса пустая или роль не существует, назначаем роль по умолчанию
					$user_obj->add_role( get_option( 'default_role', 'subscriber' ) );
				}
				// Опционально: сброс уровня при смене расы
				// $this->character_manager->set_level( $user_id, 1 );
				// $this->character_manager->update_meta( $user_id, 'experience_points', 0 );
			}
		}

		// Сохраняем уровень
		if ( isset( $_POST['wcrpg_level'] ) ) {
			$current_race_for_level = $this->character_manager->get_race( $user_id );
			$max_level_for_save     = ( 'dwarf' === $current_race_for_level ) ? LevelManager::get_max_dwarf_level() : LevelManager::get_max_level();
			$level_to_save          = intval( $_POST['wcrpg_level'] );
			$level_to_save          = max( 1, min( $level_to_save, $max_level_for_save ) );
			$this->character_manager->set_level( $user_id, $level_to_save );
		}

		// Сохраняем потраченную сумму
		if ( isset( $_POST['wcrpg_total_spent'] ) ) {
			$total_spent_to_save = isset( $_POST['wcrpg_total_spent'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['wcrpg_total_spent'] ) ) ) : 0.0;
			$total_spent_to_save = max( 0.0, $total_spent_to_save );
			$this->character_manager->update_meta( $user_id, 'total_spent', $total_spent_to_save );
		}
		// RPG купоны управляются через AJAX, здесь не сохраняем напрямую.
	}
}
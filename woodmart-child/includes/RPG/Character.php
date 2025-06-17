<?php
/**
 * Класс для управления данными RPG-персонажа.
 *
 * @package WoodmartChildRPG\RPG
 */

namespace WoodmartChildRPG\RPG;

use WoodmartChildRPG\RPG\RaceFactory;
use WoodmartChildRPG\RPG\LevelManager; // <--- ДОБАВИТЬ ЭТУ СТРОКУ

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class Character {

	const META_PREFIX = 'wcrpg_';

	public function initialize_new_user_meta( $user_id, $race_slug = '', $gender = '' ) {
		if ( ! $user_id ) {
			return;
		}

		if ( ! empty( $gender ) ) {
			update_user_meta( $user_id, self::META_PREFIX . 'gender', sanitize_text_field( $gender ) );
		}

		$race_to_set = ! empty( $race_slug ) ? sanitize_text_field( $race_slug ) : 'human';
		update_user_meta( $user_id, self::META_PREFIX . 'race', $race_to_set );

		update_user_meta( $user_id, self::META_PREFIX . 'level', 1 );
		update_user_meta( $user_id, self::META_PREFIX . 'experience_points', 0 );
		update_user_meta( $user_id, self::META_PREFIX . 'gold', 0 );
		update_user_meta( $user_id, self::META_PREFIX . 'total_spent', 0 );
		update_user_meta( $user_id, self::META_PREFIX . 'coupon_inventory', array() );

		$user = new \WP_User( $user_id );
		if ( $user->exists() ) {
			if ( in_array( 'administrator', $user->roles, true ) ) {
				return;
			}
			if ( get_role( $race_to_set ) ) {
				$user->add_role( $race_to_set );
			} else {
				$user->add_role( 'subscriber' );
				error_log( 'WoodmartChildRPG Ошибка: Роль "' . esc_html( $race_to_set ) . '" не существует для пользователя ' . esc_html( $user_id ) . '. Назначена роль "subscriber".' );
			}
		}
	}

	public function get_meta( $user_id, $meta_key, $single = true ) {
		return get_user_meta( $user_id, self::META_PREFIX . $meta_key, $single );
	}

	public function update_meta( $user_id, $meta_key, $meta_value ) {
		return update_user_meta( $user_id, self::META_PREFIX . $meta_key, $meta_value );
	}

	public function get_user_race_object( $user_id ) {
		$race_slug = $this->get_race( $user_id );
		if ( ! $race_slug ) {
			return null;
		}
		return RaceFactory::create_race( $race_slug );
	}

	public function get_race( $user_id ) {
		return $this->get_meta( $user_id, 'race' );
	}

	public function get_gender( $user_id ) {
		return $this->get_meta( $user_id, 'gender' );
	}

	public function get_level( $user_id ) {
		return (int) $this->get_meta( $user_id, 'level' );
	}

	public function get_experience( $user_id ) {
		return (int) $this->get_meta( $user_id, 'experience_points' );
	}

	public function get_gold( $user_id ) {
		return (int) $this->get_meta( $user_id, 'gold' );
	}

	public function get_total_spent( $user_id ) {
		return (float) $this->get_meta( $user_id, 'total_spent' );
	}

	public function get_coupon_inventory( $user_id ) {
		$inventory = $this->get_meta( $user_id, 'coupon_inventory' );
		return is_array( $inventory ) ? $inventory : array();
	}
    
    public function update_coupon_inventory( $user_id, $inventory ) { // <--- НОВЫЙ МЕТОД
		if ( ! is_array( $inventory ) ) {
			$inventory = array();
		}
		return $this->update_meta( $user_id, 'coupon_inventory', $inventory );
	}

	public function set_race( $user_id, $race_slug ) {
		$race_slug = sanitize_text_field( $race_slug );
		// TODO: Добавить логику смены роли WordPress при смене расы.
		return $this->update_meta( $user_id, 'race', $race_slug );
	}

	public function set_gender( $user_id, $gender ) {
		return $this->update_meta( $user_id, 'gender', sanitize_text_field( $gender ) );
	}

	public function add_experience( $user_id, $amount ) {
		if ( $amount <= 0 ) {
			return;
		}
		$current_xp = $this->get_experience( $user_id );
		$new_xp     = $current_xp + (int) $amount;
		$this->update_meta( $user_id, 'experience_points', $new_xp );
		$this->check_for_level_up( $user_id );
	}

	public function add_gold( $user_id, $amount ) {
		if ( $amount == 0 ) {
			return;
		}
		$current_gold = $this->get_gold( $user_id );
		$new_gold     = $current_gold + (int) $amount;
		$this->update_meta( $user_id, 'gold', $new_gold );
	}
    
    public function add_total_spent( $user_id, $amount ) { // <--- НОВЫЙ МЕТОД
		if ( $amount <= 0 ) {
			return;
		}
		$current_spent = $this->get_total_spent( $user_id );
		$new_spent     = $current_spent + (float) $amount;
		$this->update_meta( $user_id, 'total_spent', $new_spent );
	}

	public function set_level( $user_id, $level ) {
		$this->update_meta( $user_id, 'level', (int) $level );
	}
	/**
	 * Добавляет RPG-купон в инвентарь пользователя.
	 *
	 * @param int   $user_id     ID пользователя.
	 * @param array $coupon_data Данные купона (например, ['type' => 'cart_percent', 'value' => 10, 'description' => 'Скидка 10% на корзину']).
	 * @param int   $limit       Максимальное количество купонов в инвентаре.
	 * @return bool True если купон добавлен, false если инвентарь полон.
	 */	
	
	public function add_rpg_coupon_to_inventory( $user_id, array $coupon_data, $limit = 10 ) { // <--- НОВЫЙ МЕТОД
		if ( ! $user_id || empty( $coupon_data ) || ! isset( $coupon_data['type'] ) || ! isset( $coupon_data['value'] ) ) {
			return false;
		}

		$inventory = $this->get_coupon_inventory( $user_id );

		if ( count( $inventory ) < $limit ) {
			$inventory[] = $coupon_data; // Добавляем как есть, предполагая, что тип и значение корректны
			$this->update_coupon_inventory( $user_id, $inventory );
			return true;
		} else {
			// error_log( "WoodmartChildRPG: Инвентарь купонов пользователя {$user_id} переполнен. Купон не добавлен." );
			return false;
		}
	}
	
	/**
	 * Проверяет и обрабатывает повышение уровня пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 */
	/**
	 * Проверяет и обрабатывает повышение уровня пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 */
	public function check_for_level_up( $user_id ) {
		$current_level  = $this->get_level( $user_id );
		$current_xp     = $this->get_experience( $user_id );
		$user_race_slug = $this->get_race( $user_id );

		if ( 'dwarf' === $user_race_slug ) {
			$max_dwarf_level = LevelManager::get_max_dwarf_level();
			$leveled_up      = false;
			// Цикл для обработки нескольких повышений уровня за раз
			while ( $current_level < $max_dwarf_level ) {
				$xp_for_next_dwarf_level = LevelManager::get_xp_for_dwarf_level( $current_level + 1 );
				if ( $current_xp >= $xp_for_next_dwarf_level ) {
					$current_level++;
					$this->set_level( $user_id, $current_level );
					$leveled_up = true;

					$race_object = $this->get_user_race_object( $user_id );
					if ( $race_object instanceof Race ) {
						$race_object->apply_level_up_bonus( $user_id, $current_level );
					}
					// error_log( "User {$user_id} (Dwarf) leveled up to {$current_level}!" );
				} else {
					break; // Опыта не хватает для следующего уровня
				}
			}
			// Если не было повышения в цикле, но calculate_dwarf_level дает другой уровень
			// (например, если опыт был уменьшен и уровень должен понизиться - хотя это редкий сценарий)
			// или для первоначального расчета, если set_level не вызывался.
			// Однако, более правильный подход - set_level вызывается только при повышении.
			// Старый calculate_dwarf_level также обновлял мета-поле, что теперь делает set_level.
			// Текущая логика цикла должна быть достаточной для повышения уровня.
			// Если требуется строгий пересчет уровня по таблице даже без набора опыта для *следующего* порога,
			// то можно вызвать LevelManager::calculate_dwarf_level и сравнить с $this->get_level().

		} else {
			// Логика для не-дварфов
			$max_level = LevelManager::get_max_level();
			while ( $current_level < $max_level ) {
				$xp_for_next_level = LevelManager::get_xp_for_level( $current_level + 1 );
				if ( $current_xp >= $xp_for_next_level ) {
					$current_level++;
					$this->set_level( $user_id, $current_level );

					$race_object = $this->get_user_race_object( $user_id );
					if ( $race_object instanceof Race ) {
						$race_object->apply_level_up_bonus( $user_id, $current_level );
					}
					// error_log( "User {$user_id} leveled up to {$current_level}!" );
				} else {
					break; 
				}
			}
		}
	}
	    /**
	 * Удаляет мета-поле пользователя.
	 *
	 * @param int    $user_id  ID пользователя.
	 * @param string $meta_key Ключ мета-поля (без префикса).
	 * @param mixed  $meta_value Опционально. Если указано, удалит только конкретное значение.
	 * @return bool True при успехе, false при ошибке.
	 */
	public function delete_meta( $user_id, $meta_key, $meta_value = '' ) { // <--- ПРОВЕРЬТЕ/ДОБАВЬТЕ ЭТОТ МЕТОД
		return delete_user_meta( $user_id, self::META_PREFIX . $meta_key, $meta_value );
	}
}

    

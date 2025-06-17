<?php
/**
 * Класс для расы Человек.
 *
 * @package WoodmartChildRPG\RPG\Races
 */

namespace WoodmartChildRPG\RPG\Races;

use WoodmartChildRPG\RPG\Race;
use WoodmartChildRPG\RPG\Character as RPGCharacter; // Для работы с инвентарем

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class Human extends Race {

	public function __construct() {
		parent::__construct(
			'human',
			__( 'Человек', 'woodmart-child' ),
			__( 'Адаптивные и разносторонние, люди преуспевают во многих областях.', 'woodmart-child' )
		);
	}

	/**
	 * Применяет бонусы при повышении уровня для Людей.
	 * Ежемесячные эксклюзивные купоны для Людей 3+ уровня выдаются через WooCommerceIntegration::handle_order_completion.
	 * Здесь можно добавить другие бонусы, специфичные для повышения уровня Людей.
	 *
	 * @param int $user_id  ID пользователя.
	 * @param int $new_level Новый уровень.
	 */
	public function apply_level_up_bonus( $user_id, $new_level ) {
		// error_log( "Human user {$user_id} reached level {$new_level}. No specific direct level up bonus here, monthly handled elsewhere." );
		// Пассивные бонусы, такие как шанс улучшения/сохранения ежедневного купона, зависят от уровня,
		// но применяются в момент активации купона (см. AJAXHandler::handle_use_rpg_coupon).
	}

	/**
	 * Получает описание пассивных бонусов для Людей.
	 * Логика из get_human_bonuses() вашего файла inc/races/Human/bonuses.php.
	 *
	 * @param int $user_id ID пользователя.
	 * @return string Описание бонусов.
	 */
	public function get_passive_bonus_description( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return __( 'Бонусы Людей применяются для авторизованных пользователей.', 'woodmart-child' );
		}

		$character_manager = new RPGCharacter(); // Нужен для получения уровня
		$level             = $character_manager->get_level( $user_id );
		$level             = max( 1, min( $level, 5 ) ); // Ограничиваем уровень 1-5

		$general_level_discount = min( $level, 5 ); // 1% за уровень, макс 5% (из discount-logic)

		$daily_value_map    = array( 1 => 3, 2 => 5, 3 => 7, 4 => 9, 5 => 10 );
		$daily_value        = isset( $daily_value_map[ $level ] ) ? $daily_value_map[ $level ] : 3;
		$upgrade_chance_map = array( 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5 );
		$upgrade_chance     = isset( $upgrade_chance_map[ $level ] ) ? $upgrade_chance_map[ $level ] : 1;
		$save_chance_map    = array( 1 => 5, 2 => 7, 3 => 10, 4 => 12, 5 => 15 );
		$save_chance        = isset( $save_chance_map[ $level ] ) ? $save_chance_map[ $level ] : 5;

		$description = sprintf(
			__( 'Общая скидка за уровень: %1$d%%. Ежедневный купон: %2$d%% (шанс улучшить: %3$d%%, шанс сохранить: %4$d%%)', 'woodmart-child' ),
			$general_level_discount,
			$daily_value,
			$upgrade_chance,
			$save_chance
		);

		if ( $level >= 4 ) {
			$weekly_value = ( 4 === $level ) ? 3 : 5;
			$description .= sprintf( __( ' + Еженедельный купон: %d%% на всю корзину', 'woodmart-child' ), $weekly_value );
		}

		if ( $level >= 3 ) {
			// Ежемесячные эксклюзивные купоны выдаются через WooCommerceIntegration, здесь только упоминание.
			$description .= __( ' + Ежемесячные эксклюзивные купоны (25% на товар и 25% на корзину)', 'woodmart-child' );
		}

		return $description . '.';
	}

	/**
	 * Активная способность Людей.
	 * В вашем коде не было явной активной способности для Людей, кроме купонов.
	 * Если она появится, реализуйте здесь.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param mixed $context Контекст применения.
	 */
	public function apply_ability( $user_id, $context = null ) {
		// error_log( "Human ability called for user {$user_id} - no specific active ability defined yet." );
	}

	/**
	 * Выдает ежедневный купон человеку, если он еще не был выдан сегодня.
	 * Статический метод, чтобы его можно было вызвать из хука без создания объекта Human.
	 *
	 * @param int          $user_id ID пользователя.
	 * @param RPGCharacter $character_manager Экземпляр менеджера персонажей.
	 */
	public static function issue_daily_coupon_for_user( $user_id, RPGCharacter $character_manager ) {
		if ( ! $user_id || 'human' !== $character_manager->get_race( $user_id ) ) {
			return;
		}

		$last_issued_meta_key = 'last_daily_coupon_issued'; // Без префикса, Character manager добавит свой
		$last_issued          = $character_manager->get_meta( $user_id, $last_issued_meta_key );
		$today                = date( 'Y-m-d' );

		if ( $last_issued === $today ) {
			return; // Уже выдан сегодня
		}

		$level        = $character_manager->get_level( $user_id );
		$level        = max( 1, min( $level, 5 ) );
		$coupon_value_map = array( 1 => 3, 2 => 5, 3 => 7, 4 => 9, 5 => 10 );
		$coupon_value = isset( $coupon_value_map[ $level ] ) ? $coupon_value_map[ $level ] : 3;

		$coupon_data = array(
			'type'        => 'daily',
			'value'       => $coupon_value,
			'description' => sprintf( __( 'Ежедневный купон Человека (%d%%)', 'woodmart-child' ), $coupon_value ),
		);

		if ( $character_manager->add_rpg_coupon_to_inventory( $user_id, $coupon_data ) ) {
			$character_manager->update_meta( $user_id, $last_issued_meta_key, $today );
			// error_log( "Issued daily coupon to Human user {$user_id}." );
		} else {
			// error_log( "Failed to issue daily coupon to Human user {$user_id} (inventory full?)." );
		}
	}

	/**
	 * Выдает еженедельный купон человеку 4+ уровня.
	 * Статический метод для вызова из cron.
	 *
	 * @param int          $user_id ID пользователя.
	 * @param RPGCharacter $character_manager Экземпляр менеджера персонажей.
	 */
	public static function issue_weekly_coupon_for_user( $user_id, RPGCharacter $character_manager ) {
		if ( ! $user_id || 'human' !== $character_manager->get_race( $user_id ) ) {
			return;
		}

		$level = $character_manager->get_level( $user_id );
		if ( $level < 4 ) {
			return;
		}

		$last_issued_meta_key = 'last_weekly_coupon_issued';
		$last_weekly_issued   = $character_manager->get_meta( $user_id, $last_issued_meta_key );
		$this_week            = date( 'W-Y' );

		if ( $last_weekly_issued === $this_week ) {
			return; // Уже выдан на этой неделе
		}

		$coupon_value = ( 4 === $level ) ? 3 : 5; // 3% на 4 уровне, 5% на 5 уровне
		$coupon_data  = array(
			'type'        => 'weekly',
			'value'       => $coupon_value,
			'description' => sprintf( __( 'Еженедельный купон Человека (%d%%)', 'woodmart-child' ), $coupon_value ),
		);

		if ( $character_manager->add_rpg_coupon_to_inventory( $user_id, $coupon_data ) ) {
			$character_manager->update_meta( $user_id, $last_issued_meta_key, $this_week );
			// error_log( "Issued weekly coupon to Human user {$user_id}." );
		} else {
			// error_log( "Failed to issue weekly coupon to Human user {$user_id} (inventory full?)." );
		}
	}
}

<?php
/**
 * Класс для расы Эльф.
 *
 * @package WoodmartChildRPG\RPG\Races
 */

namespace WoodmartChildRPG\RPG\Races;

use WoodmartChildRPG\RPG\Race;
use WoodmartChildRPG\RPG\Character; // Для доступа к уровню и мета-данным

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Запрещаем прямой доступ.
}

class Elf extends Race {

	public function __construct() {
		parent::__construct(
			'elf',
			__( 'Эльф', 'woodmart-child' ),
			__( 'Изящные и мудрые, эльфы обладают связью с природой и магией.', 'woodmart-child' )
		);
	}

	public function apply_level_up_bonus( $user_id, $new_level ) {
		// error_log("Elf user {$user_id} reached level {$new_level}. Specific level up bonuses can be added here.");
		// Можно, например, выдать купон или редкий предмет.
	}

	/**
	 * Получает описание пассивных бонусов для Эльфов.
	 * Логика из get_elf_bonuses() вашего файла bonuses.php (для эльфов).
	 *
	 * @param int $user_id ID пользователя.
	 * @return string Описание бонусов.
	 */
	public function get_passive_bonus_description( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return __( 'Бонусы эльфов применяются для авторизованных пользователей.', 'woodmart-child' );
		}

		$character_manager = new Character();
		$level             = $character_manager->get_level( $user_id );
		$level             = max( 1, min( $level, 5 ) ); // Уровень эльфа 1-5

		// Общая скидка за уровень (из discount-logic.php, применялась для всех не-дварфов)
		$general_level_discount = min( $level, 5 );

		// Скидка на эльфийские товары
		$elf_item_discount_map     = array( 1 => 5, 2 => 10, 3 => 10, 4 => 10, 5 => 12 );
		$elf_item_discount_percent = isset( $elf_item_discount_map[ $level ] ) ? $elf_item_discount_map[ $level ] : 5;

		$description = sprintf(
			__( 'Общая скидка за уровень: %d%%. Скидка на эльфийские товары: %d%%.', 'woodmart-child' ),
			$general_level_discount,
			$elf_item_discount_percent
		);

		// Описание способности "Чутье"
		if ( $level >= 3 ) {
			$sense_max_items_map = array( 3 => 1, 4 => 2, 5 => 3 );
			$max_items           = isset( $sense_max_items_map[ $level ] ) ? $sense_max_items_map[ $level ] : 1;
			$sense_bonus_text    = sprintf(
				__( " + Способность 'Чутье': сделать %d товар(а/ов) эльфийским(и) раз в неделю (скидка %d%%)", 'woodmart-child' ),
				$max_items,
				$elf_item_discount_percent
			);
			$description .= $sense_bonus_text;
		}

		return $description;
	}

	/**
	 * Применяет пассивные скидки Эльфа к корзине.
	 * Логика из apply_elf_discount() вашего файла bonuses.php (для эльфов).
	 *
	 * @param \WC_Cart $cart Объект корзины.
	 * @param int      $user_id ID пользователя.
	 */
	public function apply_passive_cart_discount( \WC_Cart $cart, $user_id ) {
		$character_manager = new Character();
		$level             = $character_manager->get_level( $user_id );
		$level             = max( 1, min( $level, 5 ) );

		$elf_item_discount_map     = array( 1 => 5, 2 => 10, 3 => 10, 4 => 10, 5 => 12 );
		$elf_item_discount_percent = isset( $elf_item_discount_map[ $level ] ) ? $elf_item_discount_map[ $level ] : 5;

		// Получаем список ID товаров, выбранных через "Чутье"
		$sense_items = $character_manager->get_meta( $user_id, 'elf_items' ); // Используем наш Character manager
		$sense_items = is_array( $sense_items ) ? $sense_items : array();

		$total_elf_discount = 0;

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			$_product   = $cart_item['data'];

			if ( ! $_product || ! $_product->exists() ) {
				continue;
			}

			$is_elf_item = false;
			// 1. Проверка категории 'elf-items'
			if ( has_term( 'elf-items', 'product_cat', $product_id ) ) {
				$is_elf_item = true;
			}
			// 2. Проверка списка "Чутья"
			if ( ! $is_elf_item && in_array( $product_id, $sense_items, true ) ) {
				$is_elf_item = true;
			}

			if ( $is_elf_item ) {
				// Используем wc_get_price_excluding_tax для получения цены без налога
				$item_price          = wc_get_price_excluding_tax( $_product );
				$discount_for_item   = ( $item_price * $elf_item_discount_percent ) / 100;
				$total_elf_discount += $discount_for_item * $cart_item['quantity'];
			}
		}

		if ( $total_elf_discount > 0 ) {
			$cart->add_fee( __( 'Скидка на эльфийские товары', 'woodmart-child' ) . ' (' . $elf_item_discount_percent . '%)', - $total_elf_discount );
		}
	}

	/**
	 * Применяет активную способность расы Эльф ("Чутье").
	 * Фактическое применение скидки происходит в apply_passive_cart_discount
	 * на основе мета-поля 'elf_items'. Этот метод может инициировать процесс выбора.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param mixed $context Контекст применения.
	 */
	public function apply_ability( $user_id, $context = null ) {
		// Логика активации "Чутья" (установка флага elf_sense_pending)
		// будет перенесена в AJAX-обработчик.
		// Этот метод может использоваться для проверки условий или других действий,
		// не связанных напрямую с AJAX.
		// error_log("Elf 'Sense' ability initiated for user {$user_id}. User should now select items in cart.");
	}

	/**
	 * Еженедельное назначение случайных товаров "эльфийскими".
	 * Логика из assign_elf_items_weekly() вашего файла bonuses.php (для эльфов).
	 * Этот метод должен вызываться по расписанию WordPress.
	 */
	public static function assign_elf_items_weekly_job() {
		$term_slug       = 'elf-items';
		$taxonomy        = 'product_cat';
		$number_of_items = 5;

		$current_elf_products_query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => $term_slug,
					),
				),
			)
		);
		$current_elf_ids = $current_elf_products_query->posts;

		if ( ! empty( $current_elf_ids ) ) {
			foreach ( $current_elf_ids as $product_id ) {
				wp_remove_object_terms( $product_id, $term_slug, $taxonomy );
			}
		}

		$previous_elf_items = get_option( 'wcrpg_previous_elf_items', array() );
		$previous_elf_items = is_array( $previous_elf_items ) ? $previous_elf_items : array();

		$new_candidates_query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $number_of_items * 2,
				'fields'         => 'ids',
				'orderby'        => 'rand',
				'post__not_in'   => $previous_elf_items,
			)
		);
		$new_candidates_ids = $new_candidates_query->posts;

		$new_elf_ids = array();
		if ( ! empty( $new_candidates_ids ) ) {
			$count = 0;
			foreach ( $new_candidates_ids as $product_id ) {
				if ( $count >= $number_of_items ) {
					break;
				}
				wp_set_object_terms( $product_id, $term_slug, $taxonomy, true );
				$new_elf_ids[] = $product_id;
				$count++;
			}
		}
		update_option( 'wcrpg_previous_elf_items', $new_elf_ids );
	}
}

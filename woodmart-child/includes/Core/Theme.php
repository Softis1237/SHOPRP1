<?php
/**
 * Файл: woodmart-child/includes/Core/Theme.php
 * Основной класс темы.
 *
 * @package WoodmartChildRPG\Core
 */
namespace WoodmartChildRPG\Core;

// Подключаем необходимые классы
require_once __DIR__ . '/Loader.php';
require_once __DIR__ . '/../Pages/CharacterPage.php';
require_once __DIR__ . '/../Admin/UserProfile.php';

use WoodmartChildRPG\RPG\RaceFactory;
use WoodmartChildRPG\RPG\Character;
use WoodmartChildRPG\RPG\LevelManager;
use WoodmartChildRPG\RPG\Races\Human;
use WoodmartChildRPG\Admin\UserProfile as AdminUserProfile;
use WoodmartChildRPG\Admin\UserTable as AdminUserTable;
use WoodmartChildRPG\Integration\Dokan\DokanUserCouponDB;
use WoodmartChildRPG\Integration\Dokan\DokanAJAXHandler;
use WoodmartChildRPG\Integration\Dokan\DokanIntegrationManager;
use WoodmartChildRPG\Pages\CharacterPage;
use WoodmartChildRPG\Assets\AssetManager;
use WoodmartChildRPG\WooCommerce\WooCommerceIntegration;
use WoodmartChildRPG\WooCommerce\DiscountManager;
use WoodmartChildRPG\WooCommerce\CartCustomizations;
use WoodmartChildRPG\Admin\AdminAJAXHandler;
use WoodmartChildRPG\Core\Installer;
use WoodmartChildRPG\Shortcodes\RegisterFormShortcode;
use WoodmartChildRPG\Shortcodes\GenderSelectShortcode;

add_action('after_switch_theme9223', [Installer::class, 'activate']);

if (!defined('ABSPATH')) {
    exit; // Запрещаем прямой доступ.
}

final class Theme {
    /**
     * @var Loader Менеджер загрузки хуков
     */
    protected $loader;

    /**
     * @var Character Менеджер персонажей
     */
    protected $character_manager;

    /**
     * @var AssetManager Менеджер ассетов
     */
    protected $asset_manager;

    /**
     * @var DokanUserCouponDB Класс работы с БД купонов Dokan
     */
    protected $dokan_coupon_db;

    /**
     * @var WooCommerceIntegration Интеграция с WooCommerce
     */
    protected $woocommerce_integration;

    /**
     * @var DiscountManager Менеджер скидок
     */
    protected $discount_manager;

    /**
     * @var AJAXHandler Обработка AJAX запросов фронтенда
     */
    protected $ajax_handler;

    /**
     * @var AdminAJAXHandler Обработка AJAX запросов админки
     */
    protected $admin_ajax_handler;

    /**
     * @var DokanAJAXHandler Обработка AJAX запросов Dokan
     */
    protected $dokan_ajax_handler;

    /**
     * @var CharacterPage Обработчик страницы "Персонаж"
     */
    protected $character_page_handler;

    /**
     * @var DokanIntegrationManager Менеджер интеграции с Dokan
     */
    protected $dokan_integration_manager;

    /**
     * @var AdminUserProfile Обработчик профиля пользователя в админке
     */
    protected $admin_user_profile;

    /**
     * @var AdminUserTable Обработчик таблицы пользователей в админке
     */
    protected $admin_user_table;

    /**
     * @var CartCustomizations Обработчик кастомизаций корзины
     */
    protected $cart_customizer;

    /**
     * @var Theme|null Экземпляр синглтона
     */
    private static $instance = null;

    /**
     * Приватный конструктор для синглтона
     */
    private function __construct() {
        $this->loader = new Loader();
        $this->load_components();
        $this->define_hooks();
    }

    /**
     * Получает экземпляр темы (синглтон)
     *
     * @return Theme
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Загружает все компоненты темы
     */
    private function load_components() {
        $this->character_manager = new Character();
        $this->asset_manager = new AssetManager($this->character_manager);
        $this->dokan_coupon_db = new DokanUserCouponDB();
        $this->discount_manager = new DiscountManager($this->character_manager, $this->dokan_coupon_db);
        $this->woocommerce_integration = new WooCommerceIntegration($this->character_manager);
        $this->ajax_handler = new AJAXHandler($this->character_manager);
        $this->admin_ajax_handler = new AdminAJAXHandler($this->character_manager);
        $this->dokan_ajax_handler = new DokanAJAXHandler($this->character_manager, $this->dokan_coupon_db);
        $this->character_page_handler = new CharacterPage($this->character_manager, $this->dokan_coupon_db);
        $this->dokan_integration_manager = new DokanIntegrationManager($this->dokan_coupon_db);
        $this->cart_customizer = new CartCustomizations();

        if (is_admin()) {
            $this->admin_user_profile = new AdminUserProfile($this->character_manager);
            $this->admin_user_table = new AdminUserTable($this->character_manager);
        }

        $this->loader->add_action('init', $this, 'register_shortcodes');
    }

    /**
     * Определяет хуки WordPress и плагинов
     */
    private function define_hooks() {
        // User hooks
        $this->loader->add_action('user_register', $this, 'on_user_register', 10, 1);
        $this->loader->add_action('init', $this, 'create_custom_roles');
        $this->loader->add_action('init', $this, 'register_scheduled_events');
        $this->loader->add_action('init', $this, 'register_character_page_endpoint');
        $this->loader->add_action('wp', $this, 'maybe_issue_daily_human_coupon');

        // WooCommerce hooks
        $this->loader->add_action('woocommerce_order_status_completed', $this->woocommerce_integration, 'handle_order_completion', 10, 1);
        $this->loader->add_action('woocommerce_cart_calculate_fees', $this->discount_manager, 'apply_rpg_cart_discounts', 20, 1);

        // Cart customization hooks
        $this->loader->add_action('wp_loaded', $this->cart_customizer, 'remove_default_coupon_form_cart', 15);
        $this->loader->add_action('woocommerce_removed_coupon', $this->dokan_ajax_handler, 'handle_woocommerce_removed_coupon', 10, 1);

        // AJAX for applying Dokan coupons
        $this->loader->add_action('wp_ajax_rpg_apply_dokan_coupon_in_cart', $this->dokan_ajax_handler, 'handle_apply_dokan_coupon_in_cart');
        $this->loader->add_action('wp_ajax_nopriv_rpg_apply_dokan_coupon_in_cart', $this->dokan_ajax_handler, 'handle_apply_dokan_coupon_in_cart');

        // NEW: AJAX for applying Dokan coupons on checkout page
        $this->loader->add_action('wp_ajax_rpg_apply_dokan_coupon_on_checkout', $this->dokan_ajax_handler, 'handle_apply_dokan_coupon_on_checkout');
        $this->loader->add_action('wp_ajax_nopriv_rpg_apply_dokan_coupon_on_checkout', $this->dokan_ajax_handler, 'handle_apply_dokan_coupon_on_checkout');

        // Enqueue assets
        if (!is_admin()) {
            $this->loader->add_action('wp_enqueue_scripts', $this->asset_manager, 'enqueue_frontend_assets');
            $this->loader->add_action('wp_enqueue_scripts', $this->asset_manager, 'enqueue_dokan_store_assets');
        } else {
            $this->loader->add_action('admin_enqueue_scripts', $this->asset_manager, 'enqueue_admin_assets');

            // Admin user profile and table hooks
            if ($this->admin_user_profile) {
                $this->loader->add_action('show_user_profile', $this->admin_user_profile, 'display_rpg_fields');
                $this->loader->add_action('edit_user_profile', $this->admin_user_profile, 'display_rpg_fields');
                $this->loader->add_action('personal_options_update', $this->admin_user_profile, 'save_rpg_fields');
                $this->loader->add_action('edit_user_profile_update', $this->admin_user_profile, 'save_rpg_fields');
            }

            if ($this->admin_user_table) {
                $this->loader->add_filter('manage_users_columns', $this->admin_user_table, 'add_columns');
                $this->loader->add_filter('manage_users_custom_column', $this->admin_user_table, 'render_column_data', 10, 3);
                $this->loader->add_filter('manage_users_sortable_columns', $this->admin_user_table, 'make_columns_sortable');
                $this->loader->add_action('pre_get_users', $this->admin_user_table, 'custom_orderby');
                $this->loader->add_action('restrict_manage_users', $this->admin_user_table, 'display_race_filter');
                $this->loader->add_action('pre_get_users', $this->admin_user_table, 'apply_race_filter');
            }
        }

        // Frontend AJAX handlers
        $this->loader->add_action('wp_ajax_use_rpg_coupon', $this->ajax_handler, 'handle_use_rpg_coupon');
        $this->loader->add_action('wp_ajax_activate_elf_sense', $this->ajax_handler, 'handle_activate_elf_sense_pending');
        $this->loader->add_action('wp_ajax_select_elf_items', $this->ajax_handler, 'handle_select_elf_items');
        $this->loader->add_action('wp_ajax_activate_orc_rage', $this->ajax_handler, 'handle_activate_orc_rage_pending');
        $this->loader->add_action('wp_ajax_select_orc_rage_product', $this->ajax_handler, 'handle_select_orc_rage_product');
        $this->loader->add_action('wp_ajax_deactivate_rpg_coupon', $this->ajax_handler, 'handle_deactivate_coupon');

        // Admin AJAX handlers
        $this->loader->add_action('wp_ajax_rpg_admin_add_coupon', $this->admin_ajax_handler, 'handle_admin_add_rpg_coupon');
        $this->loader->add_action('wp_ajax_rpg_admin_delete_coupon', $this->admin_ajax_handler, 'handle_admin_delete_rpg_coupon');
        $this->loader->add_action('wp_ajax_rpg_admin_reset_ability', $this->admin_ajax_handler, 'handle_admin_reset_ability_cooldown');

        // Dokan AJAX handlers
        $this->loader->add_action('wp_ajax_rpg_take_dokan_coupon', $this->dokan_ajax_handler, 'handle_take_dokan_coupon');
        $this->loader->add_action('wp_ajax_nopriv_rpg_take_dokan_coupon', $this->dokan_ajax_handler, 'handle_take_dokan_coupon');
        $this->loader->add_action('wp_ajax_rpg_add_dokan_coupon_by_code', $this->dokan_ajax_handler, 'handle_add_dokan_coupon_by_code');
        $this->loader->add_action('wp_ajax_rpg_activate_dokan_coupon_from_inventory', $this->dokan_ajax_handler, 'handle_activate_dokan_coupon_from_inventory');
        $this->loader->add_action('wp_ajax_rpg_refresh_vendor_coupons_status', $this->dokan_ajax_handler, 'handle_refresh_dokan_coupons_status');
        $this->loader->add_action('wp_ajax_get_store_dokan_coupons_status', $this->dokan_ajax_handler, 'handle_get_store_dokan_coupons_status');
        $this->loader->add_action('wp_ajax_nopriv_get_store_dokan_coupons_status', $this->dokan_ajax_handler, 'handle_get_store_dokan_coupons_status');
        $this->loader->add_action('wp_ajax_rpg_clear_invalid_dokan_coupons', $this->dokan_ajax_handler, 'handle_clear_invalid_dokan_coupons');

        // Scheduled events
        $elf = new \WoodmartChildRPG\RPG\Races\Elf();
        add_action('wcrpg_assign_elf_items_weekly_event', array($elf, 'assign_elf_items_weekly_job'), 10);
        $this->loader->add_action('wcrpg_issue_weekly_human_coupon_event', $this, 'run_weekly_human_coupon_issuance_job');

        // Character page in My Account
        $this->loader->add_filter('woocommerce_account_menu_items', $this, 'add_character_tab_to_account_menu', 20);
        $this->loader->add_action('woocommerce_account_character_endpoint', $this->character_page_handler, 'render_page_content');

        // Hooks for DokanIntegrationManager
        $this->loader->add_action('wp_trash_post', $this->dokan_integration_manager, 'handle_deleted_dokan_coupon_post');
        $this->loader->add_action('delete_post', $this->dokan_integration_manager, 'handle_deleted_dokan_coupon_post');
    }

    /**
     * Обработчик хука user_register
     */
    public function on_user_register($user_id) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $selected_race   = isset($_POST['selected_race']) ? sanitize_text_field(wp_unslash($_POST['selected_race'])) : '';
        $selected_gender = isset($_POST['selected_gender']) ? sanitize_text_field(wp_unslash($_POST['selected_gender'])) : '';
        // phpcs:enable
        $this->character_manager->initialize_new_user_meta($user_id, $selected_race, $selected_gender);
    }

    /**
     * Создает кастомные роли для рас
     */
    public function create_custom_roles() {
        $subscriber_role = get_role('subscriber');
        $capabilities    = $subscriber_role && isset($subscriber_role->capabilities) ? $subscriber_role->capabilities : ['read' => true];
        $races           = ['orc' => 'Orc', 'elf' => 'Elf', 'human' => 'Human', 'dwarf' => 'Dwarf'];
        foreach ($races as $role_slug => $role_name) {
            if (!get_role($role_slug)) {
                add_role($role_slug, __($role_name, 'woodmart-child'), $capabilities);
            }
        }
    }

    /**
     * Регистрирует запланированные события WordPress
     */
    public function register_scheduled_events() {
        // Еженедельное назначение эльфийских товаров
        if (!wp_next_scheduled('wcrpg_assign_elf_items_weekly_event')) {
            wp_schedule_event(strtotime('next Sunday midnight'), 'weekly', 'wcrpg_assign_elf_items_weekly_event');
        }

        // Еженедельная выдача купонов Людям
        if (!wp_next_scheduled('wcrpg_issue_weekly_human_coupon_event')) {
            wp_schedule_event(strtotime('next Monday midnight'), 'weekly', 'wcrpg_issue_weekly_human_coupon_event');
        }
    }

    /**
     * Регистрирует endpoint для страницы "Персонаж"
     */
    public function register_character_page_endpoint() {
        add_rewrite_endpoint('character', EP_ROOT | EP_PAGES);
    }

    /**
     * Добавляет вкладку "Персонаж" в меню "Мой аккаунт"
     */
    public function add_character_tab_to_account_menu($items) {
        $new_items   = [];
        $logout_item = null;

        if (isset($items['customer-logout'])) {
            $logout_item = $items['customer-logout'];
            unset($items['customer-logout']);
        }

        $character_tab_added = false;
        $order_keys          = ['orders', 'downloads', 'edit-address', 'edit-account'];

        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if (in_array($key, $order_keys, true) && !isset($new_items['character'])) {
                $temp_after = [];
                $found      = false;

                foreach ($new_items as $n_key => $n_val) {
                    if ($n_key === $key) {
                        $found = true;
                    }
                    if ($found && $n_key !== $key) {
                        $temp_after[$n_key] = $n_val;
                        unset($new_items[$n_key]);
                    }
                }

                $new_items['character'] = __('Персонаж', 'woodmart-child');
                foreach ($temp_after as $n_key => $n_val) {
                    $new_items[$n_key] = $n_val;
                }

                $character_tab_added = true;
            }
        }

        if (!$character_tab_added && !isset($new_items['character'])) {
            $new_items['character'] = __('Персонаж', 'woodmart-child');
        }

        if ($logout_item) {
            $new_items['customer-logout'] = $logout_item;
        }

        return $new_items;
    }

    /**
     * Регистрирует шорткоды темы
     */
    public function register_shortcodes() {
        add_shortcode('custom_register_form', [RegisterFormShortcode::class, 'render']);
        add_shortcode('gender_select', [GenderSelectShortcode::class, 'render']);
    }

    /**
     * Выдает ежедневный купон Людям, если это применимо
     */
    public function maybe_issue_daily_human_coupon() {
        if (!is_user_logged_in() || is_admin()) {
            return;
        }

        $user_id = get_current_user_id();
        Human::issue_daily_coupon_for_user($user_id, $this->character_manager);
    }

    /**
     * Запускает еженедельную выдачу купонов Людям (для cron)
     */
    public function run_weekly_human_coupon_issuance_job() {
        $human_users = get_users(['role__in' => ['human']]);
        if (!empty($human_users)) {
            foreach ($human_users as $user) {
                Human::issue_weekly_coupon_for_user($user->ID, $this->character_manager);
            }
        }
    }

    /**
     * Геттер для DokanIntegrationManager
     */
    public function get_dokan_integration_manager() {
        return $this->dokan_integration_manager;
    }

    /**
     * Запускает регистрацию всех хуков
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Предотвращаем клонирование
     */
    private function __clone() {}

    /**
     * Предотвращаем десериализацию
     *
     * @throws \Exception
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize a singleton.');
    }
}
<?php
namespace WoodmartChildRPG\CLI;

use WP_CLI;
use WoodmartChildRPG\RPG\Character;
use WoodmartChildRPG\RPG\RaceFactory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AbilityCheckCommand {
    /**
     * Проверяет расу и способности пользователя.
     *
     * ## OPTIONS
     *
     * [--user_id=<id>]
     * : ID пользователя. Если не указан, используется текущий авторизованный пользователь.
     */
    public function __invoke( $args, $assoc_args ) {
        $user_id = isset( $assoc_args['user_id'] ) ? intval( $assoc_args['user_id'] ) : get_current_user_id();
        if ( ! $user_id ) {
            WP_CLI::error( 'Не удалось определить пользователя.' );
        }

        $character = new Character();
        $race_slug = $character->get_race( $user_id );
        if ( ! $race_slug ) {
            WP_CLI::error( 'У пользователя не установлена раса.' );
        }

        $race_obj = RaceFactory::create_race( $race_slug );
        if ( ! $race_obj ) {
            WP_CLI::error( "Неизвестная раса {$race_slug}." );
        }

        $level = $character->get_level( $user_id );
        $bonus_desc = $race_obj->get_passive_bonus_description( $user_id );

        WP_CLI::log( "Раса: {$race_slug}" );
        WP_CLI::log( "Уровень: {$level}" );
        WP_CLI::log( "Пассивные бонусы: {$bonus_desc}" );
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'rpg-check-ability', __NAMESPACE__ . '\\AbilityCheckCommand' );
}

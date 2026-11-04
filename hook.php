<?php

/**
 * -------------------------------------------------------------------------
 * Escalade plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Escalade.
 *
 * Escalade is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Escalade is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Escalade. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2015-2023 by Escalade plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/escalade
 * -------------------------------------------------------------------------
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_escalade_install()
{
    /** @var DBmysql $DB */
    global $DB;

    //init migration
    $migration = new Migration(PLUGIN_ESCALADE_VERSION);

    $default_charset = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

    // == Tables creation (initial installation) ==
    if (!$DB->tableExists('glpi_plugin_escalade_histories')) {
        $query = "CREATE TABLE `glpi_plugin_escalade_histories` (
         `id`              INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `tickets_id`      INT {$default_key_sign} NOT NULL,
         `groups_id`       INT {$default_key_sign} NOT NULL,
         `date_mod`        TIMESTAMP NOT NULL,
         PRIMARY KEY (`id`),
         KEY `tickets_id` (`tickets_id`),
         KEY `groups_id` (`groups_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    if (!$DB->tableExists('glpi_plugin_escalade_configs')) {
        $query = "CREATE TABLE `glpi_plugin_escalade_configs` (
         `id`                                      INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `remove_group`                            INT NOT NULL,
         `show_history`                            INT NOT NULL,
         `task_history`                            INT NOT NULL,
         `remove_tech`                             INT NOT NULL,
         `solve_return_group`                      INT NOT NULL,
         `reassign_group_from_cat`                 INT NOT NULL,
         `reassign_tech_from_cat`                  INT NOT NULL,
         `cloneandlink_ticket`                     INT NOT NULL,
         `close_linkedtickets`                     INT NOT NULL,
         `assign_me_as_observer`                   INT NOT NULL,
         `use_assign_user_group`                   INT NOT NULL,
         `use_assign_user_group_creation`          INT NOT NULL,
         `use_assign_user_group_modification`      INT NOT NULL,
         `remove_delete_requester_user_btn`        TINYINT NOT NULL DEFAULT 1,
         `remove_delete_watcher_user_btn`          TINYINT NOT NULL DEFAULT 1,
         `remove_delete_assign_user_btn`           TINYINT NOT NULL DEFAULT 0,
         `remove_delete_requester_group_btn`       TINYINT NOT NULL DEFAULT 1,
         `remove_delete_watcher_group_btn`         TINYINT NOT NULL DEFAULT 1,
         `remove_delete_assign_group_btn`          TINYINT NOT NULL DEFAULT 0,
         `remove_delete_assign_supplier_btn`       TINYINT NOT NULL DEFAULT 1,
         `use_filter_assign_group`                 INT NOT NULL,
         `ticket_last_status`                      INT NOT NULL,
         `remove_requester`                        INT NOT NULL,
         `task_private`                            INT NOT NULL,
         PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);

        $query = "INSERT INTO glpi_plugin_escalade_configs
      VALUES (NULL, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, 0, 0, 0, 1, 1, 0, 1, 1, 0, 1, 0, '" . Ticket::WAITING . "',0, 1)";
        $DB->doQuery($query);
    }

    // == Update to 1.2 ==
    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'cloneandlink_ticket')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'cloneandlink_ticket',
            'integer',
            ['after' => 'reassign_tech_from_cat'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'close_linkedtickets')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'close_linkedtickets',
            'integer',
            ['after' => 'cloneandlink_ticket'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'use_assign_user_group')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'use_assign_user_group',
            'integer',
            ['after' => 'close_linkedtickets'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'use_assign_user_group_creation')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'use_assign_user_group_creation',
            'integer',
            ['after' => 'use_assign_user_group'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'use_assign_user_group_modification')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'use_assign_user_group_modification',
            'integer',
            ['after' => 'use_assign_user_group_creation'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    if (
        ! isIndex("glpi_plugin_escalade_histories", 'tickets_id')
        || ! isIndex("glpi_plugin_escalade_histories", 'groups_id')
    ) {
        $migration->addKey("glpi_plugin_escalade_histories", 'tickets_id', 'tickets_id');
        $migration->addKey("glpi_plugin_escalade_histories", 'groups_id', 'groups_id');
        $migration->migrationOneTable('glpi_plugin_escalade_histories');
    }

    // == Update to 1.3 ==
    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'use_filter_assign_group')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'use_filter_assign_group',
            'integer',
            ['after' => 'use_assign_user_group_modification'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    if (!$DB->tableExists('glpi_plugin_escalade_groups_groups')) {
        $query = "CREATE TABLE `glpi_plugin_escalade_groups_groups` (
         `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `groups_id_source` int {$default_key_sign} NOT NULL DEFAULT '0',
         `groups_id_destination` int {$default_key_sign} NOT NULL DEFAULT '0',
         PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    // Update for 0.84 status
    $escalade_fields = $DB->listFields('glpi_plugin_escalade_configs');
    if ($DB->tableExists('glpi_plugin_escalade_configs') && !str_starts_with($escalade_fields['ticket_last_status']['Type'], 'int')) {
        foreach ($DB->request(['FROM' => 'glpi_plugin_escalade_configs']) as $data) {
            $statusMap = [
                'solved'  => Ticket::SOLVED,
                'waiting' => Ticket::WAITING,
                'closed'  => Ticket::CLOSED,
                'assign'  => Ticket::ASSIGNED,
                'new'     => Ticket::INCOMING,
                'plan'    => Ticket::PLANNED,
            ];
            $status = $statusMap[$data['ticket_last_status']] ?? -1; // Status managed by core, not by plugin

            $query = "UPDATE `glpi_plugin_escalade_configs`
                   SET `ticket_last_status` = '" . $status . "'
                   WHERE `id` = '" . $data['id'] . "'";
            $DB->doQuery($query);
        }

        $query = "ALTER TABLE `glpi_plugin_escalade_configs` MODIFY `ticket_last_status` INT;";
        $DB->doQuery($query);
    }

    // update to 0.85-1.0
    if ($DB->fieldExists("glpi_plugin_escalade_configs", "assign_me_ticket")) {
        // assign me ticket feature native in glpi 0.85
        $migration->dropField("glpi_plugin_escalade_configs", "assign_me_ticket");
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    // update to 0.90-1.1
    if (!$DB->tableExists('glpi_plugin_escalade_users')) {
        $query = "CREATE TABLE `glpi_plugin_escalade_users` (
                  `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                  `users_id` INT {$default_key_sign} NOT NULL,
                  `use_filter_assign_group` TINYINT NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  INDEX `users_id` (`users_id`)
               )
               ENGINE=InnoDB;";
        $DB->doQuery($query);

        $config = $DB->request([
            'SELECT' => ['use_filter_assign_group'],
            'FROM'   => 'glpi_plugin_escalade_configs',
            'WHERE'  => ['id' => 1],
        ])->current();
        $default_value = (int) ($config['use_filter_assign_group'] ?? 0);

        $user = new User();
        foreach ($user->find() as $data) {
            $query = "INSERT INTO glpi_plugin_escalade_users (`users_id`, `use_filter_assign_group`)
                     VALUES (" . $data['id'] . sprintf(', %s)', $default_value);
            $DB->doQuery($query);
        }
    }

    // ## update to 2.2.0
    // add new fields
    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'remove_delete_requester_user_btn')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'remove_delete_requester_user_btn',
            'bool',
            ['value' => '1'],
        );
    }

    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'remove_delete_requester_group_btn')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'remove_delete_requester_group_btn',
            'bool',
            ['value' => '1'],
        );
    }

    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'remove_delete_watcher_user_btn')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'remove_delete_watcher_user_btn',
            'bool',
            ['value' => '1'],
        );
    }

    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'remove_delete_watcher_group_btn')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'remove_delete_watcher_group_btn',
            'bool',
            ['value' => '1'],
        );
    }

    // migrate old fields
    if ($DB->fieldExists('glpi_plugin_escalade_configs', 'remove_delete_user_btn')) {
        $migration->changeField(
            'glpi_plugin_escalade_configs',
            'remove_delete_user_btn',
            'remove_delete_assign_user_btn',
            'bool',
            ['value' => 0],
        );
    }

    if ($DB->fieldExists('glpi_plugin_escalade_configs', 'remove_delete_group_btn')) {
        $migration->changeField(
            'glpi_plugin_escalade_configs',
            'remove_delete_group_btn',
            'remove_delete_assign_group_btn',
            'bool',
            ['value' => 0],
        );
    }

    if ($DB->fieldExists('glpi_plugin_escalade_configs', 'remove_delete_supplier_btn')) {
        $migration->changeField(
            'glpi_plugin_escalade_configs',
            'remove_delete_supplier_btn',
            'remove_delete_assign_supplier_btn',
            'bool',
            ['value' => 1],
        );
    }

    $migration->migrationOneTable('glpi_plugin_escalade_configs');

    if ($DB->fieldExists('glpi_plugin_escalade_histories', 'previous_groups_id')) {
        $migration->changeField('glpi_plugin_escalade_histories', 'previous_groups_id', 'groups_id_previous', sprintf('INT %s NOT NULL DEFAULT 0', $default_key_sign));
    }

    $migration->migrationOneTable('glpi_plugin_escalade_histories');

    if (
        !$DB->fieldExists('glpi_plugin_escalade_histories', 'groups_id_previous')
        || !$DB->fieldExists("glpi_plugin_escalade_histories", 'counter')
    ) {
        if (!$DB->fieldExists('glpi_plugin_escalade_histories', 'groups_id_previous')) {
            $migration->addField('glpi_plugin_escalade_histories', 'groups_id_previous', sprintf('INT %s NOT NULL DEFAULT 0', $default_key_sign), ['before' => 'groups_id']);
        }

        if (!$DB->fieldExists('glpi_plugin_escalade_histories', 'counter')) {
            $migration->addField('glpi_plugin_escalade_histories', 'counter', 'integer', ['after' => 'groups_id']);
        }

        $migration->migrationOneTable('glpi_plugin_escalade_histories');

        $histories = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_escalade_histories']) as $data) {
            $tickets_id = $data['tickets_id'];
            unset($data['tickets_id']);

            $histories[$tickets_id] ??= [];

            $histories[$tickets_id][] = $data;
        }

        foreach ($histories as $h) {
            $counters = [];

            foreach ($h as $k => $details) {
                if (isset($h[$k + 1])) {
                    $first  = $h[$k + 1]['groups_id'] < $details['groups_id'] ? $h[$k + 1]['groups_id'] : $details['groups_id'];
                    $second = $h[$k + 1]['groups_id'] < $details['groups_id'] ? $details['groups_id'] : $h[$k + 1]['groups_id'];

                    $counters[$first][$second] = isset($counters[$first][$second]) ? $counters[$first][$second] + 1 : 1;
                    $h[$k + 1]['groups_id_previous'] = $details['groups_id'];
                    $h[$k + 1]['counter'] = $counters[$first][$second];
                }
            }

            foreach ($h as $details) {
                $DB->update(
                    'glpi_plugin_escalade_histories',
                    ['groups_id_previous' => $details['groups_id_previous'], 'counter' => $details['counter']],
                    ['id' => $details['id']],
                );
            }
        }
    }

    //Update to 2.6.3
    // add new fields
    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'remove_requester')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'remove_requester',
            'integer',
            ['after' => 'ticket_last_status'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    //Update to 2.9.7
    // add new fields
    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'assign_me_as_observer')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'assign_me_as_observer',
            'integer',
            ['after' => 'close_linkedtickets'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    //Update to 2.9.10
    // change fields name
    if ($DB->fieldExists('glpi_plugin_escalade_users', 'use_filter_assign_group')) {
        $migration->changeField('glpi_plugin_escalade_users', 'use_filter_assign_group', 'bypass_filter_assign_group', 'bool');
        $migration->migrationOneTable('glpi_plugin_escalade_users');
    }

    //Update to 2.10.2
    // add new fields
    if (!$DB->fieldExists('glpi_plugin_escalade_configs', 'task_private')) {
        $migration->addField(
            'glpi_plugin_escalade_configs',
            'task_private',
            'integer',
            ['after' => 'remove_requester'],
        );
        $migration->migrationOneTable('glpi_plugin_escalade_configs');
    }

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_escalade_uninstall()
{
    /** @var DBmysql $DB */
    global $DB;

    //Delete plugin's table
    $tables = [
        'glpi_plugin_escalade_histories',
        'glpi_plugin_escalade_configs',
        'glpi_plugin_escalade_groups_groups',
        'glpi_plugin_escalade_users',
    ];
    foreach ($tables as $table) {
        $DB->doQuery(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    }

    return true;
}

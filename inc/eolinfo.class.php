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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Message\MessageType;
use Glpi\Migration\AbstractPluginMigration;

/**
 * End of Life information of Escalade: its features are now provided by the MoreOptions plugin,
 * this version only helps migrating the group filter (see the `plugins:moreoptions:migrate_escalade`
 * command of MoreOptions).
 */
class PluginEscaladeEOLInfo extends CommonGLPI
{
    public const MIGRATION_COMMAND = 'php bin/console plugins:moreoptions:migrate_escalade';

    public const LINKS_TABLE = 'glpi_plugin_escalade_groups_groups';

    public const MOREOPTIONS_LINKS_TABLE = 'glpi_plugin_moreoptions_groups_links';

    /**
     * Migration provided by MoreOptions, referenced by name: MoreOptions may not be installed.
     */
    public const MIGRATION_CLASS = 'GlpiPlugin\\Moreoptions\\Migration\\EscaladePluginMigration';

    public static string $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __s('Escalade (End Of Life)', 'escalade');
    }

    public static function getIcon()
    {
        return 'ti ti-alert-triangle';
    }

    public static function canView(): bool
    {
        return Session::haveRight(Config::$rightname, READ);
    }

    /**
     * Show the End of Life notice.
     *
     * @return void
     */
    public function showForm()
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        TemplateRenderer::getInstance()->display('@escalade/eol_info.html.twig', [
            'plugin_version' => PLUGIN_ESCALADE_VERSION,
            'plugin_web_dir' => $CFG_GLPI['root_doc'] . '/plugins/escalade',
        ]);
    }

    /**
     * Show the migration status of the group filter of Escalade.
     *
     * @return void
     */
    public static function showMigrationStatus()
    {
        TemplateRenderer::getInstance()->display('@escalade/migration_status.html.twig', [
            'plugin_version'      => PLUGIN_ESCALADE_VERSION,
            'migration_command'   => self::MIGRATION_COMMAND,
            'moreoptions_active'  => Plugin::isPluginActive('moreoptions'),
            'moreoptions_ready'   => self::isMoreoptionsReady(),
            'can_migrate'         => self::canMigrate(),
            'links'               => self::getLinksStatus(),
            'filter_active'       => self::isFilterActive(),
            'bypassing_users'     => self::countBypassingUsers(),
        ]);
    }

    /**
     * Whether the migration can be run from the interface: MoreOptions is active and provides it.
     */
    public static function canMigrate(): bool
    {
        return Plugin::isPluginActive('moreoptions')
            && self::isMoreoptionsReady()
            && is_a(self::MIGRATION_CLASS, AbstractPluginMigration::class, true);
    }

    /**
     * @return class-string<AbstractPluginMigration>
     */
    public static function getMigrationClass(): string
    {
        $class = self::MIGRATION_CLASS;
        if (!is_a($class, AbstractPluginMigration::class, true)) {
            throw new RuntimeException('The migration of MoreOptions is not available.');
        }

        return $class;
    }

    /**
     * Run (or simulate) the migration of MoreOptions from the interface, its result being shown as
     * session messages, like the `plugins:moreoptions:migrate_escalade` command shows it.
     */
    public static function runMigration(bool $simulate): void
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!self::canMigrate()) {
            Session::addMessageAfterRedirect(
                __s('Install and enable the MoreOptions plugin before running the migration.', 'escalade'),
                false,
                ERROR,
            );
            return;
        }

        $class     = self::getMigrationClass();
        $migration = new $class($DB);
        $result    = $migration->execute($simulate);

        foreach ($result->getMessages() as $message) {
            $type = match ($message['type']) {
                MessageType::Error   => ERROR,
                MessageType::Warning => WARNING,
                MessageType::Debug   => null,
                default              => INFO,
            };
            if ($type !== null) {
                Session::addMessageAfterRedirect(htmlescape($message['message']), false, $type);
            }
        }

        $created = array_sum(array_map(count(...), $result->getCreatedItemsIds()));
        $reused  = array_sum(array_map(count(...), $result->getReusedItemsIds()));
        $success = $result->isFullyProcessed() && !$result->hasErrors();

        if (!$success) {
            $summary = __s('The migration failed, all changes have been reverted.', 'escalade');
        } elseif ($simulate) {
            $summary = sprintf(__s('Simulation: %1$d items would be created, %2$d reused. Nothing has been changed.', 'escalade'), $created, $reused);
        } else {
            $summary = sprintf(__s('Migration done: %1$d items created, %2$d reused.', 'escalade'), $created, $reused);
        }

        Session::addMessageAfterRedirect($summary, false, $success ? INFO : ERROR);
    }

    /**
     * Whether MoreOptions can receive the group filter: its escalation tree is installed.
     */
    private static function isMoreoptionsReady(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        return $DB->tableExists(self::MOREOPTIONS_LINKS_TABLE);
    }

    /**
     * The links of the group filter of Escalade, each one with whether MoreOptions has a link
     * between the same groups in the root entity, where the migration puts them.
     *
     * @return list<array{source: string, destination: string, migrated: bool}>
     */
    private static function getLinksStatus(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!$DB->tableExists(self::LINKS_TABLE)) {
            return [];
        }

        $migrated = [];
        if (self::isMoreoptionsReady()) {
            $rows = $DB->request([
                'SELECT' => ['groups_id_source', 'groups_id_destination'],
                'FROM'   => self::MOREOPTIONS_LINKS_TABLE,
                'WHERE'  => ['entities_id' => 0],
            ]);
            foreach ($rows as $row) {
                $migrated[$row['groups_id_source'] . '-' . $row['groups_id_destination']] = true;
            }
        }

        $links = [];
        $rows  = $DB->request([
            'SELECT'    => [
                'gg.groups_id_source',
                'gg.groups_id_destination',
                'source.completename AS source_name',
                'destination.completename AS destination_name',
            ],
            'FROM'      => self::LINKS_TABLE . ' AS gg',
            'LEFT JOIN' => [
                Group::getTable() . ' AS source' => [
                    'ON' => ['gg' => 'groups_id_source', 'source' => 'id'],
                ],
                Group::getTable() . ' AS destination' => [
                    'ON' => ['gg' => 'groups_id_destination', 'destination' => 'id'],
                ],
            ],
            'ORDER'     => ['source.completename', 'destination.completename'],
        ]);
        foreach ($rows as $row) {
            $links[] = [
                'source'      => $row['source_name'] ?? sprintf(__s('Deleted group #%d', 'escalade'), $row['groups_id_source']),
                'destination' => $row['destination_name'] ?? sprintf(__s('Deleted group #%d', 'escalade'), $row['groups_id_destination']),
                'migrated'    => isset($migrated[$row['groups_id_source'] . '-' . $row['groups_id_destination']]),
            ];
        }

        return $links;
    }

    private static function isFilterActive(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!$DB->tableExists('glpi_plugin_escalade_configs') || !$DB->fieldExists('glpi_plugin_escalade_configs', 'use_filter_assign_group')) {
            return false;
        }

        $config = $DB->request([
            'SELECT' => ['use_filter_assign_group'],
            'FROM'   => 'glpi_plugin_escalade_configs',
            'WHERE'  => ['id' => 1],
        ])->current();

        return (int) ($config['use_filter_assign_group'] ?? 0) === 1;
    }

    private static function countBypassingUsers(): int
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!$DB->tableExists('glpi_plugin_escalade_users') || !$DB->fieldExists('glpi_plugin_escalade_users', 'bypass_filter_assign_group')) {
            return 0;
        }

        return countElementsInTable('glpi_plugin_escalade_users', ['bypass_filter_assign_group' => 1]);
    }
}

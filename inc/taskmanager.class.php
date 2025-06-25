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

class PluginEscaladeTaskmanager
{
    private static ?TicketTask $ticket_task = null;

    public static function setTicketTask(array $input): void
    {
        if (self::$ticket_task === null) {
            self::$ticket_task = new TicketTask();
        }

        if (!self::canAddEscaladeTicketTask()) {
            return;
        }

        if (!empty(self::$ticket_task->input)) {
            return;
        }

        self::$ticket_task->input = $input;
    }

    public static function canAddEscaladeTicketTask(): bool
    {
        if (!$_SESSION['glpi_plugins']['escalade']['config']['task_history']) {
            return false;
        }

        if (
            isset($_SESSION['plugin_escalade']['ticket_creation'])
            && $_SESSION['plugin_escalade']['ticket_creation']
        ) {
            return false;
        }

        return true;
    }

    public static function addTicketTaskInTimeline(): void
    {
        if (!self::canAddEscaladeTicketTask()) {
            return;
        }
        self::$ticket_task->add(self::$ticket_task->input);
        self::resetTicketTask();
    }

    public static function resetTicketTask(): void
    {
        self::$ticket_task = new TicketTask();
    }
}

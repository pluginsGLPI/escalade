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

include("../../../inc/includes.php");
Session::checkLoginUser();

if (!Plugin::isPluginActive('escalade')) {
    echo "Plugin not installed or activated";
    return;
}

if (
    ! isset($_REQUEST['tickets_id'])
    || ! isset($_REQUEST['groups_id'])
) {
    Html::displayErrorAndDie(__("missing parameters", "escalade"));
}

$ticket = new Ticket();
$ticket->getFromDB((int) $_REQUEST['tickets_id']);

if (!$ticket->canAssign()) {
    Html::displayRightError();
}


PluginEscaladeTicket::climb_group((int) $_REQUEST['tickets_id'], (int) $_REQUEST['groups_id']);

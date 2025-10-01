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

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

$escalade_config = $_SESSION['glpi_plugins']['escalade']['config'];

if (!$escalade_config['cloneandlink_ticket']) {
    throw new AccessDeniedHttpException("The user does not have permission to clone ticket.");
}

if (!isset($_REQUEST['tickets_id'])) {
    throw new BadRequestHttpException();
}

$ticket = new Ticket();
if ($ticket->getFromDB($_REQUEST['tickets_id'])) {
    if ($ticket->can($_REQUEST['tickets_id'], READ)) {
        PluginEscaladeTicket::cloneAndLink($_REQUEST['tickets_id']);
    } else {
        throw new AccessDeniedHttpException("The user does not have permission to view this ticket.");
    }
}

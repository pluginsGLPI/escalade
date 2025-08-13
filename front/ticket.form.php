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

include('../../../inc/includes.php');
Session::checkLoginUser();

/** @var array $CFG_GLPI */
global $CFG_GLPI;

if (isset($_POST['escalate'])) {
    $group_id = (int) $_POST['groups_id'];
    $tickets_id = (int) $_POST['tickets_id'];

    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id)) {
        Html::displayNotFoundError();
    }

    // Same right check as in PluginEscaladeTicket::addToTimeline()
    if (!$ticket->canAssign()) {
        Html::displayRightError();
    }

    PluginEscaladeTicket::timelineClimbAction($group_id, $tickets_id, $_POST);

    $track = new Ticket();

    if (!$track->can($_POST["tickets_id"], READ)) {
        Session::addMessageAfterRedirect(
            __('You have been redirected because you no longer have access to this ticket'),
            true,
            ERROR,
        );
        Html::redirect($CFG_GLPI["root_doc"] . "/front/ticket.php");
    }
}

Html::back();

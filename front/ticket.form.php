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

use \Glpi\Toolbox\Sanitizer;

include('../../../inc/includes.php');

// Same right check as in PluginEscaladeTicket::addToTimeline()
Session::checkRight(Ticket::$rightname, Ticket::READALL)
    && Session::checkRight(Ticket::$rightname, Ticket::READASSIGN)
    && Session::checkRight(Ticket::$rightname, Ticket::CREATE);

if (isset($_POST['escalate'])) {
    $group_id = (int)$_POST['groups_id'];
    $tickets_id = (int)$_POST['tickets_id'];

    $group = new Group();
    if ($group_id === 0 || $group->getFromDB($group_id) === false) {
        Session::addMessageAfterRedirect(__('You must select a group.', 'escalade'),false, ERROR);
    } else if (!empty($_POST['comment']) && !empty($tickets_id)) {

        if ((bool)$_POST['is_observer_checkbox']) {
            $ticket_user = new Ticket_User();
            $ticket_user->add([
                'type'       => CommonITILActor::OBSERVER,
                'tickets_id' => $tickets_id,
                'users_id'   => Session::getLoginUserID(),
            ]);
        }

        $task = new TicketTask();
        $task->add([
            'tickets_id' => $tickets_id,
            'is_private' => true,
            'state'      => Planning::INFO,
            // Sanitize before merging with $_POST['comment'] which is already sanitized
            'content'    => Sanitizer::sanitize(
                '<p><i>' . sprintf(__('Escalation to the group %s.', 'escalade'), Sanitizer::unsanitize($group->getName())) . '</i></p><hr />'
            ) . $_POST['comment']
        ]);

        $group_ticket = new Group_Ticket();
        $group_ticket->add([
            'type'       => CommonITILActor::ASSIGN,
            'groups_id'  => $group_id,
            'tickets_id' => $tickets_id,
            '_plugin_escalade_no_history' => true, // Prevent a duplicated task to be added
        ]);
    }
}

Html::back();

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

use Glpi\Toolbox\Sanitizer;

include('../../../inc/includes.php');
Session::checkLoginUser();

/** @var array $CFG_GLPI */
global $CFG_GLPI;

if (isset($_POST['escalate'])) {
    $group_id = (int)$_POST['groups_id'];
    $tickets_id = (int)$_POST['tickets_id'];

    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id)) {
        Html::displayNotFoundError();
    }

    // Same right check as in PluginEscaladeTicket::addToTimeline()
    if (!$ticket->canAssign()) {
        Html::displayRightError();
    }

    $group = new Group();
    if ($group_id === 0 || $group->getFromDB($group_id) === false) {
        Session::addMessageAfterRedirect(__('You must select a group.', 'escalade'), false, ERROR);
    } else if (!empty($_POST['comment']) && !empty($tickets_id)) {
        if ((bool)$_POST['is_observer_checkbox']) {
            $ticket_user = new Ticket_User();
            $ticket_user->add([
                'type'       => CommonITILActor::OBSERVER,
                'tickets_id' => $tickets_id,
                'users_id'   => Session::getLoginUserID(),
            ]);
        }

        // Update the ticket with actor data in order to execute the necessary rules
        $_form_object = [
            '_do_not_compute_status' => true,
        ];
        if ($_SESSION['glpi_plugins']['escalade']['config']['ticket_last_status'] != -1) {
            $_form_object['status'] = $_SESSION['glpi_plugins']['escalade']['config']['ticket_last_status'];
        }
        $updates_ticket = new Ticket();
        if (
            $updates_ticket->update(
                $_POST['ticket_details'] + [
                    '_actors' => PluginEscaladeTicket::getTicketFieldsWithActors($tickets_id, $group_id),
                    '_plugin_escalade_no_history' => true, // Prevent a duplicated task to be added
                    'actortype' => CommonITILActor::ASSIGN,
                    'groups_id' => $group_id,
                    '_form_object' => $_form_object,
                ]
            )
        ) {
            //notified only the last group assigned
            $ticket = new Ticket();
            $ticket->getFromDB($tickets_id);

            $event = "assign_group";
            NotificationEvent::raiseEvent($event, $ticket);
        }
    }

    $track = new Ticket();

    if (!$track->can($_POST["tickets_id"], READ)) {
        Session::addMessageAfterRedirect(
            __('You have been redirected because you no longer have access to this ticket'),
            true,
            ERROR
        );
        Html::redirect($CFG_GLPI["root_doc"] . "/front/ticket.php");
    }
}

Html::back();

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
 * @copyright Copyright (C) 2015-2022 by Escalade plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/escalade
 * -------------------------------------------------------------------------
 */

include("../../../inc/includes.php");
Session::checkLoginUser();
use \Glpi\Toolbox\Sanitizer;
$groupTicket = new Group_Ticket();
$ticketUser = new Ticket_User();

if (empty($_GET["id"])) {
    $_GET["id"] = "";
}
$tickets_id = $_POST["tickets_id"];

if (isset($_POST["update"])) {
    if (!empty($_POST["groups_id"]) && !empty($_POST["comment"]) && !empty($tickets_id)) {

        $input['tickets_id'] = $tickets_id;
        $input['groups_id'] = $_POST["groups_id"];
        $input['escalade_comment'] = $_POST["comment"];
        $input['type'] = CommonITILActor::ASSIGN;
        if ($_POST['is_observer_checkbox']) {

            $inputTicketUser['type'] = CommonITILActor::OBSERVER;
            $inputTicketUser['tickets_id'] = $tickets_id;
            $inputTicketUser['users_id'] = Session::getLoginUserID();
            $ticketUser->add($inputTicketUser);
        }

        $config = new PluginEscaladeConfig();

        $task = new TicketTask();

        $group = new Group();
        $group->getFromDB($input['groups_id']);
        $content = '';
        $content .= sprintf(__("Escalation to the group %s", "escalade"), $group->getName()) . "<br>";
        $content .= "<strong>" . __('User comment', 'escalade') . "</strong><br>";

        // Sanitize before merging with $input['escalade_comment'] which is already sanitized
        $content = Sanitizer::sanitize($content);
        $content .= $input['escalade_comment'];
        $task->add([
            'tickets_id' => $tickets_id,
            'is_private' => true,
            'state' => Planning::INFO,
            'content' => $content
        ]);
        // We don't want to trigger another task if the "task_history" setting is enabled.
        $input['_plugin_escalade_no_history'] = true;
        $groupTicket->add($input);
    } else{
        Session::addMessageAfterRedirect(__('You must select a group', 'escalade'),false, ERROR);
    }


}

Html::back();
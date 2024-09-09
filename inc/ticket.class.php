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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginEscaladeTicket
{
    public static function pre_item_update(CommonDBTM $item)
    {
        if (isset($input['_itil_assign'])) {
            $item->input['_do_not_compute_status'] = true;
        }
        $config = $_SESSION['plugins']['escalade']['config'];
        $old_groups = [];

        // Get actual actors for the ticket
        if ($item instanceof Ticket) {
            $actorTypes = [CommonITILActor::REQUESTER, CommonITILActor::OBSERVER, CommonITILActor::ASSIGN];
            $ticket_actors = array_reduce(
                $actorTypes,
                function ($carry, $type) use ($item) {
                    $carry[$item->getActorFieldNameType($type)] = $item->getActorsForType($type);
                    return $carry;
                },
                []
            );

            $old_groups = array_filter($ticket_actors['assign'], function ($actor) {
                return isset($actor['itemtype']) && $actor['itemtype'] === 'Group';
            });
        }
        if (!isset($item->input['actortype'])) {
            $groups = new Group_Ticket();
            $groups = $groups->find(['tickets_id' => $item->getID(), 'type' => CommonITILActor::ASSIGN]);
            if (!empty($item->input['_actors'])) {
                foreach ($item->input['_actors']['assign'] as $actors) {
                    if ($actors['itemtype'] == 'Group' && !in_array($actors['items_id'], $groups)) {
                        $item->input['groups_id'] = $actors['items_id'];
                        $item->input['actortype'] = CommonITILActor::ASSIGN;
                    }
                }
            }
        }
        if (
            isset($item->input['_actors']['assign'])
        ) {
            $new_groups = array_filter($item->input['_actors']['assign'], function ($actor) {
                return isset($actor['itemtype']) && $actor['itemtype'] === 'Group';
            });
            if (
                (isset($item->input['actortype']) && $item->input['actortype'] == CommonITILActor::ASSIGN) &&
                (
                    count($old_groups) < count($new_groups)
                )
            ) {
                //disable notification to prevent notification for old AND new group
                $item->input['_disablenotif'] = true;
                if ($_SESSION['plugins']['escalade']['config']['ticket_last_status'] != -1) {
                    $item->input['_do_not_compute_status'] = true;
                    $item->input['status'] = $_SESSION['plugins']['escalade']['config']['ticket_last_status'];
                }
                return PluginEscaladeTicket::addHistoryOnAddGroup($item);
            } else if (count($old_groups) == count($new_groups)) {
                $old_group_ids = [];
                foreach ($old_groups as $old_group) {
                    $old_group_ids[$old_group['items_id']] = true;
                }

                foreach ($new_groups as $new_group) {
                    if (!isset($old_group_ids[$new_group['items_id']])) {
                        $item->input['_disablenotif'] = true;
                        if ($_SESSION['plugins']['escalade']['config']['ticket_last_status'] != -1) {
                            $item->input['_do_not_compute_status'] = true;
                            $item->input['status'] = $_SESSION['plugins']['escalade']['config']['ticket_last_status'];
                        }
                        return PluginEscaladeTicket::addHistoryOnAddGroup($item);
                    }
                }
            }
            return $item;
        }
    }

    /**
     * Provide a redirection to other functions
     * @param  CommonDBTM $item
     * @return void
     */
    public static function item_update(CommonDBTM $item)
    {

        if ($_SESSION['plugins']['escalade']['config']['remove_group']) {
            //solve ticket
            if (isset($item->input['status']) && $item->input['status'] == CommonITILObject::SOLVED) {
                self::AssignFirstGroupOnSolve($item);

                //extend solve linked ticket to status change (when no solution provided)
                if (
                    !in_array("solutiontypes_id", $item->updates)
                    && !in_array("solution", $item->updates)
                ) {
                    self::linkedTickets($item, CommonITILObject::SOLVED);
                }
            }

            //close ticket
            if (isset($item->input['status']) && $item->input['status'] == CommonITILObject::CLOSED) {
                //close linked tickets
                self::linkedTickets($item, CommonITILObject::CLOSED);
            } else if (
                isset($item->input['status'])
                && $item->input['status'] == CommonITILObject::ASSIGNED
                && isset($item->oldvalues['status'])
                && $item->oldvalues['status'] == CommonITILObject::SOLVED
            ) {
                //solution rejected
                self::AssignLastGroupOnRejectedSolution($item);
            }
        }

        //ticket qualification on cat change
        if (isset($item->input['itilcategories_id'])) {
            self::qualification($item);
        }

        // notification on solve date modification
        if (in_array('solvedate', $item->updates)) {
            NotificationEvent::raiseEvent('update_solvedate', $item);
        }
    }


    /**
     * When a ticket is solved, if group histories exists, assign the first group on the ticket
     * @param CommonDBTM $item the ticket object
     */
    public static function AssignFirstGroupOnSolve(CommonDBTM $item)
    {
        if (
            $_SESSION['plugins']['escalade']['config']['remove_group']
            && $_SESSION['plugins']['escalade']['config']['solve_return_group']
        ) {
            $tickets_id = $item->fields['id'];

            $first_history = PluginEscaladeHistory::getFirstLineForTicket($tickets_id);
            $last_history  = PluginEscaladeHistory::getLastLineForTicket($tickets_id);

            //if no history
            if ($first_history === false) {
                return;
            }
            //if first history group == last history group
            if ($first_history['id'] == $last_history['id']) {
                return;
            }

            self::removeAssignGroups($tickets_id, $first_history['groups_id']);
            self::removeAssignUsers($item);

            //set session var to prevent double task message
            $_SESSION['plugin_escalade']['solution'] = true;

            //add the first history group (if not already exist)
            $group_ticket = new Group_Ticket();
            $condition = [
                'tickets_id' => $tickets_id,
                'groups_id'  => $first_history['groups_id'],
                'type'       => CommonITILActor::ASSIGN
            ];
            if (!$group_ticket->find($condition)) {
                $group_ticket->add($condition);
            }

            //add a task to inform the escalation
            if ($_SESSION['plugins']['escalade']['config']['task_history']) {
                $group = new Group();
                $group->getFromDB($first_history['groups_id']);
                $task = new TicketTask();
                $task->add([
                    'tickets_id' => $tickets_id,
                    'is_private' => true,
                    '_no_reopen' => true, //prevent reopening ticket
                    'state'      => Planning::INFO,
                    'content'    => __("Solution provided, back to the group", "escalade") . " " .
                        $group->getName()
                ]);
            }
        }
    }


    /**
     * When a ticket solution is rejected, if group histories exists,
     * assign the last group on the ticket
     * @param CommonDBTM $item the ticket object
     */
    public static function AssignLastGroupOnRejectedSolution(CommonDBTM $item)
    {
        if (!isset($_POST['add_reopen'])) {
            return;
        }

        if (
            $_SESSION['plugins']['escalade']['config']['remove_group']
            && $_SESSION['plugins']['escalade']['config']['solve_return_group']
        ) {
            $tickets_id = $item->fields['id'];

            $last_history  = PluginEscaladeHistory::getLastLineForTicket($tickets_id);
            $full_history  = PluginEscaladeHistory::getFullHistory($tickets_id);
            if (count($full_history) <= 1) {
                return; //no escalation found, return
            }
            array_shift($full_history); //remove current group in history
            $rejected_history = array_shift($full_history); // get previous group

            //if no history
            if ($last_history === false) {
                return;
            }
            //if first history group == last history group
            if ($rejected_history['id'] == $last_history['id']) {
                return;
            }

            self::removeAssignGroups($tickets_id, $rejected_history['groups_id']);

            //set session var to prevent double task message
            $_SESSION['plugin_escalade']['solution'] = true;

            //add the first history group
            $group_ticket = new Group_Ticket();
            $group_ticket->add([
                'tickets_id' => $tickets_id,
                'groups_id'  => $rejected_history['groups_id'],
                'type'       => CommonITILActor::ASSIGN
            ]);

            //add a task to inform the escalation
            if ($_SESSION['plugins']['escalade']['config']['task_history']) {
                $group = new Group();
                $group->getFromDB($rejected_history['groups_id']);
                $task = new TicketTask();
                $task->add([
                    'tickets_id' => $tickets_id,
                    'is_private' => true,
                    'state'      => Planning::INFO,
                    'content'    => __("Solution rejected, return to the group", "escalade") . " " .
                        $group->getName()
                ]);
            }

            //update status
            if ($_SESSION['plugins']['escalade']['config']['ticket_last_status'] != -1) {
                $item->update([
                    'id' => $tickets_id,
                    'status' => $_SESSION['plugins']['escalade']['config']['ticket_last_status']
                ]);
            }
        }
    }


    /**
     *  remove old groups to a ticket when a new group assigned
     *  called by "pre_item_add" hook on Group_Ticket object
     * @param CommonDBTM $item the ticket object
     */
    public static function addHistoryOnAddGroup(CommonDBTM $item)
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($_SESSION['plugins']['escalade']['config']['remove_group'] == false) {
            return true;
        }

        //if group sent is not an assign group, return
        if ($item->input['actortype'] != CommonITILActor::ASSIGN) {
            return;
        }

        $tickets_id = $item->input['id'];
        $groups_id  = $item->input['groups_id'];

        $item->fields['status'] = CommonITILObject::ASSIGNED;

        //add line in history table
        $history = new PluginEscaladeHistory();

        $group_ticket       = new Group_Ticket();
        $group_ticket->getFromDBByRequest([
            'ORDER'   => 'id DESC',
            'LIMIT'      => 1,
            'tickets_id' => $tickets_id,
            'type'       => 2
        ]);

        $previous_groups_id = 0;
        $counter            = 0;

        if (count($group_ticket->fields) > 0) {
            $previous_groups_id = $group_ticket->fields['groups_id'];

            $last_history_groups = PluginEscaladeHistory::getLastHistoryForTicketAndGroup($tickets_id, $groups_id, $previous_groups_id);

            if (count($last_history_groups->fields) > 0) {
                $counter = $last_history_groups->fields['counter'] + 1;
            }
        }

        $history->add([
            'tickets_id'         => $tickets_id,
            'groups_id'          => $groups_id,
            'groups_id_previous' => $previous_groups_id,
            'counter'            => $counter
        ]);

        // check if group assignment is made during ticket creation
        // in this case, skip following steps as it cannot be considered as a group escalation
        $backtraces   = debug_backtrace();
        foreach ($backtraces as $backtrace) {
            if (
                $backtrace['function'] == "add"
                && ($backtrace['object'] instanceof CommonITILObject)
            ) {
                return;
                break;
            }
        }

        //remove old user(s) (pass if user added by new ticket)
        self::removeAssignUsers($item);

        //add a task to inform the escalation (pass if solution)
        if (isset($_SESSION['plugin_escalade']['solution'])) {
            unset($_SESSION['plugin_escalade']['solution']);
            return $item;
        }
        if (
            $_SESSION['plugins']['escalade']['config']['task_history']
            && !($item->input['_plugin_escalade_no_history'] ?? false)
        ) {
            $group = new Group();
            $group->getFromDB($groups_id);

            $task = new TicketTask();
            $task->add([
                'tickets_id' => $tickets_id,
                'is_private' => true,
                'state'      => Planning::INFO,
                'content'    => Toolbox::addslashes_deep(sprintf(__("Escalation to the group %s.", "escalade"), $group->getName())),
            ]);
        }

        if ($_SESSION['plugins']['escalade']['config']['ticket_last_status'] != -1) {
            $ticket = new Ticket();
            $ticket->update([
                'id'     => $tickets_id,
                'status' => $_SESSION['plugins']['escalade']['config']['ticket_last_status']
            ]);
        }

        return $item;
    }

    public static function processAfterAddGroup(CommonDBTM $item)
    {
        $tickets_id = $item->fields['tickets_id'];
        $groups_id = $item->fields['groups_id'];

        //remove old groups (keep last assigned)
        if ($_SESSION['plugins']['escalade']['config']['remove_group'] == true) {
            self::removeAssignGroups($tickets_id, $groups_id);
        }

        //notified only the last group assigned
        $ticket = new Ticket();
        $ticket->getFromDB($tickets_id);

        $event = "assign_group";
        NotificationEvent::raiseEvent($event, $ticket);
    }


    /**
     * @param Ticket $ticket
     * @return bool
     */
    public static function assignUserGroup(Ticket $ticket)
    {
        if (!is_array($ticket->input) || !count($ticket->input)) {
            // Already cancel by another plugin
            return false;
        }

        //check plugin behaviors (for avoid conflict)
        if (Plugin::isPluginActive('behaviors')) {
            // @phpstan-ignore-next-line
            $behavior_config = PluginBehaviorsConfig::getInstance();
            if ($behavior_config->getField('use_assign_user_group') != 0) {
                return false;
            }
        }

        //check this plugin config
        if (
            $_SESSION['plugins']['escalade']['config']['use_assign_user_group'] == 0
            || $_SESSION['plugins']['escalade']['config']['use_assign_user_group_creation'] == 0
        ) {
            return false;
        }

        if (
            isset($ticket->input['_users_id_assign'])
            && $ticket->input['_users_id_assign'] > 0
            && (!isset($ticket->input['_groups_id_assign'])
                || empty($ticket->input['_groups_id_assign']))
        ) {
            if ($_SESSION['plugins']['escalade']['config']['use_assign_user_group'] == 1) {
                // First group
                $ticket->input['_groups_id_assign']
                    = PluginEscaladeUser::getTechnicianGroup(
                        $ticket->input['entities_id'],
                        current($ticket->input['_users_id_assign']),
                        true
                    );
                //prevent adding empty group
                if (empty($ticket->input['_groups_id_assign'])) {
                    unset($ticket->input['_groups_id_assign']);
                }
            } else {
                // All groups
                $ticket->input['_additional_groups_assigns']
                    = PluginEscaladeUser::getTechnicianGroup(
                        $ticket->input['entities_id'],
                        $ticket->input['_users_id_assign'],
                        false
                    );
                //prevent adding empty group
                if (empty($ticket->input['_additional_groups_assigns'])) {
                    unset($ticket->input['_additional_groups_assigns']);
                }
            }
        }

        return true;
    }

    /**
     * assign a previous group to the ticket
     * @param  int $tickets_id the ticket to change
     * @param  int $groups_id  the group to assign
     * @return void
     */
    public static function climb_group($tickets_id, $groups_id)
    {
        //don't add group if already exist for this ticket
        $group_ticket = new Group_Ticket();
        $condition = [
            'tickets_id' => $tickets_id,
            'groups_id'  => $groups_id,
            'type'       => CommonITILActor::ASSIGN
        ];
        if (!$group_ticket->find($condition)) {
            $ticket = new Ticket();
            $ticket->getFromDB($tickets_id);

            // Update the ticket with actor data in order to execute the necessary rules
            $_form_object = [
                '_do_not_compute_status' => true,
            ];
            if ($_SESSION['plugins']['escalade']['config']['ticket_last_status'] != -1) {
                $_form_object['status'] = $_SESSION['plugins']['escalade']['config']['ticket_last_status'];
            }
            $ticket_details = $_POST['ticket_details'] ?? $_GET['ticket_details'] ?? [];
            $updates_ticket = new Ticket();
            $updates_ticket->update($ticket_details + [
                '_actors' => PluginEscaladeTicket::getTicketFieldsWithActors($tickets_id, $groups_id),
                '_plugin_escalade_no_history' => false,
                'actortype' => CommonITILActor::ASSIGN,
                'groups_id' => $groups_id,
                '_form_object' => $_form_object,
            ]);
        }

        Html::back();
    }


    /**
     * Clean all assigned groups for the ticket
     * @param  int $tickets_id
     * @return void
     */
    public static function removeAssignGroups($tickets_id, $keep_groups_id = false)
    {
        $where_keep = [
            'tickets_id' => $tickets_id,
            'type'       => CommonITILActor::ASSIGN,
        ];
        if ($keep_groups_id !== false) {
            $where_keep[] = ['NOT' => ['groups_id' => $keep_groups_id]];
        }

        $group_ticket = new Group_Ticket();
        $found = $group_ticket->find($where_keep);
        foreach ($found as $id => $gt) {
            $group_ticket->delete($gt);
        }

        //add a var to prevent status changes unwanted
        $_SESSION['plugin_escalade']['remove_assign'] = true;
    }


    /**
     * Clean all assigned users for the ticket
     * @param  CommonDBTM $item the ticket object
     * @return void
     */
    public static function removeAssignUsers($item, $keep_users_id = false, $type = CommonITILActor::ASSIGN)
    {
        if (
            $_SESSION['plugins']['escalade']['config']['remove_tech'] == false
            && $_SESSION['plugins']['escalade']['config']['remove_requester'] == false
        ) {
            return true;
        }
        if ($type == CommonITILActor::ASSIGN && !$_SESSION['plugins']['escalade']['config']['remove_tech']) {
            return true;
        }
        if ($type == CommonITILActor::REQUESTER && !$_SESSION['plugins']['escalade']['config']['remove_requester']) {
            return true;
        }

        $tickets_id = $item->input['id'] ?? $item->fields['id'];

        $where_keep = [
            'tickets_id' => $tickets_id,
            'type' => $type
        ];
        if ($keep_users_id !== false) {
            $where_keep[] = ['NOT' => ['users_id' => $keep_users_id]];
        }

        $types = [
            CommonITILActor::ASSIGN => 'assign',
            CommonITILActor::REQUESTER => 'requester'
        ];

        $ticket_user = new Ticket_User();
        $found = $ticket_user->find($where_keep);
        foreach ($found as $id => $tu) {
            //if user must be keeped (see item_add_user function)
            if (
                isset($_SESSION['plugin_escalade']['keep_users'])
                && is_array($_SESSION['plugin_escalade']['keep_users'])
                && in_array($tu['users_id'], $_SESSION['plugin_escalade']['keep_users'])
            ) {
                continue;
            }

            //delete user
            $ticket_user->delete(['id' => $id]);

            if (isset($item->input['_actors'])) {
                foreach ($item->input['_actors'][$types[$type]] as $key => $actor) {
                    if (
                        $actor['items_id'] == $tu['users_id']
                        && $actor['itemtype'] == User::class
                    ) {
                        unset($item->input['_actors'][$types[$type]][$key]);
                    }
                }
            }
        }

        //clean session var (to prevent users be keeped post to this ticket update)
        unset($_SESSION['plugin_escalade']['keep_users']);

        //add a var to prevent status changes unwanted
        $_SESSION['plugin_escalade']['remove_assign'] = true;
    }


    /**
     * Update ticket status when user added.
     * Trigger also adding user groups if feature enabled
     * @param  Ticket_User $item Ticket_User object
     * @return void
     */
    public static function item_add_user(Ticket_User $item, $type = CommonITILActor::ASSIGN)
    {
        $users_id   = $item->input['users_id'];
        $tickets_id = $item->input['tickets_id'];
        $ticket = new Ticket();
        $ticket->getFromDB($tickets_id);
        $groups_id = [];

        self::removeAssignUsers($ticket, $users_id, $type);

        // == Add user groups on modification ==
        //check this plugin config
        if (
            $_SESSION['plugins']['escalade']['config']['use_assign_user_group'] == 0
            || $_SESSION['plugins']['escalade']['config']['use_assign_user_group_modification'] == 0
        ) {
            return true;
        }

        if ($_SESSION['plugins']['escalade']['config']['use_assign_user_group'] == 1) {
            // First group
            $groups_id = PluginEscaladeUser::getTechnicianGroup(
                $ticket->fields['entities_id'],
                $item->fields['users_id'],
                true
            );
        } else {
            // All groups
            $groups_id = PluginEscaladeUser::getTechnicianGroup(
                $ticket->fields['entities_id'],
                $item->fields['users_id'],
                false
            );
        }

        if (!empty($groups_id)) {
            $group_ticket = new Group_Ticket();

            //The ticket cannot have this group already assigned
            $found = $group_ticket->find([
                'tickets_id' => $tickets_id,
                'groups_id'  => $groups_id,
                'type'       => CommonITILActor::ASSIGN
            ]);
            if (!empty($found)) {
                return;
            }

            //prevent user removal
            $_SESSION['plugin_escalade']['keep_users'][$item->fields['users_id']]
                = $item->fields['users_id'];

            //add new group to ticket
            $group_ticket->add([
                'tickets_id' => $tickets_id,
                'groups_id'  => $groups_id,
                'type'       => CommonITILActor::ASSIGN
            ]);
        } else {
            if ($_SESSION['plugins']['escalade']['config']['remove_tech']) {
                self::removeAssignGroups($tickets_id);
            }
        }

        //fix ticket status
        $ticket->update([
            'id'     => $tickets_id,
            'status' => CommonITILObject::ASSIGNED
        ]);
    }


    /**
     * Close linked tickets when ticket passed in parameter is closed
     * @param  CommonDBTM $item the ticket object
     * @return void
     */
    public static function linkedTickets(CommonDBTM $ticket, $status = CommonITILObject::SOLVED)
    {
        if ($_SESSION['plugins']['escalade']['config']['close_linkedtickets']) {
            $input = [
                'status' => $status
            ];

            $tickets = Ticket_Ticket::getLinkedTicketsTo($ticket->getID());
            if (count($tickets)) {
                $linkedTicket = new Ticket();
                foreach ($tickets as $data) {
                    $input['id'] = $data['tickets_id'];
                    if (
                        $linkedTicket->can($input['id'], UPDATE)
                        && $data['link'] == Ticket_Ticket::DUPLICATE_WITH
                    ) {
                        $linkedTicket->update($input);
                    }
                }
            }
        }
    }


    /**
     * On ticket category change, add ticket category group and user
     * @param  CommonDBTM $item
     * @return void
     */
    public static function qualification(CommonDBTM $item)
    {
        /** @var DBmysql $DB */
        global $DB;

        //get auto-assign mode (config in entity)
        $auto_assign_mode = Entity::getUsedConfig('auto_assign_mode', $_SESSION['glpiactive_entity']);
        if ($auto_assign_mode == Entity::CONFIG_NEVER) {
            return true;
        }

        //get category
        $category = new ITILCategory();
        $category->getFromDB($item->input['itilcategories_id']);

        //category group
        if (
            !empty($category->fields['groups_id'])
            && $_SESSION['plugins']['escalade']['config']['reassign_group_from_cat']
        ) {
            $group_ticket = new Group_Ticket();

            //check if group is not already present
            $group_condition = [
                'tickets_id' => $item->fields['id'],
                'groups_id'  => $category->fields['groups_id'],
                'type'       => CommonITILActor::ASSIGN,
            ];
            $group_found = $group_ticket->find($group_condition);
            if (empty($group_found)) {
                //add group to ticket
                $group_ticket->add($group_condition);
                //remove old group if needed
                if ($_SESSION['plugins']['escalade']['config']['remove_group']) {
                    if (isset($item->input['_groups_id_assign'])) {
                        foreach ($item->input['_groups_id_assign'] as $idActor => $actor) {
                            unset($item->input['_groups_id_assign'][$idActor]);
                            unset($item->input['_groups_id_assign_notif']['use_notification'][$idActor]);
                            unset($item->input['_groups_id_assign_notif']['alternative_email'][$idActor]);
                        }
                    }
                }
            }
        }

        //category user
        if (
            !empty($category->fields['users_id'])
            && $_SESSION['plugins']['escalade']['config']['reassign_tech_from_cat']
        ) {
            $ticket_user = new Ticket_User();

            //check if user is not already present
            $user_condition = [
                'tickets_id' => $item->fields['id'],
                'users_id'   => $category->fields['users_id'],
                'type'       => CommonITILActor::ASSIGN,
            ];
            $user_found = $ticket_user->find($user_condition);
            if (empty($user_found)) {
                //add user to ticket
                $ticket_user->add($user_condition);
                //remove old tech if needed
                if ($_SESSION['plugins']['escalade']['config']['remove_tech']) {
                    if (isset($item->input['_users_id_assign'])) {
                        foreach ($item->input['_users_id_assign'] as $idActor => $actor) {
                            unset($item->input['_users_id_assign'][$idActor]);
                            unset($item->input['_users_id_assign_notif']['use_notification'][$idActor]);
                            unset($item->input['_users_id_assign_notif']['alternative_email'][$idActor]);
                        }
                    }
                }
            }
        }
    }


    /**
     * CLone a ticket and his relations
     * @param  integer $tickets_id id of the ticket to clone
     * @return void print a json response (return nothing)
     */
    public static function cloneAndLink($tickets_id)
    {
        /** @var DBmysql $DB */
        global $DB;

        //get old ticket
        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            Session::addMessageAfterRedirect(__('Error : get old ticket', 'escalade'), false, ERROR);
            exit;
        }

        //set fields
        $fields = $ticket->fields;
        $fields = array_map(['Toolbox', 'addslashes_deep'], $fields);
        $fields['id']                  = 0;
        $fields['_users_id_requester'] = 0;
        $fields['status']              = CommonITILObject::INCOMING;

        /*var_dump($fields);
       exit;*/

        //create new ticket (duplicate from previous)
        if (!$newID = $ticket->add($fields)) {
            Session::addMessageAfterRedirect(__('Error : adding new ticket', 'escalade'), false, ERROR);
            exit;
        }

        //add link between them
        $ticket_ticket = new Ticket_Ticket();
        if (
            !$ticket_ticket->add([
                'tickets_id_1' => $tickets_id,
                'tickets_id_2' => $newID,
                'link'         => Ticket_Ticket::LINK_TO
            ])
        ) {
            Session::addMessageAfterRedirect(__('Error : adding link between the two tickets', 'escalade'), false, ERROR);
            exit;
        }

        //add a followup to indicate duplication
        $followup = new ITILFollowup();
        if (
            !$followup->add([
                'items_id'        => $newID,
                'itemtype'        => Ticket::class,
                'users_id'        => Session::getLoginUserID(),
                'content'         => __("This ticket has been cloned from the ticket num", "escalade") . " " .
                    $tickets_id,
                'is_private'      => true,
                'requesttypes_id' => 6 //other
            ])
        ) {
            Session::addMessageAfterRedirect(__('Error : adding followups', 'escalade'), false, ERROR);
            exit;
        }

        //add actors to the new ticket (without assign)
        //users
        $query_users = "INSERT INTO glpi_tickets_users
      SELECT null AS id, $newID as tickets_id, users_id, type, use_notification, alternative_email
      FROM glpi_tickets_users
      WHERE tickets_id = $tickets_id AND type != 2";
        // @phpstan-ignore-next-line
        if (!$res = $DB->query($query_users)) {
            Session::addMessageAfterRedirect(__('Error : adding actors (user)', 'escalade'), false, ERROR);
            exit;
        }
        //groups
        $query_groups = "INSERT INTO glpi_groups_tickets
      SELECT null AS id, $newID as tickets_id, groups_id, type
      FROM glpi_groups_tickets
      WHERE tickets_id = $tickets_id AND type != 2";
        // @phpstan-ignore-next-line
        if (!$res = $DB->query($query_groups)) {
            Session::addMessageAfterRedirect(__('Error : adding actors (group)', "escalade"), false, ERROR);
            exit;
        }

        //add documents
        $query_docs = "INSERT INTO glpi_documents_items (documents_id, items_id, itemtype, entities_id, is_recursive, date_mod)
      SELECT documents_id, $newID, 'Ticket', entities_id, is_recursive, date_mod
      FROM glpi_documents_items
      WHERE items_id = $tickets_id AND itemtype = 'Ticket'";
        // @phpstan-ignore-next-line
        if (!$res = $DB->query($query_docs)) {
            Session::addMessageAfterRedirect(__('Error : adding documents', 'escalade'), false, ERROR);
            exit;
        }

        //add history to the new ticket
        $changes[0] = '0';
        $changes[1] = __("This ticket has been cloned from the ticket num", "escalade") . " " . $tickets_id;
        $changes[2] = "";
        Log::history($newID, 'Ticket', $changes, 'Ticket');

        //add message (ticket cloned) after redirect
        Session::addMessageAfterRedirect(__("This ticket has been cloned from the ticket num", "escalade") .
            " " . $tickets_id);

        //all ok
        echo "{\"success\":true, \"newID\":$newID}";
    }


    public static function assign_me($tickets_id)
    {

        $tu = new Ticket_User();
        $found = $tu->find([
            'tickets_id' => $tickets_id,
            'users_id'   => $_SESSION['glpiID'],
            'type'       => CommonITILActor::ASSIGN
        ]);

        if (empty($found)) {
            $ticket = new Ticket();
            $ticket->update([
                'id'           => $tickets_id,
                '_itil_assign' => [
                    'users_id' => $_SESSION['glpiID'],
                    '_type'    => 'user'
                ]
            ]);
        }
    }

    public static function filter_actors(array $params = []): array
    {
        $itemtype = $params['params']['itemtype'];
        $items_id = $params['params']['items_id'];

        if ($itemtype == 'Ticket' && $params['params']['actortype'] == 'assign') {
            // find filtered groups
            $PluginEscaladeGroup_Group = new PluginEscaladeGroup_Group();
            $groups_id_filtred = $PluginEscaladeGroup_Group->getGroups($items_id);
            $groups_id_filtred = array_keys($groups_id_filtred);

            foreach ($params['actors'] as $index => &$actor) {
                //remove groups in children nodes
                if (isset($actor['children'])) {
                    foreach ($actor['children'] as $index_child => &$child) {
                        if ($child['itemtype'] == "Group" && !in_array($child['items_id'], $groups_id_filtred)) {
                            unset($actor['children'][$index_child]);
                        }
                    }

                    if (count($actor['children']) > 0) {
                        // reindex correctly children (to avoid select2 fails)
                        $actor['children'] = array_values($actor['children']);
                    } else {
                        // otherwise remove empty parent
                        unset($params['actors'][$index]);
                    }
                } else {
                    // remove direct groups (don't sure this exists)
                    if ($actor['itemtype'] == "Group" && !in_array($actor['items_id'], $groups_id_filtred)) {
                        unset($params['actors'][$index]);
                    }
                }
            }
        }

        return $params;
    }

    public static function addToTimeline($options)
    {
        if (!($options['item'] instanceof Ticket)) {
            return [];
        }

        if (!$options['item']->canAssign()) {
            return [];
        }

        // Groups in the tickets' entity that are not assigned to the current ticket
        $groups = (new Group())->find([
            'is_assign' => 1,
            'NOT' => [
                // Group currently assigned to the ticket
                'id' => new QuerySubQuery([
                    'SELECT' => 'groups_id',
                    'FROM'   => Group_Ticket::getTable(),
                    'WHERE'  => [
                        'tickets_id' => $options['item']->getID(),
                        'type' => CommonITILActor::ASSIGN,
                    ]
                ])
            ],
            // Restrict to ticket entity
            getEntitiesRestrictCriteria(
                Group::getTable(),
                '',
                $options['item']->fields['entities_id'],
                true
            )
        ]);

        $itemtypes = [];
        if (!empty($groups)) {
            $itemtypes['escalation'] = [
                'type' => 'PluginEscaladeTicket',
                'class' => 'action-escalation',
                'icon' => 'ti ti-arrow-up',
                'label' => __('Escalate', 'escalade'),
                'short_label' => __('Escalate', 'escalade'),
                'item' => new self()
            ];
        }

        return $itemtypes;
    }

    /**
     * Get ticket field with actors inputs
     *
     * @param int $tickets_id
     * @param int $group_id
     *
     * @return array
     */
    public static function getTicketFieldsWithActors($tickets_id, $group_id)
    {
        $ticket = new Ticket();
        $link_class = [
            'User' => new $ticket->userlinkclass(),
            'Group' => new $ticket->grouplinkclass(),
            'Supplier' => new $ticket->supplierlinkclass(),
        ];
        $ticket_actors = [];
        $actor_types = [
            CommonITILActor::ASSIGN => 'assign',
            CommonITILActor::OBSERVER => 'observer',
            CommonITILActor::REQUESTER => 'requester',
        ];

        foreach ($link_class as $itemtype => $class) {
            $ticket_actor = $class->getActors($tickets_id);
            foreach ($ticket_actor as $type => $value) {
                $actors_input = [];
                foreach ($value as $val) {
                    $actors_input[] = [
                        'itemtype' => $itemtype,
                        'items_id' => $val[strtolower($itemtype . 's_id')],
                        'alternative_email' => $val['alternative_email'] ?? '',
                    ];
                }
                $actortype = $actor_types[$type] ?? '';
                if ($actortype) {
                    $ticket_actors[$itemtype][$actortype] = $actors_input;
                }
            }
        }
        $ticket_actors['Group']['assign'][] = [
            'itemtype' => 'Group',
            'items_id' => $group_id,
        ];

        $_actors['assign'] = array_merge(
            $ticket_actors['User']['assign'] ?? [],
            $ticket_actors['Group']['assign'] ?? [],
            $ticket_actors['Supplier']['assign'] ?? []
        );
        $_actors['observer'] = array_merge(
            $ticket_actors['User']['observer'] ?? [],
            $ticket_actors['Group']['observer'] ?? [],
            $ticket_actors['Supplier']['observer'] ?? []
        );
        $_actors['requester'] = array_merge(
            $ticket_actors['User']['requester'] ?? [],
            $ticket_actors['Group']['requester'] ?? [],
            $ticket_actors['Supplier']['requester'] ?? []
        );

        return $_actors;
    }

    public function showForm($ID, $options = [])
    {
        $tickets_id = $options["parent"]->getID();
        $groups_assign = new Group_Ticket();
        $groups = $groups_assign->find([
            'tickets_id' => $tickets_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        $assigned_groups = [];
        foreach ($groups as $grp) {
            $assigned_groups[$grp['groups_id']] = $grp['groups_id'];
        }
        $config = new PluginEscaladeConfig();
        $config->getFromDB(1);
        $PluginEscaladeGroup_Group = new PluginEscaladeGroup_Group();
        $groups_id_filtered = array_keys($PluginEscaladeGroup_Group->getGroups($tickets_id));
        $groups_id_filtered = empty($groups_id_filtered) ? [-1] : $groups_id_filtered;
        $condition = [
            'is_assign' => 1
        ];
        if ($config->fields['use_filter_assign_group']) {
            $condition['id'] = $groups_id_filtered;
        }
        TemplateRenderer::getInstance()->display('@escalade/escalade_form.html.twig', [
            'action'          => PLUGIN_ESCALADE_WEBDIR . '/front/ticket.form.php',
            'ticket'          => $options['parent'],
            'assign_me_as_observer' => $config->fields['assign_me_as_observer'],
            'assigned_groups' => $assigned_groups,
            'condition'     => $condition,
        ]);
    }
}

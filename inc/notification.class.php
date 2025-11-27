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

class PluginEscaladeNotification
{
    public const NTRGT_TICKET_REQUESTER_USER          = 357951;
    public const NTRGT_TICKET_REQUESTER_GROUP         = 357952;
    public const NTRGT_TICKET_REQUESTER_GROUP_MANAGER = 357953;
    public const NTRGT_TICKET_WATCH_USER              = 357954;
    public const NTRGT_TICKET_WATCH_GROUP             = 357955;
    public const NTRGT_TICKET_WATCH_GROUP_MANAGER     = 357956;
    public const NTRGT_TICKET_TECH_GROUP              = 357957;
    public const NTRGT_TICKET_TECH_USER               = 357958;
    public const NTRGT_TICKET_TECH_GROUP_MANAGER      = 357959;
    public const NTRGT_TASK_GROUP                     = 357960;

    public const NTRGT_TICKET_ESCALADE_GROUP          = 457951;
    public const NTRGT_TICKET_ESCALADE_GROUP_MANAGER  = 457952;
    public const NTRGT_TICKET_LAST_ESCALADE_GROUP     = 457953;
    public const NTRGT_TICKET_LAST_ESCALADE_GROUP_MANAGER = 457954;

    /**
     * Add additional targets (recipient) to Glpi Notification
     *
     * This function provides notification targets for escalation scenarios:
     *
     * For NotificationTargetPlanningRecall:
     * - Standard targets for ticket actors (requester, observer, technician)
     * - NTRGT_TICKET_ESCALADE_GROUP* targets: notify ALL groups in escalation history
     *
     * For NotificationTargetCommonITILObject (Ticket notifications):
     * - NTRGT_TICKET_LAST_ESCALADE_GROUP* targets: notify ONLY the most recently assigned group
     *   This prevents duplicate notifications during escalation by focusing on the current assignment
     *
     * @param NotificationTarget $target the current NotificationTarget
     */
    public static function addTargets(NotificationTarget $target)
    {
        // only for Planning recall
        if ($target instanceof NotificationTargetPlanningRecall) {
            // add new native targets
            $target->addTarget(
                self::NTRGT_TICKET_REQUESTER_USER,
                __('Requester user of the ticket', 'escalade'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_REQUESTER_GROUP,
                __('Requester group'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_REQUESTER_GROUP_MANAGER,
                __('Requester group manager'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_WATCH_USER,
                __('Watcher user'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_WATCH_GROUP,
                __('Watcher group'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_WATCH_GROUP_MANAGER,
                __('Watcher group manager'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_TECH_GROUP,
                __('Group in charge of the ticket'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_TECH_USER,
                __('Technician in charge of the ticket'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_TECH_GROUP_MANAGER,
                __('Manager of the group in charge of the ticket'),
            );
            $target->addTarget(
                self::NTRGT_TASK_GROUP,
                __('Group in charge of the task'),
            );

            // add plugins targets
            $target->addTarget(
                self::NTRGT_TICKET_ESCALADE_GROUP,
                __('Group escalated in the ticket', 'escalade'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_ESCALADE_GROUP_MANAGER,
                __('Manager of the group escalated in the ticket', 'escalade'),
            );

            // change label for this core target to avoid confusion with NTRGT_TICKET_REQUESTER_USER
            $target->addTarget(
                Notification::AUTHOR,
                __('Requester user of the task/reminder', 'escalade'),
            );
        } elseif ($target instanceof NotificationTargetCommonITILObject) {
            $target->addTarget(
                self::NTRGT_TICKET_LAST_ESCALADE_GROUP,
                __('Last group escalated in the ticket', 'escalade'),
            );
            $target->addTarget(
                self::NTRGT_TICKET_LAST_ESCALADE_GROUP_MANAGER,
                __('Manager of last group escalated in the ticket', 'escalade'),
            );
        }
    }

    /**
     * Computer targets with real users_id/email
     * @param NotificationTarget $target the current NotificationTarget.
     * The second type is designed to prevent duplicate notifications during escalation.
     */
    public static function getActionTargets(NotificationTarget $target)
    {
        if ($target instanceof NotificationTargetPlanningRecall) {
            $item = getItemForItemtype($target->obj->fields['itemtype']);
            if ($item === false) {
                return;
            }
            $item->getFromDB($target->obj->fields['items_id']);
            if ($item instanceof TicketTask) {
                $ticket = new Ticket();
                $ticket->getFromDB($item->getField('tickets_id'));

                switch ($target->data['items_id']) {
                    // group's users
                    case self::NTRGT_TICKET_REQUESTER_GROUP: // phpcs:ignore
                        $group_type = CommonITILActor::REQUESTER;
                        // no break
                    case self::NTRGT_TICKET_WATCH_GROUP: // phpcs:ignore
                        if (!isset($group_type)) {
                            $group_type = CommonITILActor::OBSERVER;
                        }
                        // no break
                    case self::NTRGT_TICKET_TECH_GROUP:
                        $manager = 0;

                        // manager of group's users
                        // no break
                    case self::NTRGT_TICKET_REQUESTER_GROUP_MANAGER: // phpcs:ignore
                        if (!isset($group_type)) {
                            $group_type = CommonITILActor::REQUESTER;
                        }
                        // no break
                    case self::NTRGT_TICKET_WATCH_GROUP_MANAGER: // phpcs:ignore
                        if (!isset($group_type)) {
                            $group_type = CommonITILActor::OBSERVER;
                        }
                        // no break
                    case self::NTRGT_TICKET_TECH_GROUP_MANAGER:
                        if (!isset($manager)) {
                            $manager = 1;
                        }
                        if (!isset($group_type)) {
                            $group_type = CommonITILActor::ASSIGN;
                        }

                        self::addGroupsOfTicket($target, $ticket->getID(), $manager, $group_type);
                        break;

                        // users
                    case self::NTRGT_TICKET_REQUESTER_USER: // phpcs:ignore
                        $user_type = CommonITILActor::REQUESTER;
                        // no break
                    case self::NTRGT_TICKET_WATCH_USER: // phpcs:ignore
                        if (!isset($user_type)) {
                            $user_type = CommonITILActor::OBSERVER;
                        }
                        // no break
                    case self::NTRGT_TICKET_TECH_USER:
                        if (!isset($user_type)) {
                            $user_type = CommonITILActor::ASSIGN;
                        }
                        self::addUsersOfTicket($target, $ticket->getID(), $user_type);
                        break;

                        // task group
                    case self::NTRGT_TASK_GROUP:
                        $target->addForGroup(0, $item->getField('groups_id_tech'));
                        break;

                        // escalation groups
                    case self::NTRGT_TICKET_ESCALADE_GROUP: // phpcs:ignore
                        $manager = 0;
                        // no break
                    case self::NTRGT_TICKET_ESCALADE_GROUP_MANAGER:
                        if (!isset($manager)) {
                            $manager = 1;
                        }
                        $history = new PluginEscaladeHistory();
                        foreach ($history->find(['tickets_id' => $ticket->getID()]) as $found_history) {
                            $target->addForGroup($manager, $found_history['groups_id']);
                        }
                        break;
                }
            }
        } elseif ($target instanceof NotificationTargetCommonITILObject) {
            $item = $target->obj;

            switch ($target->data['items_id']) {
                // Only last escalation group
                case self::NTRGT_TICKET_LAST_ESCALADE_GROUP:
                    $manager = 0;
                    // no break
                case self::NTRGT_TICKET_LAST_ESCALADE_GROUP_MANAGER:
                    if (!isset($manager)) {
                        $manager = 1;
                    }

                    $group_to_notify = null;

                    if (isset($_POST['escalate']) && isset($_POST['groups_id'])) {
                        // Direct escalation via plugin's ticket.form.php
                        $group_to_notify = (int) $_POST['groups_id'];
                    } elseif (isset($_POST['_actors']['assign'])) {
                        // Escalation via _actors update
                        $groups = [];
                        foreach ($_POST['_actors']['assign'] as $actor) {
                            if (isset($actor['itemtype']) && $actor['itemtype'] === 'Group') {
                                $groups[] = $actor['items_id'];
                            }
                        }
                        $group_ticket = new Group_Ticket();
                        $current_groups = $group_ticket->find(
                            [
                                'tickets_id' => $item->getID(),
                                'type' => CommonITILActor::ASSIGN,
                            ],
                            ['id DESC'],
                            1,
                        );
                        $group_to_notify = array_intersect($groups, array_column($current_groups, 'groups_id'));
                    } elseif (isset($_POST['_itil_assign']['groups_id'])) {
                        $group_to_notify = $_POST['_itil_assign']['groups_id'];
                    }

                    if (!$group_to_notify) {
                        $group_ticket = new Group_Ticket();
                        $current_groups = $group_ticket->find(
                            [
                                'tickets_id' => $item->getID(),
                                'type' => CommonITILActor::ASSIGN,
                            ],
                            ['id DESC'],
                            1,
                        );

                        if (!empty($current_groups)) {
                            $current_group = reset($current_groups);
                            $group_to_notify = $current_group['groups_id'];
                        }
                    }

                    if (!$group_to_notify) {
                        $last_escalation = PluginEscaladeHistory::getMostRecentEscalationForTicket($item->getID());

                        if ($last_escalation !== false) {
                            $group_ticket = new Group_Ticket();
                            $verification = $group_ticket->find([
                                'tickets_id' => $item->getID(),
                                'groups_id' => $last_escalation['groups_id'],
                                'type' => CommonITILActor::ASSIGN,
                            ]);

                            if (!empty($verification)) {
                                $group_to_notify = $last_escalation['groups_id'];
                            }
                        }
                    }

                    if ($group_to_notify) {
                        $target->addForGroup($manager, $group_to_notify);
                    }
                    break;
            }
        }
    }

    /**
     * Add all group's users for a ticket and a type of actors
     *
     * @param NotificationTarget $target     The current notification target (the recipient)
     * @param integer            $tickets_id The ticket's identifier
     * @param integer            $manager    0 all users, 1 only supervisors, 2 all users without supervisors
     * @param integer            $group_type @see CommonITILActor
     *
     * @return void
     */
    public static function addGroupsOfTicket(
        NotificationTarget $target,
        $tickets_id = 0,
        $manager = 0,
        $group_type = CommonITILActor::REQUESTER,
    ) {
        $group_ticket = new Group_Ticket();
        foreach (
            $group_ticket->find(['tickets_id' => $tickets_id,
                'type' => $group_type,
            ]) as $current
        ) {
            $target->addForGroup($manager, $current['groups_id']);
        }
    }

    /**
     * Add all users for a ticket and a type of actors
     * @param NotificationTarget $target     The current notification target (the recipient)
     * @param integer            $tickets_id The ticket's identifier
     * @param integer            $user_type  @see CommonITILActor
     *
     * @return void
     */
    public static function addUsersOfTicket(
        NotificationTarget $target,
        $tickets_id = 0,
        $user_type = CommonITILActor::REQUESTER,
    ) {
        $ticket_user = new Ticket_User();
        $user        = new User();
        foreach (
            $ticket_user->find(['type' => $user_type,
                'tickets_id' => $tickets_id,
            ]) as $current
        ) {
            if ($user->getFromDB($current['users_id'])) {
                $target->addToRecipientsList(['language' => $user->getField('language'),
                    'users_id' => $user->getField('id'),
                ]);
            }
        }
    }

    public static function getEvents(NotificationTarget $target)
    {
        if ($target instanceof NotificationTargetTicket) {
            $target->events['update_solvedate'] = __('Solve date modification', 'escalade');
        }
    }
}

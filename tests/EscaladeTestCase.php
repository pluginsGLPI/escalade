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

namespace GlpiPlugin\Escalade\Tests;

use Glpi\Tests\DbTestCase;
use QueuedNotification;
use Session;

abstract class EscaladeTestCase extends DbTestCase
{
    public function createGroup(string $group_name = 'TestGroup'): \Group
    {
        return $this->createItem(\Group::class, ['name' => $group_name]);
    }

    /**
     * Create a group and assign users to it
     *
     * @param \User|\User[] $users A single user or array of users to assign to the group
     * @param string $group_name Name of the group to create
     * @return \Group The created group
     */
    public function createGroupAndAssignUsers($users, string $group_name = 'TestGroup'): \Group
    {
        $group = $this->createGroup($group_name);

        // If it's a single user, convert to array for consistent processing
        if (!is_array($users)) {
            $users = [$users];
        }

        foreach ($users as $user) {
            $this->createItem(
                \Group_User::class,
                [
                    'users_id' => $user->getID(),
                    'groups_id' => $group->getID(),
                ],
            );
        }

        return $group;
    }

    public function initConfig(array $conf = [])
    {
        $this->login();

        // Initialize session structure FIRST to avoid warnings
        if (!isset($_SESSION['glpi_plugins'])) {
            $_SESSION['glpi_plugins'] = [];
        }
        if (!isset($_SESSION['glpi_plugins']['escalade'])) {
            $_SESSION['glpi_plugins']['escalade'] = [];
        }

        // Load default config into session to avoid warnings during ticket operations
        $_SESSION['glpi_plugins']['escalade']['config'] = [
            'use_assign_user_group' => 0,
            'use_assign_user_group_creation' => 0,
            'use_assign_user_group_modification' => 0,
            'remove_tech' => 0,
            'remove_group' => 0,
            'remove_requester' => 0,
            'show_history' => 0,
            'ticket_last_status' => 0,
            'solve_return_group' => 0,
            'task_history' => 0,
            'cloneandlink_ticket' => 0,
            'close_linkedtickets' => 0,
            'reassign_group_from_cat' => 0,
        ];

        // Update escalade config in database if provided
        if (!empty($conf)) {
            $this->updateItem(\PluginEscaladeConfig::class, 1, $conf);

            // Load updated config into session
            $config = new \PluginEscaladeConfig();
            $config->getFromDB(1);
            $_SESSION['glpi_plugins']['escalade']['config'] = array_merge(
                $_SESSION['glpi_plugins']['escalade']['config'],
                $config->fields,
            );
        }
    }

    /**
     * Get the methods for simulating different ways of escalating a ticket.
     *
     * @param array $methods Contains the names of the different methods that must be used to simulate an escalation.
     * eg. ['escalateWithTimelineButton', 'escalateWithHistoryButton'] to simulate the escalation with the timeline and history buttons.
     *
     * @return array
     */
    public static function escalateTicketMethods(array $methods = []): array
    {
        $escalate_methods = [
            [
                'method' => 'escalateWithTimelineButton',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithHistoryButton',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithSolvedTicket',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithRejectSolutionTicket',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithAssignMySelfButton',
                'itemtype' => \User::class,
            ],
        ];

        return array_filter($escalate_methods, function ($escalate_method) use ($methods) {
            return in_array($escalate_method['method'], $methods);
        });
    }

    /**
     * Simulate the escalation of a ticket with the timeline button.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     * @param array $options
     */
    public function escalateWithTimelineButton(\Ticket $ticket, \Group $group, array $options = []): void
    {
        $options['ticket_details'] = array_merge(
            $options['ticket_details'] ?? [],
            [
                'id' => $ticket->getID(),
            ],
        );
        $_POST['comment'] = $options['comment'] ?? 'Default comment';
        \PluginEscaladeTicket::timelineClimbAction($group->getID(), $ticket->getID(), $options);
        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
            'type'       => \CommonITILActor::ASSIGN,
        ]);
        $this->assertTrue($is_escalate);
        if (isset($options['is_observer_checkbox']) && $options['is_observer_checkbox']) {
            $ticket_user = new \Ticket_User();
            $is_observer = $ticket_user->getFromDBByCrit([
                'type'       => \CommonITILActor::OBSERVER,
                'tickets_id' => $ticket->getID(),
                'users_id'   => Session::getLoginUserID(),
            ]);
            $this->assertTrue($is_observer);
        }
    }

    /**
     * Simulate the escalation of a ticket with the history button.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     */
    public function escalateWithHistoryButton(\Ticket $ticket, \Group $group): void
    {
        \PluginEscaladeTicket::climb_group($ticket->getID(), $group->getID(), true);
        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
            'type'       => \CommonITILActor::ASSIGN,
        ]);
        $this->assertTrue($is_escalate);
    }

    /**
     * Simulate the escalation of a ticket with a solved ticket.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     * @param array $solution_options
     */
    public function escalateWithSolvedTicket(\Ticket $ticket, \Group $group, array $solution_options = []): void
    {
        $config = new \PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $config->update([
            'id' => 1,
            'solve_return_group' => 1,
        ] + $conf);

        $solution = new \ITILSolution();
        $solution_id = $solution->add(array_merge([
            'content' => 'Test Solution',
            'itemtype' => $ticket->getType(),
            'items_id' => $ticket->getID(),
            'users_id' => Session::getLoginUserID(),
        ], $solution_options));
        $this->assertGreaterThan(0, $solution_id);

        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
            'type'       => \CommonITILActor::ASSIGN,
        ]);
        $this->assertTrue($is_escalate);
    }

    /**
     * Simulate the escalation of a ticket with a reject solution ticket.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     * @param array $followup_options
     */
    public function escalateWithRejectSolutionTicket(\Ticket $ticket, \Group $group, array $followup_options = []): void
    {
        $_POST['add_reopen'] = 1;

        $followup = new \ITILFollowup();
        $followup_id = $followup->add(array_merge([
            'itemtype'   => 'Ticket',
            'items_id'   => $ticket->getID(),
            'add_reopen'   => '1',
            'content'      => 'reopen followup',
        ], $followup_options));
        $this->assertGreaterThan(0, $followup_id);

        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
            'type'       => \CommonITILActor::ASSIGN,
        ]);
        $this->assertTrue($is_escalate);
    }

    /**
     * Simulate the escalation of a ticket with the assign myself button.
     *
     * @param \Ticket $ticket
     * @param \User $user
     */
    public function escalateWithAssignMySelfButton(\Ticket $ticket, \User $user): void
    {
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user->getID(),
                            'itemtype' => 'User',
                            'use_notification' => 1,
                        ],
                    ],
                ],
            ],
        );

        $ticket_user = new \Ticket_User();
        $is_escalate = $ticket_user->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'users_id'  => $user->getID(),
        ]);
        $this->assertTrue($is_escalate);
    }

    /**
     * Get the email address for a user
     *
     * @param \User $user
     * @return string
     */
    protected function getItemEmail(\User $user): string
    {
        $useremail = new \UserEmail();
        $useremail->getFromDBByCrit([
            'users_id' => $user->getID(),
            'is_default' => 1,
        ]);
        return $useremail->fields['email'] ?? '';
    }

    /**
     * Cleans the notification queue
     */
    public function cleanQueuedNotifications()
    {
        global $DB;
        $DB->delete(QueuedNotification::getTable(), [new \Glpi\DBAL\QueryExpression('true')]);

        $queued = new QueuedNotification();
        $notifications = $queued->find();
        $this->assertEmpty($notifications, "The notification queue is not empty after cleaning");
    }



    /**
     * Set notification targets for a notification
     */
    protected function setNotificationTargets($notification_id, array $targets)
    {
        global $DB;

        //Clear targets
        $DB->delete(\NotificationTarget::getTable(), ['notifications_id' => $notification_id]);

        //Set new targets
        foreach ($targets as $target) {
            $this->createItem(\NotificationTarget::class, [
                'notifications_id' => $notification_id,
                'type' => \Notification::USER_TYPE,
                'items_id' => $target,
            ]);
        }

        $notification_target = new \NotificationTarget();
        $this->assertEquals(count($targets), count($notification_target->find(['notifications_id' => $notification_id])), "The number of notification targets doesn't match after addition");
    }
}

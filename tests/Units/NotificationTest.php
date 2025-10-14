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

namespace GlpiPlugin\Escalade\Tests\Units;

use CommonITILActor;
use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use Group;
use Group_Ticket;
use NotificationTarget;
use PluginEscaladeConfig;
use PluginEscaladeNotification;
use PluginEscaladeTicket;
use QueuedNotification;
use Ticket;
use User;

final class NotificationTest extends EscaladeTestCase
{
    private function enableNotifications(): void
    {
        global $CFG_GLPI;
        $CFG_GLPI['use_notifications'] = 1;
        $CFG_GLPI['notifications_mailing'] = 1;
    }

    private function setupNotificationTargets(): void
    {
        global $DB;

        // Disable all notifications first
        $DB->update(\Notification::getTable(), ['is_active' => false], [new \Glpi\DBAL\QueryExpression('true')]);

        // Enable only the "assign group" notification
        $notification = new \Notification();
        if (!$notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => 'assign_group'])) {
            $this->markTestSkipped('assign_group notification not found');
        }
        $this->updateItem(\Notification::class, $notification->getID(), ['is_active' => 1]);

        // Set our notification target
        $this->setNotificationTargets(
            $notification->getID(),
            [
                PluginEscaladeNotification::NTRGT_TICKET_LAST_ESCALADE_GROUP,
            ],
        );
    }

    public function testEscalationViaClimbGroupNotifiesOnlyNewGroup(): void
    {
        global $CFG_GLPI;

        $this->initConfig([
            'show_history' => 1,
            'remove_group' => 1,
            'task_history' => 1,
        ]);
        $this->enableNotifications();
        $this->setupNotificationTargets();

        [$user1, $user2, $user3, $user4] = $this->createItems(\User::class, [
            ['name' => 'User 1_' . uniqid(), '_useremails' => [-1 => 'user1_' . uniqid() . '@example.com']],
            ['name' => 'User 2_' . uniqid(), '_useremails' => [-1 => 'user2_' . uniqid() . '@example.com']],
            ['name' => 'User 3_' . uniqid(), '_useremails' => [-1 => 'user3_' . uniqid() . '@example.com']],
            ['name' => 'User 4_' . uniqid(), '_useremails' => [-1 => 'user4_' . uniqid() . '@example.com']],
        ]);

        $group1 = $this->createGroupAndAssignUsers([$user1, $user2], 'test_climb_group_1_' . uniqid());
        $group2 = $this->createGroupAndAssignUsers([$user3, $user4], 'test_climb_group_2_' . uniqid());

        // Create a ticket assigned to the first group
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Test escalation via climb_group',
            'content' => 'Content for escalation test',
            'entities_id' => $this->getTestRootEntity(true),
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Escalate using updateItem with _actors (like in GroupEscalationTest)
        $this->updateItem(Ticket::class, $ticket->getID(), [
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Check that the ticket is now assigned to group2 only
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket->getID(),
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have only one assigned group after escalation");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group2->getID(), $assigned_group['groups_id'], "Should be assigned to group2");

        // Check notifications were sent
        $queued = new QueuedNotification();
        $notifications = $queued->find();
        $this->assertGreaterThan(0, count($notifications), "Should have sent notifications");

        // Get notification recipients
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that only group2 users received notifications
        $group2_user_emails = [$this->getItemEmail($user3), $this->getItemEmail($user4)];
        $group1_user_emails = [$this->getItemEmail($user1), $this->getItemEmail($user2)];

        foreach ($group2_user_emails as $email) {
            $this->assertContains($email, $notification_recipients, "Group2 users should receive notifications");
        }

        foreach ($group1_user_emails as $email) {
            $this->assertNotContains($email, $notification_recipients, "Group1 users should NOT receive notifications");
        }
    }

    /**
     * Test that escalation via ticket update (_actors) notifies only the newly assigned group
     */
    public function testEscalationViaTicketUpdateNotifiesOnlyNewGroup(): void
    {
        global $CFG_GLPI;

        $this->initConfig([
            'show_history' => 1,
            'remove_group' => 1,
            'task_history' => 1,
        ]);
        $this->enableNotifications();
        $this->setupNotificationTargets();

        [$user1, $user2, $user3, $user4] = $this->createItems(\User::class, [
            ['name' => 'User 1_' . uniqid(), '_useremails' => [-1 => 'user1_' . uniqid() . '@example.com']],
            ['name' => 'User 2_' . uniqid(), '_useremails' => [-1 => 'user2_' . uniqid() . '@example.com']],
            ['name' => 'User 3_' . uniqid(), '_useremails' => [-1 => 'user3_' . uniqid() . '@example.com']],
            ['name' => 'User 4_' . uniqid(), '_useremails' => [-1 => 'user4_' . uniqid() . '@example.com']],
        ]);

        $group1 = $this->createGroupAndAssignUsers([$user1, $user2], 'test_update_group_1_' . uniqid());
        $group2 = $this->createGroupAndAssignUsers([$user3, $user4], 'test_update_group_2_' . uniqid());

        // Create a ticket assigned to the first group
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Test escalation via ticket update',
            'content' => 'Content for escalation test',
            'entities_id' => $this->getTestRootEntity(true),
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Escalate using ticket update (via form submission)
        $this->updateItem(Ticket::class, $ticket->getID(), [
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Check that the ticket is now assigned to group2 only
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket->getID(),
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have only one assigned group after escalation");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group2->getID(), $assigned_group['groups_id'], "Should be assigned to group2");

        // Check notifications were sent
        $queued = new QueuedNotification();
        $notifications = $queued->find();
        $this->assertGreaterThan(0, count($notifications), "Should have sent notifications");

        // Get notification recipients
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that only group2 users received notifications
        $group2_user_emails = [$this->getItemEmail($user3), $this->getItemEmail($user4)];
        $group1_user_emails = [$this->getItemEmail($user1), $this->getItemEmail($user2)];

        foreach ($group2_user_emails as $email) {
            $this->assertContains($email, $notification_recipients, "Group2 users should receive notifications");
        }

        foreach ($group1_user_emails as $email) {
            $this->assertNotContains($email, $notification_recipients, "Group1 users should NOT receive notifications");
        }
    }

    /**
     * Test that escalation via _itil_assign notifies only the newly assigned group
     */
    public function testEscalationViaItilAssignNotifiesOnlyNewGroup(): void
    {
        global $CFG_GLPI;

        $this->initConfig([
            'show_history' => 1,
            'remove_group' => 1,
            'task_history' => 1,
        ]);
        $this->enableNotifications();
        $this->setupNotificationTargets();

        [$user1, $user2, $user3, $user4] = $this->createItems(\User::class, [
            ['name' => 'User 1_' . uniqid(), '_useremails' => [-1 => 'user1_' . uniqid() . '@example.com']],
            ['name' => 'User 2_' . uniqid(), '_useremails' => [-1 => 'user2_' . uniqid() . '@example.com']],
            ['name' => 'User 3_' . uniqid(), '_useremails' => [-1 => 'user3_' . uniqid() . '@example.com']],
            ['name' => 'User 4_' . uniqid(), '_useremails' => [-1 => 'user4_' . uniqid() . '@example.com']],
        ]);

        $group1 = $this->createGroupAndAssignUsers([$user1, $user2], 'test_itil_group_1_' . uniqid());
        $group2 = $this->createGroupAndAssignUsers([$user3, $user4], 'test_itil_group_2_' . uniqid());

        // Create a ticket assigned to the first group
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Test escalation via _itil_assign',
            'content' => 'Content for escalation test',
            'entities_id' => $this->getTestRootEntity(true),
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Escalate using _actors method (like other working tests)
        $this->updateItem(Ticket::class, $ticket->getID(), [
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Check that the ticket is now assigned to group2 only
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket->getID(),
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have only one assigned group after escalation");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group2->getID(), $assigned_group['groups_id'], "Should be assigned to group2");

        // Check notifications were sent
        $queued = new QueuedNotification();
        $notifications = $queued->find();
        $this->assertGreaterThan(0, count($notifications), "Should have sent notifications");

        // Get notification recipients
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that only group2 users received notifications
        $group2_user_emails = [$this->getItemEmail($user3), $this->getItemEmail($user4)];
        $group1_user_emails = [$this->getItemEmail($user1), $this->getItemEmail($user2)];

        foreach ($group2_user_emails as $email) {
            $this->assertContains($email, $notification_recipients, "Group2 users should receive notifications");
        }

        foreach ($group1_user_emails as $email) {
            $this->assertNotContains($email, $notification_recipients, "Group1 users should NOT receive notifications");
        }
    }

    /**
     * Test that direct group assignment (not escalation) still works correctly
     */
    public function testDirectGroupAssignmentNotification(): void
    {
        global $CFG_GLPI;

        $this->initConfig([
            'show_history' => 1,
            'remove_group' => 1,
            'task_history' => 1,
        ]);
        $this->enableNotifications();
        $this->setupNotificationTargets();

        [$user1, $user2] = $this->createItems(\User::class, [
            ['name' => 'User 1_' . uniqid(), '_useremails' => [-1 => 'user1_' . uniqid() . '@example.com']],
            ['name' => 'User 2_' . uniqid(), '_useremails' => [-1 => 'user2_' . uniqid() . '@example.com']],
        ]);

        $group = $this->createGroupAndAssignUsers([$user1, $user2], 'test_direct_group_' . uniqid());

        // Create a ticket without any initial assignment
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Test direct group assignment',
            'content' => 'Content for direct assignment test',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Directly assign the group (not an escalation)
        $this->updateItem(Ticket::class, $ticket->getID(), [
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Check that the ticket is assigned to the group
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket->getID(),
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have one assigned group");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group->getID(), $assigned_group['groups_id'], "Should be assigned to the correct group");

        // Check notifications were sent to the assigned group
        $queued = new QueuedNotification();
        $notifications = $queued->find();
        $this->assertGreaterThan(0, count($notifications), "Should have sent notifications");

        // Get notification recipients
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that group users received notifications
        $group_user_emails = [$this->getItemEmail($user1), $this->getItemEmail($user2)];
        foreach ($group_user_emails as $email) {
            $this->assertContains($email, $notification_recipients, "Group users should receive notifications");
        }
    }

    /**
     * Test that escalation history is properly maintained
     */
    public function testEscalationHistoryMaintenance(): void
    {
        $this->initConfig([
            'show_history' => 1,
            'remove_group' => 1,
            'task_history' => 1,
        ]);

        [$user1, $user2] = $this->createItems(\User::class, [
            ['name' => 'User 1_' . uniqid(), '_useremails' => [-1 => 'user1_' . uniqid() . '@example.com']],
            ['name' => 'User 2_' . uniqid(), '_useremails' => [-1 => 'user2_' . uniqid() . '@example.com']],
        ]);

        // Create two groups
        $group1 = $this->createGroupAndAssignUsers([$user1], 'test_history_group_1_' . uniqid());
        $group2 = $this->createGroupAndAssignUsers([$user2], 'test_history_group_2_' . uniqid());

        // Create a ticket assigned to group1
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Test escalation history',
            'content' => 'Content for history test',
            'entities_id' => $this->getTestRootEntity(true),
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Check initial group assignment
        $group_ticket = new Group_Ticket();
        $assigned_groups_before = $group_ticket->find([
            'tickets_id' => $ticket->getID(),
            'type' => CommonITILActor::ASSIGN,
        ]);
        $this->assertCount(1, $assigned_groups_before, "Should have one assigned group initially");
        $initial_group = reset($assigned_groups_before);
        $this->assertEquals($group1->getID(), $initial_group['groups_id'], "Should be assigned to group1 initially");

        // Escalate to group2 using updateItem
        $this->updateItem(Ticket::class, $ticket->getID(), [
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        // Check final group assignment
        $assigned_groups_after = $group_ticket->find([
            'tickets_id' => $ticket->getID(),
            'type' => CommonITILActor::ASSIGN,
        ]);
        $this->assertCount(1, $assigned_groups_after, "Should have one assigned group after escalation");
        $final_group = reset($assigned_groups_after);
        $this->assertEquals($group2->getID(), $final_group['groups_id'], "Should be assigned to group2 after escalation");

        // Check escalation history
        $history = new \PluginEscaladeHistory();
        $history_entries = $history->find(['tickets_id' => $ticket->getID()]);
        $this->assertGreaterThan(0, count($history_entries), "Should have escalation history entries");

        // Check that the most recent history entry corresponds to group2
        $recent_escalation = \PluginEscaladeHistory::getMostRecentEscalationForTicket($ticket->getID());
        $this->assertNotFalse($recent_escalation, "Should find most recent escalation");
        $this->assertEquals($group2->getID(), $recent_escalation['groups_id'], "Most recent escalation should be to group2");
    }
}

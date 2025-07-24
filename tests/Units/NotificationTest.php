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
    /**
     * Clean queued notifications to avoid interference between tests
     */
    private function cleanQueuedNotifications(): void
    {
        $queued = new QueuedNotification();
        $queued->deleteByCriteria(['1' => '1']);

        if (isset($_SESSION['plugin_escalade']['current_group_assignment'])) {
            unset($_SESSION['plugin_escalade']['current_group_assignment']);
        }
    }

    private function createGroupWithUsers(string $group_name, int $user_count = 2): array
    {
        $group = new Group();
        $group_id = $group->add([
            'name' => $group_name,
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $this->assertGreaterThan(0, $group_id);

        $users = [];
        for ($i = 1; $i <= $user_count; $i++) {
            $user = new User();
            $user_id = $user->add([
                'name' => $group_name . '_user_' . $i,
                '_profiles_id' => 4,
                'firstname' => 'Test',
                'realname' => 'User ' . $i,
            ]);
            $this->assertGreaterThan(0, $user_id);

            $email = strtolower($group_name) . '_user_' . $i . '@example.com';
            $userEmail = new \UserEmail();
            $userEmail->add([
                'users_id' => $user_id,
                'email' => $email,
                'is_default' => 1,
            ]);

            $groupUser = new \Group_User();
            $groupUser->add([
                'groups_id' => $group_id,
                'users_id' => $user_id,
            ]);

            $users[] = [
                'id' => $user_id,
                'name' => $group_name . '_user_' . $i,
                'email' => $email,
            ];
        }

        return [
            'id' => $group_id,
            'name' => $group_name,
            'users' => $users,
        ];
    }

    private function setupEscaladeConfig(): void
    {
        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);

        $this->assertTrue($config->update([
            'show_history' => 1,
            'remove_group' => 1,
            'task_history' => 1,
            'cloneandlink' => 0,
            'close_linkedtickets' => 0,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();
    }

    private function enableNotifications(): void
    {
        global $CFG_GLPI;
        $CFG_GLPI['use_notifications'] = 1;
        $CFG_GLPI['notifications_mailing'] = 1;
    }

    private function setupNotificationTargets(): void
    {
        $notification = new \Notification();
        $template = new \NotificationTemplate();

        $notif_found = $notification->find([
            'itemtype' => 'Ticket',
            'event' => 'assign_group',
        ]);

        if (empty($notif_found)) {
            $template_id = $template->add([
                'name' => 'Test Escalade Group Assignment',
                'itemtype' => 'Ticket',
            ]);
            $this->assertGreaterThan(0, $template_id);

            $notif_id = $notification->add([
                'name' => 'Test Group Assignment Notification',
                'entities_id' => 0,
                'itemtype' => 'Ticket',
                'event' => 'assign_group',
                'is_active' => 1,
                'notificationtemplates_id' => $template_id,
            ]);
            $this->assertGreaterThan(0, $notif_id);
        } else {
            $notif_data = reset($notif_found);
            $notif_id = $notif_data['id'];
        }

        $target = new \NotificationTarget();
        $existing_targets = $target->find(['notifications_id' => $notif_id]);
        foreach ($existing_targets as $existing_target) {
            $target->delete(['id' => $existing_target['id']]);
        }

        $target_id = $target->add([
            'notifications_id' => $notif_id,
            'type' => \Notification::USER_TYPE,
            'items_id' => PluginEscaladeNotification::NTRGT_TICKET_LAST_ESCALADE_GROUP,
        ]);
        $this->assertGreaterThan(0, $target_id);
    }

    public function testEscalationViaClimbGroupNotifiesOnlyNewGroup(): void
    {
        global $CFG_GLPI;

        $this->login();
        $this->setupEscaladeConfig();
        $this->enableNotifications();
        $this->setupNotificationTargets();

        $group1 = $this->createGroupWithUsers('test_climb_group_1', 2);
        $group2 = $this->createGroupWithUsers('test_climb_group_2', 2);

        $ticket = new Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test escalation via climb_group',
            'content' => 'Content for escalation test',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1['id'],
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Escalate using climb_group method (from history widget)
        PluginEscaladeTicket::climb_group($ticket_id, $group2['id'], true);

        // Check that the ticket is now assigned to group2 only
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have only one assigned group after escalation");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group2['id'], $assigned_group['groups_id'], "Should be assigned to group2");

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
        $group2_user_emails = array_column($group2['users'], 'email');
        $group1_user_emails = array_column($group1['users'], 'email');

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

        $this->login();
        $this->setupEscaladeConfig();
        $this->enableNotifications();
        $this->setupNotificationTargets();

        // Create two groups with users
        $group1 = $this->createGroupWithUsers('test_update_group_1', 2);
        $group2 = $this->createGroupWithUsers('test_update_group_2', 2);

        // Create a ticket assigned to the first group
        $ticket = new Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test escalation via ticket update',
            'content' => 'Content for escalation test',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1['id'],
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Escalate using ticket update (via form submission)
        // We add the new group while keeping the old one in the payload
        // The escalade plugin should handle removing the old group
        $this->assertTrue($ticket->update([
            'id' => $ticket_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1['id'],
                        'itemtype' => 'Group',
                    ],
                    [
                        'items_id' => $group2['id'],
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]));

        // Check that the ticket is now assigned to group2 only
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have only one assigned group after escalation");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group2['id'], $assigned_group['groups_id'], "Should be assigned to group2");

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
        $group2_user_emails = array_column($group2['users'], 'email');
        $group1_user_emails = array_column($group1['users'], 'email');

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

        $this->login();
        $this->setupEscaladeConfig();
        $this->enableNotifications();
        $this->setupNotificationTargets();

        // Create two groups with users
        $group1 = $this->createGroupWithUsers('test_itil_group_1', 2);
        $group2 = $this->createGroupWithUsers('test_itil_group_2', 2);

        // Create a ticket assigned to the first group
        $ticket = new Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test escalation via _itil_assign',
            'content' => 'Content for escalation test',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1['id'],
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Escalate using _itil_assign method
        $this->assertTrue($ticket->update([
            'id' => $ticket_id,
            '_itil_assign' => [
                '_type' => 'group',
                'groups_id' => $group2['id'],
                'use_notification' => 1,
            ],
        ]));

        // Check that the ticket is now assigned to group2 only
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have only one assigned group after escalation");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group2['id'], $assigned_group['groups_id'], "Should be assigned to group2");

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
        $group2_user_emails = array_column($group2['users'], 'email');
        $group1_user_emails = array_column($group1['users'], 'email');

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

        $this->login();
        $this->setupEscaladeConfig();
        $this->enableNotifications();
        $this->setupNotificationTargets();

        // Create a group with users
        $group = $this->createGroupWithUsers('test_direct_group', 2);

        // Create a ticket without any initial assignment
        $ticket = new Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test direct group assignment',
            'content' => 'Content for direct assignment test',
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Clear notification queue
        $this->cleanQueuedNotifications();

        // Directly assign the group (not an escalation)
        $this->assertTrue($ticket->update([
            'id' => $ticket_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group['id'],
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]));

        // Check that the ticket is assigned to the group
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        $this->assertCount(1, $assigned_groups, "Should have one assigned group");
        $assigned_group = reset($assigned_groups);
        $this->assertEquals($group['id'], $assigned_group['groups_id'], "Should be assigned to the correct group");

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
        $group_user_emails = array_column($group['users'], 'email');
        foreach ($group_user_emails as $email) {
            $this->assertContains($email, $notification_recipients, "Group users should receive notifications");
        }
    }

    /**
     * Test that escalation history is properly maintained
     */
    public function testEscalationHistoryMaintenance(): void
    {
        $this->login();
        $this->setupEscaladeConfig();

        // Create two groups
        $group1 = $this->createGroupWithUsers('test_history_group_1', 1);
        $group2 = $this->createGroupWithUsers('test_history_group_2', 1);

        // Create a ticket assigned to group1
        $ticket = new Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test escalation history',
            'content' => 'Content for history test',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1['id'],
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Check initial group assignment
        $group_ticket = new Group_Ticket();
        $assigned_groups_before = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);
        $this->assertCount(1, $assigned_groups_before, "Should have one assigned group initially");
        $initial_group = reset($assigned_groups_before);
        $this->assertEquals($group1['id'], $initial_group['groups_id'], "Should be assigned to group1 initially");

        // Escalate to group2 using climb_group
        PluginEscaladeTicket::climb_group($ticket_id, $group2['id'], true);

        // Check final group assignment
        $assigned_groups_after = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);
        $this->assertCount(1, $assigned_groups_after, "Should have one assigned group after escalation");
        $final_group = reset($assigned_groups_after);
        $this->assertEquals($group2['id'], $final_group['groups_id'], "Should be assigned to group2 after escalation");

        // Check escalation history
        $history = new \PluginEscaladeHistory();
        $history_entries = $history->find(['tickets_id' => $ticket_id]);
        $this->assertGreaterThan(0, count($history_entries), "Should have escalation history entries");

        // Check that the most recent history entry corresponds to group2
        $recent_escalation = \PluginEscaladeHistory::getMostRecentEscalationForTicket($ticket_id);
        $this->assertNotFalse($recent_escalation, "Should find most recent escalation");
        $this->assertEquals($group2['id'], $recent_escalation['groups_id'], "Most recent escalation should be to group2");
    }
}

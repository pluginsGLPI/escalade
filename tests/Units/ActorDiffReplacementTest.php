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
use Glpi\DBAL\QueryExpression;
use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use Group_Ticket;
use Notification;
use NotificationTarget;
use QueuedNotification;
use Ticket;
use User;

// Kept isolated from GroupEscalationTest.php, which has a pre-existing state-leak flakiness.
final class ActorDiffReplacementTest extends EscaladeTestCase
{
    public function testSingleShotGroupReplacementPreservesStatusAndNotifiesOnlyNewGroup(): void
    {
        global $CFG_GLPI, $DB;

        $this->initConfig([
            'remove_group' => 1,
            'show_history' => 1,
        ]);

        $CFG_GLPI['use_notifications'] = true;
        $CFG_GLPI['notifications_mailing'] = true;

        $DB->update(Notification::getTable(), ['is_active' => false], [new QueryExpression('true')]);

        $notification = new Notification();
        if (!$notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => 'assign_group'])) {
            $this->markTestSkipped('assign_group notification not found');
        }

        $this->assertTrue($notification->update(['id' => $notification->getID(), 'is_active' => 1]));

        $DB->delete(NotificationTarget::getTable(), ['notifications_id' => $notification->getID()]);
        $this->createItem(NotificationTarget::class, [
            'notifications_id' => $notification->getID(),
            'items_id'         => Notification::ASSIGN_GROUP,
            'type'             => Notification::USER_TYPE,
        ]);

        [$user1, $user2] = $this->createItems(User::class, [
            ['name' => 'diff_user1_' . uniqid(), '_useremails' => [-1 => 'diff1_' . uniqid() . '@example.com']],
            ['name' => 'diff_user2_' . uniqid(), '_useremails' => [-1 => 'diff2_' . uniqid() . '@example.com']],
        ]);

        $group1 = $this->createGroupAndAssignUsers($user1, 'diff_group_1_' . uniqid());
        $group2 = $this->createGroupAndAssignUsers($user2, 'diff_group_2_' . uniqid());

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Diff replacement test',
            'content'     => 'content',
            'entities_id' => $this->getTestRootEntity(true),
            '_actors'     => [
                'assign' => [
                    ['items_id' => $group1->getID(), 'itemtype' => 'Group'],
                ],
            ],
        ]);
        $this->assertEquals(Ticket::ASSIGNED, $ticket->fields['status']);

        $this->cleanQueuedNotifications();

        // Diff-only: only group2 is sent, core must delete group1 and add group2 in one call.
        $this->updateItem(
            Ticket::class,
            $ticket->getID(),
            [
                '_actors' => [
                    'assign' => [
                        ['items_id' => $group2->getID(), 'itemtype' => 'Group'],
                    ],
                ],
            ],
        );

        $group_ticket = new Group_Ticket();
        $this->assertFalse($group_ticket->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group1->getID(),
            'type'       => CommonITILActor::ASSIGN,
        ]), 'The old group must have been removed');
        $this->assertTrue($group_ticket->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group2->getID(),
            'type'       => CommonITILActor::ASSIGN,
        ]), 'The new group must have been added');

        $ticket->getFromDB($ticket->getID());
        $this->assertEquals(
            Ticket::ASSIGNED,
            $ticket->fields['status'],
            'Status must be preserved despite the old group being removed in the same call as the new group addition',
        );

        $queued = new QueuedNotification();
        $recipients = array_column($queued->find(), 'recipient');

        $this->assertContains($this->getItemEmail($user2), $recipients, "The new group's users must be notified");
        $this->assertNotContains($this->getItemEmail($user1), $recipients, "The old group's users must not be notified");
    }
}

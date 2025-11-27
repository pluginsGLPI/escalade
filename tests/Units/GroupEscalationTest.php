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
use PluginEscaladeHistory;
use PluginEscaladeNotification;
use QueuedNotification;
use Ticket;
use Ticket_User;
use User;

final class GroupEscalationTest extends EscaladeTestCase
{
    public function testTechGroupAttributionUpdateTicket()
    {
        $this->initConfig([
            'use_assign_user_group'              => 1,
            'use_assign_user_group_creation'     => 0,
            'use_assign_user_group_modification' => 1,
            'remove_tech'                        => 1,
            'remove_group'                       => 1,
        ]);

        // Create two groups with users
        $user1 = getItemByTypeName(User::class, 'glpi');
        $this->createGroupAndAssignUsers($user1);

        $user2 = getItemByTypeName(User::class, 'tech');
        $group2 = $this->createGroupAndAssignUsers($user2);

        // Create ticket without technician
        $ticket = $this->createItem(
            Ticket::class,
            [
                'name' => 'Assign Group Escalation Test',
                'content' => '',
                '_actors' => [
                    'requester' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ],
        );

        // Check no group linked to the ticket
        $ticket_group = new Group_Ticket();
        $this->assertEquals(0, count($ticket_group->find(['tickets_id' => $ticket->getID()])));

        // Update ticket with a technician
        $this->updateItem(Ticket::class, $ticket->getID(), [
            '_actors' => [
                'requester' => [
                    [
                        'items_id' => $user1->getID(),
                        'itemtype' => 'User',
                    ],
                ],
                'assign' => [
                    [
                        'items_id' => $user2->getID(),
                        'itemtype' => 'User',
                    ],
                ],
            ],
        ]);

        $ticket_group2 = new Group_Ticket();
        // Check only one groupe linked to the ticket
        $this->assertEquals(1, count($ticket_group2->find(['tickets_id' => $ticket->getID()])));

        $ticket_group2->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
        ]);
        // Check group assigned to the ticket
        $this->assertEquals($group2->getID(), $ticket_group2->fields['groups_id']);

        $ticket_user = new Ticket_User();
        // Check user assigned to the ticket
        $this->assertEquals(2, count($ticket_user->find(['tickets_id' => $ticket->getID()])));

        // Check requester
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'users_id' => $user1->getID(), 'type' => CommonITILActor::REQUESTER])));

        // Check assign
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'users_id' => $user2->getID(), 'type' => CommonITILActor::ASSIGN])));
    }

    private function testTechGroupAttributionProvider()
    {
        yield [
            'conf' => [
                'use_assign_user_group'              => 0,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 0,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 0,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 0,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 0,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 0,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 0,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 1,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 0,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 0,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 0,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 1,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 1,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 2,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 1,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 2,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 1,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 1,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 0,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 0,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 0,
                'remove_tech'                        => 1,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 0,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 1,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 0,
            ],
            'update_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 2,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 0,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 2,
                'group_ticket' => 1,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 1,
                'remove_group'                       => 0,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 2,
            ],
        ];

        yield [
            'conf' => [
                'use_assign_user_group'              => 2,
                'use_assign_user_group_creation'     => 1,
                'use_assign_user_group_modification' => 1,
                'remove_tech'                        => 1,
                'remove_group'                       => 1,
            ],
            'add_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
            'update_expected' => [
                'user_ticket' => 1,
                'group_ticket' => 1,
            ],
        ];
    }

    public function testTechGroupAttributionAddTicket()
    {
        $user1 = getItemByTypeName(User::class, 'glpi');
        $user2 = getItemByTypeName(User::class, 'tech');

        $group1 = $this->createGroupAndAssignUsers($user1);
        $group2 = $this->createGroupAndAssignUsers($user2);
        $group3 = $this->createGroupAndAssignUsers($user1);
        $group4 = $this->createGroupAndAssignUsers($user2);

        foreach ($this->testTechGroupAttributionProvider() as $provider) {
            $this->initConfig($provider['conf']);

            $ticket = $this->createItem(Ticket::class, [
                'name' => 'Assign Group Escalation Test',
                'content' => '',
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ]);

            $ticket_user = new Ticket_User();
            $this->assertEquals($provider['add_expected']['user_ticket'], count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])));

            $group_ticket = new Group_Ticket();
            $this->assertEquals($provider['add_expected']['group_ticket'], count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])));

            if ($provider['conf']['use_assign_user_group_creation'] === 1) {
                $group = $group_ticket->getFromDBByCrit(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN]);
                $this->assertTrue($group);
                if ($provider['conf']['use_assign_user_group'] === 1) {
                    $this->assertEquals($group_ticket->fields['groups_id'], $group1->getID(), "Failed with config: " . json_encode($provider['conf']));
                }

                if ($provider['conf']['use_assign_user_group'] === 2) {
                    $this->assertEquals($group_ticket->fields['groups_id'], $group3->getID(), "Failed with config: " . json_encode($provider['conf']));
                }
            }

            $assign = [
                [
                    'items_id' => $user2->getID(),
                    'itemtype' => 'User',
                ],
                [
                    'items_id' => $user1->getID(),
                    'itemtype' => 'User',
                ],
            ];

            if (!empty($provider['conf']['use_assign_user_group_creation'])) {
                if ($provider['conf']['use_assign_user_group'] === 1) {
                    $assign[] = [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group',
                    ];
                }

                if ($provider['conf']['use_assign_user_group'] === 2) {
                    $assign[] = [
                        'items_id' => $group3->getID(),
                        'itemtype' => 'Group',
                    ];
                }
            }

            $this->updateItem(
                Ticket::class,
                $ticket->getID(),
                [
                    '_actors' => [
                        'assign' => $assign,
                    ],
                ],
            );

            $this->assertEquals($provider['update_expected']['user_ticket'], count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])), 'Failed with config: ' . json_encode($provider['conf']));
            $this->assertEquals($provider['update_expected']['group_ticket'], count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])), 'Failed with config: ' . json_encode($provider['conf']));

            if ($provider['conf']['use_assign_user_group_modification'] === 1) {
                if ($provider['conf']['use_assign_user_group'] === 1) {
                    $group = $group_ticket->getFromDBByCrit(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN, 'groups_id' => $group2->getID()]);
                    $this->assertTrue($group);
                }

                if ($provider['conf']['use_assign_user_group'] === 2) {
                    $group = $group_ticket->getFromDBByCrit(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN, 'groups_id' => $group4->getID()]);
                    $this->assertTrue($group);
                }
            }
        }
    }

    public function testHistory()
    {
        // Update escalade config
        $this->initConfig([
            'show_history' => 1,
        ]);

        $group1 = $this->createGroup();

        // Create ticket without technician
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Assign Group Escalation Test',
            'content' => '',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        $history = new PluginEscaladeHistory();
        $this->assertEquals(1, count($history->find(['tickets_id' => $ticket->getID(),])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group1->getID()])));

        $group2 = $this->createGroup();

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

        $history = new PluginEscaladeHistory();
        $this->assertEquals(2, count($history->find(['tickets_id' => $ticket->getID()])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group1->getID()])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group2->getID()])));

        // Update escalade config
        $this->initConfig([
            'show_history' => 0,
        ]);

        $this->updateItem(Ticket::class, $ticket->getID(), [
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);

        $history = new PluginEscaladeHistory();
        $this->assertEquals(2, count($history->find(['tickets_id' => $ticket->getID()])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group1->getID()])));
    }

    /**
     * Test that the standard target "Group in charge of the ticket"
     * sends notifications to users of both groups (old and new) during an escalation
     */
    public function testStandardGroupNotification()
    {
        global $CFG_GLPI, $DB;

        $this->initConfig([
            'use_assign_user_group'              => 1,
            'use_assign_user_group_creation'     => 0,
            'use_assign_user_group_modification' => 1,
            'remove_tech'                        => 1,
            'remove_group'                       => 1,
            'show_history' => 1,
        ]);

        // Enable notifications for the test
        $CFG_GLPI['use_notifications'] = true;
        $CFG_GLPI['notifications_mailing'] = true;

        // Disable all notifications first
        $DB->update(Notification::getTable(), ['is_active' => false], [new QueryExpression('true')]);

        // Enable only the "assign group" notification
        $notification = new Notification();
        if (!$notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => 'assign_group'])) {
            $this->markTestSkipped('assign_group notification not found');
        }

        $this->assertTrue($notification->update(['id' => $notification->getID(), 'is_active' => 1]));


        // Set notification targets to "Group in charge of the ticket"
        $DB->delete(NotificationTarget::getTable(), ['notifications_id' => $notification->getID()]);
        $this->createItem(NotificationTarget::class, [
            'notifications_id' => $notification->getID(),
            'items_id'         => Notification::ASSIGN_GROUP,
            'type'             => Notification::USER_TYPE,
        ]);

        [$user1, $user2, $user3, $user4] = $this->createItems(User::class, [
            ['name' => 'User 1_' . uniqid(), '_useremails' => [-1 => 'user1_' . uniqid() . '@example.com']],
            ['name' => 'User 2_' . uniqid(), '_useremails' => [-1 => 'user2_' . uniqid() . '@example.com']],
            ['name' => 'User 3_' . uniqid(), '_useremails' => [-1 => 'user3_' . uniqid() . '@example.com']],
            ['name' => 'User 4_' . uniqid(), '_useremails' => [-1 => 'user4_' . uniqid() . '@example.com']],
        ]);

        // Create two groups with users
        $group1 = $this->createGroupAndAssignUsers([$user1, $user2], 'test_standard_group_1_' . uniqid());
        $group2 = $this->createGroupAndAssignUsers([$user3, $user4], 'test_standard_group_2_' . uniqid());

        // Create a ticket assigned to the first group
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Test notification standard',
            'content' => 'Contenu de test',
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

        // Clear the notification queue again
        $this->cleanQueuedNotifications();

        // Escalate the ticket to the second group
        $this->updateItem(
            Ticket::class,
            $ticket->getID(),
            [
                'id' => $ticket->getID(),
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $group2->getID(),
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        );

        // Check notifications
        $queued = new QueuedNotification();
        $notifications = $queued->find();

        // Get the list of notification recipients (emails)
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that users from both groups received notifications
        $group1_user_emails = [$this->getItemEmail($user1), $this->getItemEmail($user2)];
        $group2_user_emails = [$this->getItemEmail($user3), $this->getItemEmail($user4)];

        // At least one user from each group should have received a notification
        $group1_notified = false;
        $group2_notified = false;

        foreach ($group1_user_emails as $email) {
            if (in_array($email, $notification_recipients)) {
                $group1_notified = true;
                break;
            }
        }

        foreach ($group2_user_emails as $email) {
            if (in_array($email, $notification_recipients)) {
                $group2_notified = true;
                break;
            }
        }

        $this->assertTrue($group1_notified, "No user from the original group received a notification");
        $this->assertTrue($group2_notified, "No user from the new group received a notification");
    }

    /**
     * Test that the "Last group escalated in the ticket" target
     * only sends notifications to users of the last escalated group
     */
    public function testLastEscalatedGroupNotification()
    {
        global $CFG_GLPI, $DB;

        $this->initConfig([
            'use_assign_user_group'              => 1,
            'use_assign_user_group_creation'     => 0,
            'use_assign_user_group_modification' => 1,
            'remove_tech'                        => 1,
            'remove_group'                       => 1,
            'show_history' => 1,
        ]);

        // Enable notifications for the test
        $CFG_GLPI['use_notifications'] = true;
        $CFG_GLPI['notifications_mailing'] = true;

        // Disable all notifications first
        $DB->update(Notification::getTable(), ['is_active' => false], [new QueryExpression('true')]);

        // Enable only the "assign group" notification
        $notification = new Notification();
        if (!$notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => 'assign_group'])) {
            $this->markTestSkipped('assign_group notification not found');
        }

        $this->assertTrue($notification->update(['id' => $notification->getID(), 'is_active' => 1]));

        // Add our new target
        $this->setNotificationTargets(
            $notification->fields['id'],
            [
                PluginEscaladeNotification::NTRGT_TICKET_LAST_ESCALADE_GROUP,
            ],
        );

        [$user1, $user2, $user3, $user4] = $this->createItems(User::class, [
            ['name' => 'User 1_' . uniqid(), '_useremails' => [-1 => 'user1_' . uniqid() . '@example.com']],
            ['name' => 'User 2_' . uniqid(), '_useremails' => [-1 => 'user2_' . uniqid() . '@example.com']],
            ['name' => 'User 3_' . uniqid(), '_useremails' => [-1 => 'user3_' . uniqid() . '@example.com']],
            ['name' => 'User 4_' . uniqid(), '_useremails' => [-1 => 'user4_' . uniqid() . '@example.com']],
        ]);

        // Create two groups with users
        $group1 = $this->createGroupAndAssignUsers([$user1, $user2], 'test_escalated_group_1_' . uniqid());
        $group2 = $this->createGroupAndAssignUsers([$user3, $user4], 'test_escalated_group_2_' . uniqid());

        // Create a ticket assigned to the first group
        $ticket = $this->createItem(Ticket::class, [
            'name' => 'Test notification escalade',
            'content' => 'Contenu de test',
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

        // Clear the notification queue
        $this->cleanQueuedNotifications();

        // Escalate the ticket to the second group using the same method as the standard test
        // This should trigger the assign_group notification event
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

        // Check notifications
        $queued = new QueuedNotification();
        $notifications = $queued->find();

        // Get the list of notification recipients (emails)
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that only users from the last group received notifications
        $group1_user_emails = [$this->getItemEmail($user1), $this->getItemEmail($user2)];
        $group2_user_emails = [$this->getItemEmail($user3), $this->getItemEmail($user4)];

        // Check if users from group 1 received a notification
        $group1_notified = false;
        foreach ($group1_user_emails as $email) {
            if (in_array($email, $notification_recipients)) {
                $group1_notified = true;
                break;
            }
        }

        // Check if users from group 2 received a notification
        $group2_notified = false;
        foreach ($group2_user_emails as $email) {
            if (in_array($email, $notification_recipients)) {
                $group2_notified = true;
                break;
            }
        }

        $this->assertFalse($group1_notified, "Users from the original group should not receive a notification");
        $this->assertTrue($group2_notified, "Users from the new group should receive a notification");
    }
}

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
use Group_User;
use NotificationEvent;
use NotificationTarget;
use PluginEscaladeConfig;
use PluginEscaladeNotification;
use PluginEscaladeTicket;
use QueuedNotification;

final class GroupEscalationTest extends EscaladeTestCase
{
    public function testTechGroupAttributionUpdateTicket()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $this->assertTrue($config->update([
            'use_assign_user_group'              => 1,
            'use_assign_user_group_creation'     => 0,
            'use_assign_user_group_modification' => 1,
            'remove_tech'                        => 1,
            'remove_group'                       => 1,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $group1 = new \Group();
        $group1_id = $group1->add(['name' => 'Group_1']);
        $this->assertGreaterThan(0, $group1_id);

        $user_group1 = new \Group_User();
        $user_group1->add([
            'users_id' => $user1->getID(),
            'groups_id' => $group1->getID(),
        ]);
        $this->assertGreaterThan(0, $user_group1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $group2 = new \Group();
        $group2_id = $group2->add(['name' => 'Group_2']);
        $this->assertGreaterThan(0, $group2_id);

        $user_group2 = new \Group_User();
        $user_group2->add([
            'users_id' => $user2->getID(),
            'groups_id' => $group2->getID(),
        ]);
        $this->assertGreaterThan(0, $user_group2->getID());

        // Create ticket without technician
        $ticket = new \Ticket();
        $t_id = $ticket->add([
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
        ]);

        // Check no group linked to the ticket
        $ticket_group = new \Group_Ticket();
        $this->assertEquals(0, count($ticket_group->find(['tickets_id' => $t_id])));

        // Update ticket with a technician
        $this->assertTrue($ticket->update(
            [
                'id' => $t_id,
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
            ],
        ));

        $ticket_group2 = new \Group_Ticket();
        // Check only one groupe linked to the ticket
        $this->assertEquals(1, count($ticket_group2->find(['tickets_id' => $t_id])));

        $ticket_group2->getFromDBByCrit([
            'tickets_id' => $t_id,
        ]);
        // Check group assigned to the ticket
        $this->assertEquals($group2_id, $ticket_group2->fields['groups_id']);

        $ticket_user = new \Ticket_User();
        // Check user assigned to the ticket
        $this->assertEquals(2, count($ticket_user->find(['tickets_id' => $t_id])));

        // Check requester
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'users_id' => $user1->getID(), 'type' => CommonITILActor::REQUESTER])));

        // Check assign
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'users_id' => $user2->getID(), 'type' => CommonITILActor::ASSIGN])));
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
        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $group1 = $this->createItem(
            \Group::class,
            [
                'name' => 'GLPI Group 1',
            ],
        );

        $group2 = $this->createItem(
            \Group::class,
            [
                'name' => 'TECH Group 1',
            ],
        );

        $group3 = $this->createItem(
            \Group::class,
            [
                'name' => 'GLPI Group 2',
            ],
        );

        $group4 = $this->createItem(
            \Group::class,
            [
                'name' => 'TECH Group 2',
            ],
        );

        $this->createItems(
            \Group_User::class,
            [
                [
                    'users_id' => $user1->getID(),
                    'groups_id' => $group1->getID(),
                ],
                [
                    'users_id' => $user2->getID(),
                    'groups_id' => $group2->getID(),
                ],
                [
                    'users_id' => $user1->getID(),
                    'groups_id' => $group3->getID(),
                ],
                [
                    'users_id' => $user2->getID(),
                    'groups_id' => $group4->getID(),
                ],
            ],
        );

        foreach ($this->testTechGroupAttributionProvider() as $provider) {
            $this->login();

            // Update escalade config
            $this->updateItem(
                PluginEscaladeConfig::class,
                1,
                $provider['conf'],
            );

            PluginEscaladeConfig::loadInSession();

            $ticket = $this->createItem(
                \Ticket::class,
                [
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
                ],
            );

            $ticket_user = new \Ticket_User();
            $this->assertEquals($provider['add_expected']['user_ticket'], count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])));

            $group_ticket = new \Group_Ticket();
            $this->assertEquals($provider['add_expected']['group_ticket'], count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])));

            if ($provider['conf']['use_assign_user_group_creation'] === 1) {
                $group = $group_ticket->getFromDBByCrit(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN]);
                $this->assertTrue($group);
                if ($provider['conf']['use_assign_user_group'] === 1) {
                    $this->assertEquals($group_ticket->fields['groups_id'], $group1->getID());
                }
                if ($provider['conf']['use_assign_user_group'] === 2) {
                    $this->assertEquals($group_ticket->fields['groups_id'], $group3->getID());
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
                \Ticket::class,
                $ticket->getID(),
                [
                    '_actors' => [
                        'assign' => $assign,
                    ],
                ],
            );

            $this->assertEquals($provider['update_expected']['user_ticket'], count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])), 'Failed with config: '.json_encode($provider['conf']));
            $this->assertEquals($provider['update_expected']['group_ticket'], count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::ASSIGN])), 'Failed with config: '.json_encode($provider['conf']));

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
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $this->assertTrue($config->update([
            'show_history' => 1,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $group1 = new \Group();
        $group1_id = $group1->add(['name' => 'Group_history_1']);
        $this->assertGreaterThan(0, $group1_id);

        // Create ticket without technician
        $ticket = new \Ticket();
        $t_id = $ticket->add([
            'name' => 'Assign Group Escalation Test',
            'content' => '',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1_id,
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);
        $this->assertGreaterThan(0, $t_id);

        $history = new \PluginEscaladeHistory();
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id,])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id, 'groups_id' => $group1_id])));

        $group2 = new \Group();
        $group2_id = $group2->add(['name' => 'Group_history_2']);
        $this->assertGreaterThan(0, $group2_id);

        $ticket = new \Ticket();
        $ticket_update = $ticket->update([
            'id' => $t_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2_id,
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);
        $this->assertTrue($ticket_update);

        $history = new \PluginEscaladeHistory();
        $this->assertEquals(2, count($history->find(['tickets_id' => $t_id])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id, 'groups_id' => $group1_id])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id, 'groups_id' => $group2_id])));

        $this->assertTrue($config->update([
            'show_history' => 0,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $ticket = new \Ticket();
        $ticket_update = $ticket->update([
            'id' => $t_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1_id,
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]);
        $this->assertTrue($ticket_update);

        $history = new \PluginEscaladeHistory();
        $this->assertEquals(2, count($history->find(['tickets_id' => $t_id])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id, 'groups_id' => $group1_id])));
    }

    /**
     * Test that the standard target "Group in charge of the ticket"
     * sends notifications to users of both groups (old and new) during an escalation
     */
    public function testStandardGroupNotification()
    {
        global $CFG_GLPI;

        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $this->assertTrue($config->update([
            'show_history' => 1,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        // Enable notifications for the test
        $CFG_GLPI['use_notifications'] = 1;
        $CFG_GLPI['notifications_mailing'] = 1;

        // Create two groups with users
        $group1 = $this->createGroupWithUsers('test_standard_group_1', 2);
        $group2 = $this->createGroupWithUsers('test_standard_group_2', 2);

        // Clear the notification queue
        $this->cleanQueuedNotifications();

        // Create a ticket assigned to the first group
        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test notification standard',
            'content' => 'Contenu de test',
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

        // Clear the notification queue again
        $this->cleanQueuedNotifications();

        // Escalate the ticket to the second group
        $this->assertTrue($ticket->update([
            'id' => $ticket_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2['id'],
                        'itemtype' => 'Group',
                    ],
                ],
            ],
        ]));

        // Check notifications
        $queued = new QueuedNotification();
        $notifications = $queued->find();

        // Get the list of notification recipients (emails)
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that users from both groups received notifications
        $group1_user_emails = array_column($group1['users'], 'email');
        $group2_user_emails = array_column($group2['users'], 'email');

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
        global $CFG_GLPI;

        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $this->assertTrue($config->update([
            'show_history' => 1,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        // Enable notifications for the test
        $CFG_GLPI['use_notifications'] = 1;
        $CFG_GLPI['notifications_mailing'] = 1;

        // Modify the notification to use the escalation target
        $notification = new \Notification();
        $notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => 'assign_group']);

        // Add our new target
        $this->setNotificationTargets(
            $notification->fields['id'],
            [
                PluginEscaladeNotification::NTRGT_TICKET_LAST_ESCALADE_GROUP,
            ],
        );

        // Create two groups with users
        $group1 = $this->createGroupWithUsers('test_escalated_group_1', 2);
        $group2 = $this->createGroupWithUsers('test_escalated_group_2', 2);

        // Create a ticket assigned to the first group
        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test notification escalade',
            'content' => 'Contenu de test',
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

        // Clear the notification queue again
        $this->cleanQueuedNotifications();

        // Escalate the ticket to the second group
        \PluginEscaladeTicket::climb_group($ticket_id, $group2['id'], true);

        // Check notifications
        $queued = new QueuedNotification();
        $notifications = $queued->find();

        // Get the list of notification recipients (emails)
        $notification_recipients = [];
        foreach ($notifications as $notif) {
            $notification_recipients[] = $notif['recipient'];
        }

        // Check that only users from the last group received notifications
        $group1_user_emails = array_column($group1['users'], 'email');
        $group2_user_emails = array_column($group2['users'], 'email');

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

    /**
     * Creates a group with users for testing
     *
     * @param string $name Group name
     * @param int $num_users Number of users to create
     * @return array Group details with its users
     */
    private function createGroupWithUsers($name, $num_users = 2)
    {
        // Create the group
        $group_id = $this->createItem(
            \Group::class,
            [
                'name' => $name,
            ],
        )->getID();

        $users = [];

        // Create users and add them to the group
        for ($i = 0; $i < $num_users; $i++) {
            $user_id = $this->createItem(
                \User::class,
                [
                    'name' => "{$name}_user_{$i}",
                ],
            )->getID();

            $this->createItem(
                \UserEmail::class,
                [
                    'users_id' => $user_id,
                    'email' => "{$name}_user_{$i}@example.com",
                ],
            );

            // Add the user to the group
            $group_user_id = $this->createItem(
                \Group_User::class,
                [
                    'users_id' => $user_id,
                    'groups_id' => $group_id,
                ],
            )->getID();

            $users[] = [
                'id' => $user_id,
                'name' => "{$name}_user_{$i}",
                'email' => "{$name}_user_{$i}@example.com",
            ];
        }

        return [
            'id' => $group_id,
            'name' => $name,
            'users' => $users,
        ];
    }

    /**
     * Cleans the notification queue
     */
    private function cleanQueuedNotifications()
    {
        global $DB;
        $DB->doQuery("TRUNCATE TABLE `glpi_queuednotifications`");

        $queued = new QueuedNotification();
        $notifications = $queued->find();
        $this->assertEmpty($notifications, "The notification queue is not empty after cleaning");
    }

    /**
     * Adds notification targets
     */
    private function setNotificationTargets($notification_id, array $targets)
    {
        //Clear targets
        $notification_target = new \NotificationTarget();
        foreach ($notification_target->find(['notifications_id' => $notification_id]) as $target) {
            $notification_target->delete(['id' => $target['id']]);
        }

        //Set new targets
        $notification_target = new \NotificationTarget();
        foreach ($targets as $target) {
            $notification_target->add([
                'notifications_id' => $notification_id,
                'type' => 1, // Type 1 = To
                'items_id' => $target,
            ]);
        }

        $this->assertEquals(count($targets), count($notification_target->find(['notifications_id' => $notification_id])), "The number of notification targets doesn't match after addition");
    }
}

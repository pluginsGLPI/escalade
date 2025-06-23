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

use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use PluginEscaladeConfig;

final class UserEscalationTest extends EscaladeTestCase
{
    public function testUserEscalationRemovesTechnicianWhenGroupIsAdded()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);
        $this->assertTrue($config->update([
            'remove_tech' => 1,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $ticket = new \Ticket();
        $t_id = $ticket->add([
            'name' => 'Task User change Escalation Test',
            'content' => '',
        ]);

        $ticket_user = new \Ticket_User();
        $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN])));

        $group1 = new \Group();
        $group1_id = $group1->add(['name' => 'Group_1']);
        $this->assertGreaterThan(0, $group1_id);

        // Update ticket with just one user
        $this->assertTrue($ticket->update(
            [
                'id' => $t_id,
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ],
        ));

        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));

        $this->assertTrue($ticket->update(
            [
                'id' => $t_id,
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                        [
                            'items_id' => $group1->getID(),
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        ));

        // Check if user is disassociated to this ticket and the group replace it
        $ticket_user = new \Ticket_User();
        $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN])));

        $group_ticket = new \Group_Ticket();
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $t_id, 'groups_id' => $group1->getID()])));

        // Disable remove tech options
        $this->assertTrue($config->update([
            'remove_tech' => 0,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        // Add a user and remove the group from this ticket
        $this->assertTrue($ticket->update(
            [
                'id' => $t_id,
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ],
        ));

        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN])));

        // Add a group to the ticket
        $this->assertTrue($ticket->update(
            [
                'id' => $t_id,
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                        [
                            'items_id' => $group1->getID(),
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        ));

        // Check if the user and group are associated to this ticket
        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN])));

        $group_ticket = new \Group_Ticket();
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $t_id, 'groups_id' => $group1->getID()])));
    }

    public function testUserEscalationRemovesTechnicianWhenUserIsAdded()
    {
        $this->login();

        $this->updateItem(
            PluginEscaladeConfig::class,
            1,
            [
                'remove_tech' => 1,
            ],
        );

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $ticket = $this->createItem(
            \Ticket::class,
            [
                'name' => 'Task User change Escalation Test',
                'content' => '',
            ],
        );

        $ticket_user = new \Ticket_User();
        $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));

        // Update ticket with just one user
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
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
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));

        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                        [
                            'items_id' => $user2->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ],
        );

        // Check if user is disassociated to this ticket and the group replace it
        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));

        // Disable remove tech options
        $this->updateItem(
            PluginEscaladeConfig::class,
            1,
            [
                'remove_tech' => 0,
            ],
        );

        PluginEscaladeConfig::loadInSession();

        // Add a user and remove the group from this ticket
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
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
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));

        // Add a group to the ticket
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                        [
                            'items_id' => $user2->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ],
        );

        // Check if the user and group are associated to this ticket
        $ticket_user = new \Ticket_User();
        $this->assertEquals(2, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
    }

    public function testUserEscalationRemovesTechnicianWhenUserIsAutoAssign()
    {
        $this->login();

        $this->updateItem(
            PluginEscaladeConfig::class,
            1,
            [
                'remove_tech' => 1,
            ],
        );

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user2->getID());

        $ticket = $this->createItem(
            \Ticket::class,
            [
                'name' => 'Task User change Escalation Test',
                'content' => '',
            ],
        );

        $ticket_user = new \Ticket_User();
        $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));

        // Update ticket with just one user
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
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
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));

        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_itil_assign' => [
                    '_type' => "user",
                    'users_id' => \Session::getLoginUserID(),
                    'use_notification' => 1,
                ],
            ],
        );

        // Check if user is disassociated to this ticket and the group replace it
        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user2->getID()])));
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));

        // Disable remove tech options
        $this->updateItem(
            PluginEscaladeConfig::class,
            1,
            [
                'remove_tech' => 0,
            ],
        );

        PluginEscaladeConfig::loadInSession();

        // Add a user and remove the group from this ticket
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
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
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));

        // Assign me to the ticket
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_itil_assign' => [
                    '_type' => "user",
                    'users_id' => \Session::getLoginUserID(),
                ],
            ],
        );

        // Check if the user and group are associated to this ticket
        $ticket_user = new \Ticket_User();
        $this->assertEquals(2, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user2->getID()])));
    }
}

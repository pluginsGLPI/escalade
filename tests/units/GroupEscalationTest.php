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
use PluginEscaladeConfig;

final class GroupEscalationTest extends EscaladeTestCase
{
    public function testTechGroupAttribution()
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
            'use_assign_user_group_creation'     => 1,
            'use_assign_user_group_modification' => 1
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
            'groups_id' => $group1->getID()
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
            'groups_id' => $group2->getID()
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
                        'itemtype' => 'User'
                    ]
                ],
            ]
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
                            'itemtype' => 'User'
                        ]
                    ],
                    'assign' => [
                        [
                            'items_id' => $user2->getID(),
                            'itemtype' => 'User'
                        ]
                    ],
                ],
            ]
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

    public function testUserWithsGroupAttributionWithoutRemoveTechConfig()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $this->assertTrue($config->update([
            'remove_tech'                        => 0,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $group2 = new \Group();
        $group2_id = $group2->add(['name' => 'Group_2']);
        $this->assertGreaterThan(0, $group2_id);

        $user_group2 = new \Group_User();
        $user_group2->add([
            'users_id' => $user2->getID(),
            'groups_id' => $group2->getID()
        ]);
        $this->assertGreaterThan(0, $user_group2->getID());

        // Create ticket without technician
        $ticket = new \Ticket();
        $t_id = $ticket->add([
            'name' => 'Assign Group Escalation Test',
            'content' => '',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $user1->getID(),
                        'itemtype' => 'User'
                    ]
                ],
            ]
        ]);
        $this->assertGreaterThan(0, $t_id);

        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => CommonITILActor::ASSIGN])));

        $ticket = new \Ticket();
        $ticket_update = $ticket->update([
            'id' => $t_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2->getID(),
                        'itemtype' => 'Group'
                    ],
                    [
                        'items_id' => $user1->getID(),
                        'itemtype' => 'User'
                    ]
                ],
            ]
        ]);
        $this->assertTrue($ticket_update);

        $group_ticket = new \Group_Ticket();
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $t_id])));
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => CommonITILActor::ASSIGN])));
    }

    public function testUserWithsGroupAttributionWithsRemoveTechConfig()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $this->assertTrue($config->update([
            'remove_tech'                        => 1,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $group2 = new \Group();
        $group2_id = $group2->add(['name' => 'Group_2']);
        $this->assertGreaterThan(0, $group2_id);

        $user_group2 = new \Group_User();
        $user_group2->add([
            'users_id' => $user2->getID(),
            'groups_id' => $group2->getID()
        ]);
        $this->assertGreaterThan(0, $user_group2->getID());

        // Create ticket without technician
        $ticket = new \Ticket();
        $t_id = $ticket->add([
            'name' => 'Assign Group Escalation Test',
            'content' => '',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $user1->getID(),
                        'itemtype' => 'User'
                    ]
                ],
            ]
        ]);
        $this->assertGreaterThan(0, $t_id);

        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => CommonITILActor::ASSIGN])));

        $ticket = new \Ticket();
        $ticket_update = $ticket->update([
            'id' => $t_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2->getID(),
                        'itemtype' => 'Group'
                    ],
                    [
                        'items_id' => $user1->getID(),
                        'itemtype' => 'User'
                    ]
                ],
            ]
        ]);
        $this->assertTrue($ticket_update);

        $group_ticket = new \Group_Ticket();
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $t_id])));
        $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $t_id, 'type' => CommonITILActor::ASSIGN])));
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
                        'itemtype' => 'Group'
                    ]
                ],
            ]
        ]);
        $this->assertGreaterThan(0, $t_id);

        $history = new \PluginEscaladeHistory();
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id,])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id, 'groups_id' => $group1_id])));

        $group2 = new \Group();
        $group2_id = $group1->add(['name' => 'Group_history_2']);
        $this->assertGreaterThan(0, $group2_id);

        $ticket = new \Ticket();
        $ticket_update = $ticket->update([
            'id' => $t_id,
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group2->getID(),
                        'itemtype' => 'Group'
                    ],
                ],
            ]
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
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group'
                    ],
                ],
            ]
        ]);
        $this->assertTrue($ticket_update);

        $history = new \PluginEscaladeHistory();
        $this->assertEquals(2, count($history->find(['tickets_id' => $t_id])));
        $this->assertEquals(1, count($history->find(['tickets_id' => $t_id, 'groups_id' => $group1_id])));
    }
}

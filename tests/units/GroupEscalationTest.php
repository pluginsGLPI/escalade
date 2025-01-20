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
        $this->assertTrue($config->update([
            'use_assign_user_group'              => 1,
            'use_assign_user_group_creation'     => 1,
            'use_assign_user_group_modification' => 1
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

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

        $ticket_group = new \Group_Ticket();
        $this->assertEquals(0, count($ticket_group->find(['tickets_id' => $t_id])));

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
        $this->assertEquals(1, count($ticket_group2->find(['tickets_id' => $t_id])));

        $ticket_group2->getFromDBByCrit([
            'tickets_id' => $t_id,
        ]);
        $this->assertEquals(2, $ticket_group2->fields['groups_id']);
    }
}

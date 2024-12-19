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
    public function testTaskGroupEscalation()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = reset($config->find());
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);
        $this->assertTrue($config->update([
            'remove_tech' => 1
        ] + $conf));

        $user1 = $user2 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $ticket = new \Ticket();
        $t_id = $ticket->add([
            'name' => 'Task User change Escalation Test',
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

        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN])));

        // Update ticket with just one group
        $this->assertTrue($ticket->update(
            [
                'id' => $t_id,
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User'
                        ],
                        [
                            'items_id' => $user2->getID(),
                            'itemtype' => 'User'
                        ]
                    ],
                ],
            ]
        ));

        $ticket_user = new \Ticket_User();
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $t_id, 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
    }
}

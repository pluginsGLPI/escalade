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

namespace GlpiPlugin\Escalade\Tests\units;

use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use CommonITILActor;
use PluginEscaladeConfig;

final class GroupHistoryTest extends EscaladeTestCase
{
    public function testGroupHistory()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $this->assertTrue($config->update(['remove_group' => 1] + $conf));

        PluginEscaladeConfig::loadInSession();

        $group1 = new \Group();
        $group1_id = $group1->add(['name' => 'Group_1']);
        $this->assertGreaterThan(0, $group1_id);

        $group2 = new \Group();
        $group2_id = $group2->add(['name' => 'Group_2']);
        $this->assertGreaterThan(0, $group2_id);

        // Create ticket with a group assigned
        $ticket = new \Ticket();
        $t_id = $ticket->add([
            'name' => 'Assign Group Escalation Test',
            'content' => '',
            '_actors' => [
                'assign' => [
                    [
                        'items_id' => $group1->getID(),
                        'itemtype' => 'Group'
                    ]
                ],
            ]
        ]);

        // Check if the group was assigned to the ticket
        $ticket_group = new \Group_Ticket();
        $this->assertTrue($ticket_group->getFromDBByCrit([
            'tickets_id' => $t_id,
            'groups_id' => $group1->getID(),
            'type' => CommonITILActor::ASSIGN
        ]));

        // Check if a record was created with 0 as the previous groups
        $history1 = new \PluginEscaladeHistory();
        $this->assertTrue($history1->getFromDBByCrit([
            'tickets_id' => $t_id,
            'groups_id_previous' => 0,
            'groups_id' => $group1->getID()
        ]));

        // Update ticket with a new group added
        $this->assertTrue($ticket->update(
            [
                'id' => $t_id,
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $group1->getID(),
                            'itemtype' => 'Group'
                        ],
                        [
                            'items_id' => $group2->getID(),
                            'itemtype' => 'Group'
                        ]
                    ],
                ],
            ]
        ));

        // Check if group2 was assigned to the ticket
        $ticket_group2 = new \Group_Ticket();
        $this->assertTrue($ticket_group2->getFromDBByCrit([
            'tickets_id' => $t_id,
            'groups_id' => $group2->getID(),
            'type' => CommonITILActor::ASSIGN
        ]));

        // Check if group1 was removed from the ticket
        $ticket_group = new \Group_Ticket();
        $this->assertFalse($ticket_group->getFromDBByCrit([
            'tickets_id' => $t_id,
            'groups_id' => $group1->getID(),
            'type' => CommonITILActor::ASSIGN
        ]));

        // Check if a record was created to reflect the changes
        $history2 = new \PluginEscaladeHistory();
        $this->assertTrue($history2->getFromDBByCrit([
            'tickets_id' => $t_id,
            'groups_id_previous' => $group1->getID(),
            'groups_id' => $group2->getID()
        ]));
    }
}

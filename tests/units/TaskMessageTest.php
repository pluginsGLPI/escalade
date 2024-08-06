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

namespace GlpiPlugin\Scim\Tests\Units;

use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use Group;
use Group_Ticket;
use Plugin;
use PluginEscaladeConfig;
use Ticket;
use TicketTask;

final class TaskMessageTest extends EscaladeTestCase
{
    public function testPluginReactivated()
    {
        $this->login();
        PluginEscaladeConfig::loadInSession();
        $_SESSION['glpilanguage'] = 'en_GB';
        $plugins = new Plugin();
        $plugins->getFromDBbyDir('escalade');
        $this->assertTrue(Plugin::isPluginActive('escalade'));
    }
    protected function dataTestEscalationTaskGroup(): array
    {
        $ticket = new Ticket();
        $ticket->add([
            'name' => 'Escalation Test',
            'content' => '',
        ]);

        $group_test = new Group();
        $group_test->add([
            'name' => 'Test Group',
        ]);

        $group_test_2 = new Group();
        $group_test_2->add([
            'name' => 'Test Group 2',
        ]);

        return [
            [
                'expected' => [
                    'group_name' => null,
                    'ticket_id' => $ticket->getID(),
                    'group_id' => null,
                ],
                'inputs' => [
                    'id' => $ticket->getID(),
                    'name' => 'Escalation Test 1',
                    'update' => true,
                ],
            ],
            [
                'expected' => [
                    'group_name' => $group_test->getName(),
                    'ticket_id' => $ticket->getID(),
                    'group_id' => $group_test->getID(),
                ],
                'inputs' => [
                    'id' => $ticket->getID(),
                    'name' => 'Escalation Test 2',
                    '_actors' => [
                        'assign' => [
                            [
                                'items_id' => $group_test->getID(),
                                'itemtype' => 'Group'
                            ],
                        ],
                    ],
                    'update' => true,
                ],
            ],
            [
                'expected' => [
                    'group_name' => $group_test_2->getName(),
                    'ticket_id' => $ticket->getID(),
                    'group_id' => $group_test_2->getID(),
                ],
                'inputs' => [
                    'id' => $ticket->getID(),
                    'name' => 'Escalation Test 3',
                    '_actors' => [
                        'assign' => [
                            [
                                'items_id' => $group_test->getID(),
                                'itemtype' => 'Group'
                            ],
                            [
                                'items_id' => $group_test_2->getID(),
                                'itemtype' => 'Group'
                            ],
                        ],
                    ],
                    'update' => true,
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataTestEscalationTaskGroup
     */
    public function testEscalationTaskGroup($expected, $inputs)
    {
        $ticket = new Ticket();
        $ticket->getFromDB($expected['ticket_id']);

        $this->assertTrue($ticket->update($inputs));

        $ticket_group = new Group_Ticket();
        $t_groups = $ticket_group->find(['tickets_id' => $expected['ticket_id']]);
        $t_groups = end($t_groups);
        $this->assertEquals($t_groups['groups_id'] ?? null, $expected['group_id']);

        $ticket_task = new TicketTask();
        $t_tasks = $ticket_task->find(['tickets_id' => $expected['ticket_id']]);
        $last_task = end($t_tasks);
        if ($last_task) {
            $this->assertStringContainsString($expected['group_name'], $last_task['content']);
        }
    }
}

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
use Group_Ticket;
use Plugin;
use PluginEscaladeConfig;
use Ticket_User;
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

    public function testGroupEscalation()
    {
        $this->login();
        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);
        PluginEscaladeConfig::loadInSession();

        $ticket = new \Ticket();
        $ticket->add([
            'name' => 'Escalation Test',
            'content' => '',
        ]);

        $user_test = new \User();
        $user_test->add([
            'name' => 'Escalation Technician',
        ]);

        $group_test = new \Group();
        $group_test->add([
            'name' => 'Escalation Group',
        ]);

        // Update ticket with a technician
        $this->assertTrue($ticket->update(
            [
                'id' => $ticket->getID(),
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user_test->getID(),
                            'itemtype' => $user_test->getType(),
                        ],
                    ],
                ],
            ],
        ));

        // Check that the group linked to this ticket is "Test group 1" and that the technician has disappeared.
        $ticket_user = new Ticket_User();
        $ticket_user->getFromDBByCrit(['tickets_id' => $ticket->getID()]);
        $this->assertEquals($ticket_user->fields['users_id'], $user_test->getID());

        // Update ticket with a group
        $this->assertTrue($ticket->update(
            [
                'id' => $ticket->getID(),
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user_test->getID(),
                            'itemtype' => $user_test->getType(),
                        ],
                        [
                            'items_id' => $group_test->getID(),
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        ));

        // Check that the group linked to this ticket is "Test group 1" and that the technician has disappeared.
        $ticket_user = new Ticket_User();
        $t_users = $ticket_user->find(['tickets_id' => $ticket->getID()]);
        $this->assertEquals(count($t_users), 0);

        // Check that the group linked to this ticket is "Test group 1" and that the technician has disappeared.
        $ticket_group = new Group_Ticket();
        $ticket_group->getFromDBByCrit(['tickets_id' => $ticket->getID()]);
        $this->assertEquals($ticket_group->fields['groups_id'], $group_test->getID());
    }

    public function testTaskGroupEscalation()
    {
        $this->login();

        // Update escalade config
        $this->updateItem(
            PluginEscaladeConfig::class,
            1,
            [
                'remove_group' => 1,
                'task_history' => 1,
            ]
        );

        PluginEscaladeConfig::loadInSession();

        $ticket = new \Ticket();
        $ticket->add([
            'name' => 'Task Group Escalation Test',
            'content' => '',
        ]);

        $group_test = new \Group();
        $group_test->add([
            'name' => 'Task Group 1',
        ]);

        $group_test_2 = new \Group();
        $group_test_2->add([
            'name' => 'Task Group 2',
        ]);

        // Update ticket with just one group
        $this->assertTrue($ticket->update(
            [
                'id' => $ticket->getID(),
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $group_test->getID(),
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        ));

        // Check the correct task content
        $ticket_task = new TicketTask();
        $t_tasks = $ticket_task->find(['tickets_id' => $ticket->getID()]);
        $last_task = end($t_tasks);
        $this->assertStringContainsString('Task Group 1', $last_task['content']);

        $this->assertTrue($ticket->update(
            [
                'id' => $ticket->getID(),
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $group_test->getID(),
                            'itemtype' => 'Group',
                        ],
                        [
                            'items_id' => $group_test_2->getID(),
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        ));

        // Check the correct order of tasks and content
        $ticket_task = new TicketTask();
        $t_tasks = $ticket_task->find(['tickets_id' => $ticket->getID()]);
        $first_task = reset($t_tasks);
        $this->assertStringContainsString('Task Group 1', $first_task['content']);
        $last_task = end($t_tasks);
        $this->assertStringContainsString('Task Group 2', $last_task['content']);

        // Check that the group linked to this ticket is "Test group 2".
        $ticket_group = new Group_Ticket();
        $t_groups = $ticket_group->find(['tickets_id' => $ticket->getID()]);
        $t_groups = end($t_groups);
        $this->assertEquals($t_groups['groups_id'], $group_test_2->getID());
    }
}

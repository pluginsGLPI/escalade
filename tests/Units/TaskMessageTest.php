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

    public static function testTaskGroupEscalationProvider()
    {
        yield [
            'conf' => [
                'remove_group' => 0,
                'solve_return_group' => 0,
                'task_history' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_group' => 0,
                'solve_return_group' => 0,
                'task_history' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_group' => 0,
                'solve_return_group' => 1,
                'task_history' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_group' => 0,
                'solve_return_group' => 1,
                'task_history' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_group' => 1,
                'solve_return_group' => 0,
                'task_history' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_group' => 1,
                'solve_return_group' => 0,
                'task_history' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_group' => 1,
                'solve_return_group' => 1,
                'task_history' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_group' => 1,
                'solve_return_group' => 1,
                'task_history' => 1,
            ],
        ];

    }

    /**
     * @dataProvider testTaskGroupEscalationProvider
     */
    public function testTaskGroupEscalation(array $conf)
    {
        $this->login();

        // Update escalade config
        $this->updateItem(
            PluginEscaladeConfig::class,
            1,
            $conf,
        );

        PluginEscaladeConfig::loadInSession();

        $ticket = $this->createItem(
            \Ticket::class,
            [
                'name' => 'Task Group Escalation Test',
                'content' => '',
            ],
        );

        $group1 = $this->createItem(
            \Group::class,
            [
                'name' => 'Test group 1',
            ],
        );

        $group2 = $this->createItem(
            \Group::class,
            [
                'name' => 'Test group 2',
            ],
        );

        $_SESSION["glpi_currenttime"] = '2025-01-01 00:00:00';

        $this->climbWithHistoryButton($ticket, $group1);

        // Check the correct task content
        $ticket_task = new TicketTask();
        $t_tasks = $ticket_task->find(['tickets_id' => $ticket->getID()]);
        if (!$conf['task_history']) {
            $this->assertEquals(0, count($t_tasks));
        } else {
            $this->assertEquals(1, count($t_tasks));
            $last_task = end($t_tasks);
            $this->assertStringContainsString('Escalation to the group ' . $group1->fields['name'], $last_task['content']);
        }

        $_SESSION["glpi_currenttime"] = '2025-01-01 01:00:00';

        $this->climbWithTimelineButton($ticket, $group2, [
            'comment' => 'Test comment',
        ]);

        // Check the correct task content
        $ticket_task = new TicketTask();
        $t_tasks = $ticket_task->find(['tickets_id' => $ticket->getID()]);
        if (!$conf['task_history']) {
            $this->assertEquals(0, count($t_tasks));
        } else {
            $this->assertEquals(2, count($t_tasks));
            $last_task = end($t_tasks);
            $this->assertStringContainsString('Escalation to the group ' . $group2->fields['name'], $last_task['content']);
            $this->assertStringContainsString('Test comment', $last_task['content']);
        }

        $_SESSION["glpi_currenttime"] = '2025-01-01 02:00:00';

        if ($conf['remove_group'] && $conf['solve_return_group']) {
            $this->climbWithSolvedTicket($ticket, $group1);

            // Check the correct task content
            $ticket_task = new TicketTask();
            $t_tasks = $ticket_task->find(['tickets_id' => $ticket->getID()]);
            if (!$conf['task_history']) {
                $this->assertEquals(0, count($t_tasks));
            } else {
                $this->assertEquals(3, count($t_tasks));
                $last_task = end($t_tasks);
                $this->assertStringContainsString('Solution provided, back to the group ' . $group1->fields['name'], $last_task['content']);
            }

            $_SESSION["glpi_currenttime"] = '2025-01-01 03:00:00';

            $this->climbWithRejectSolutionTicket($ticket, $group2);

            // Check the correct task content
            $ticket_task = new TicketTask();
            $t_tasks = $ticket_task->find(['tickets_id' => $ticket->getID()]);
            if (!$conf['task_history']) {
                $this->assertEquals(0, count($t_tasks));
            } else {
                $this->assertEquals(4, count($t_tasks));
                $last_task = end($t_tasks);
                $this->assertStringContainsString('Solution rejected, return to the group ' . $group2->fields['name'], $last_task['content']);
            }
        }
    }
}

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
        $this->initConfig();
        $_SESSION['glpilanguage'] = 'en_GB';
        $plugins = new Plugin();
        $plugins->getFromDBbyDir('escalade');
        $this->assertTrue(Plugin::isPluginActive('escalade'));
    }

    public function testGroupEscalation()
    {
        $this->initConfig([
            'remove_tech' => 1,
        ]);

        $ticket = $this->createItem('Ticket', [
            'name' => 'Escalation Test',
            'content' => '',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $user_test = $this->createItem('User', [
            'name' => 'Escalation Technician',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $group_test = $this->createItem('Group', [
            'name' => 'Escalation Group',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        // Update ticket with a technician
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user_test->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ],
        );

        // Check that the group linked to this ticket is "Test group 1" and that the technician has disappeared.
        $ticket_user = new Ticket_User();
        $ticket_user->getFromDBByCrit(['tickets_id' => $ticket->getID()]);
        $this->assertEquals($ticket_user->fields['users_id'], $user_test->getID());

        // Update ticket with a group
        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user_test->getID(),
                            'itemtype' => 'User',
                        ],
                        [
                            'items_id' => $group_test->getID(),
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        );

        // Check that the group linked to this ticket is "Test group 1" and that the technician has disappeared.
        $ticket_user = new Ticket_User();
        $t_users = $ticket_user->find(['tickets_id' => $ticket->getID()]);
        $this->assertEquals(count($t_users), 0);

        // Check that the group linked to this ticket is "Test group 1" and that the technician has disappeared.
        $ticket_group = new Group_Ticket();
        $ticket_group->getFromDBByCrit(['tickets_id' => $ticket->getID()]);
        $this->assertEquals($ticket_group->fields['groups_id'], $group_test->getID());
    }

    public static function taskGroupEscalationProvider()
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

    #[\PHPUnit\Framework\Attributes\DataProvider('taskGroupEscalationProvider')]
    public function testTaskGroupEscalation(array $conf)
    {
        $this->initConfig($conf);

        $ticket = $this->createItem('Ticket', [
            'name' => 'Task Group Escalation Test',
            'content' => '',
            'entities_id' => 0,
        ]);

        $group1 = $this->createItem('Group', [
            'name' => 'Test group 1',
            'entities_id' => 0,
        ]);

        $group2 = $this->createItem('Group', [
            'name' => 'Test group 2',
            'entities_id' => 0,
        ]);

        $_SESSION["glpi_currenttime"] = '2025-01-01 00:00:00';

        $this->escalateWithHistoryButton($ticket, $group1);

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

        $this->escalateWithTimelineButton($ticket, $group2, [
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
            $this->escalateWithSolvedTicket($ticket, $group1);

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

            $this->escalateWithRejectSolutionTicket($ticket, $group2);

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

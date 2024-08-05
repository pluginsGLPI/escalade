<?php

use PHPUnit\Framework\TestCase;

class TaskMessage extends TestCase
{
    protected function dataTestEscalationTaskGroup(): array
    {
        $ticket = new Ticket();
        $ticket->add([
            'name' => 'Test',
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
                    'groups_count' => 0,
                    'tasks_count' => 0,
                    'last_task_content' => null,
                    'ticket_id' => $ticket->getID(),
                    'group_id' => null,
                ],
                'inputs' => [
                    'id' => $ticket->getID(),
                ],
            ],
            [
                'expected' => [
                    'groups_count' => 1,
                    'tasks_count' => 1,
                    'last_task_content' => 'Escalation to the group Test Group.',
                    'ticket_id' => $ticket->getID(),
                    'group_id' => $group_test->getID(),
                ],
                'inputs' => [
                    'id' => $ticket->getID(),
                    '_actors' => [
                        'assign' => [
                            [
                                'items_id' => $group_test->getID(),
                                'itemtype' => 'Group'
                            ],
                        ],
                    ],
                ],
            ],
            [
                'expected' => [
                    'groups_count' => 1,
                    'tasks_count' => 2,
                    'last_task_content' => 'Escalation to the group Test Group 2.',
                    'ticket_id' => $ticket->getID(),
                    'group_id' => $group_test_2->getID(),
                ],
                'inputs' => [
                    'id' => $ticket->getID(),
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
        $ticket->update($inputs);

        $ticket_group = new Group_Ticket();
        $t_groups = $ticket_group->find(['tickets_id' => $expected['ticket_id']]);
        $this->assertEquals(count($t_groups), $expected['groups_count']);

        $ticket_task = new TicketTask();
        $t_tasks = $ticket_task->find(['tickets_id' => $expected['ticket_id']]);
        $this->assertEquals(count($t_tasks), $expected['tasks_count']);
        $last_task = end($t_tasks);
        if ($last_task) {
            $this->assertEquals($last_task['content'], $expected['last_task_content']);
        }
    }
}

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
use CommonITILObject;
use Entity;
use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use Group;
use Group_Ticket;
use Group_User;
use ITILCategory;
use PluginEscaladeTicket;
use Ticket;
use Ticket_User;
use User;
use TicketTask;

final class TicketTest extends EscaladeTestCase
{
    public function testEscalationWithMandatoryFields()
    {
        $this->initConfig();

        // Create ticket template without mandatory fields
        $template = $this->createItem('TicketTemplate', [
            'name' => 'Test template for escalation',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $template_id = $template->getID();

        // Create a category linked to this template
        $category = $this->createItem('ITILCategory', [
            'name' => 'Test category with template',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $category_id = $category->getID();

        // Create first group for assignment
        $group1 = $this->createItem('Group', [
            'name' => 'Group for escalation test 1',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $group1_id = $group1->getID();

        // Create second group for escalation
        $group2 = $this->createItem('Group', [
            'name' => 'Group for escalation test 2',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $group2_id = $group2->getID();

        // Create a ticket with the template and first group assigned
        $ticket = $this->createItem('Ticket', [
            'name' => 'Test ticket for escalation',
            'content' => 'Content for test ticket',
            'entities_id' => 0,
            'itilcategories_id' => $category_id,
            '_groups_id_assign' => [$group1_id],
        ]);
        $ticket_id = $ticket->getID();

        // Add a mandatory field to the template
        $mandatory_field = $this->createItem('TicketTemplateMandatoryField', [
            'tickettemplates_id' => $template_id,
            'num' => 10, // impact field
        ]);
        $mandatory_field->getID();

        // Now try to escalate the ticket without filling the mandatory field
        // First, create a properly configured ticket instance
        $ticket_instance = new Ticket();
        $this->assertTrue($ticket_instance->getFromDB($ticket_id));

        // Prepare the input with missing mandatory field
        $input_without_mandatory = [
            'id' => $ticket_id,
            'groups_id' => $group2_id,
            'actortype' => CommonITILActor::ASSIGN,
        ];

        // Use a mock to test the behavior
        $mock_ticket = $this->getMockBuilder(Ticket::class)
            ->onlyMethods(['prepareInputForUpdate'])
            ->getMock();

        // Configure the mock to return false when prepareInputForUpdate is called
        // This simulates the behavior when mandatory fields are missing
        $mock_ticket->method('prepareInputForUpdate')
            ->willReturn(false);

        $mock_ticket->input = $input_without_mandatory;
        $mock_ticket->fields = $ticket_instance->fields;

        // Test pre_item_update with missing mandatory field
        $result = PluginEscaladeTicket::pre_item_update($mock_ticket);
        $this->assertFalse($result, "Escalation should be blocked when mandatory fields are not filled");

        // Now simulate a case where all mandatory fields are filled
        // Update the ticket with the mandatory field
        $this->updateItem('Ticket', $ticket_id, [
            'impact' => 3, // Fill the mandatory field
        ]);

        // Refresh the ticket data
        $ticket_instance->getFromDB($ticket_id);

        // Create a new mock that will pass the prepareInputForUpdate check
        $mock_ticket_success = $this->getMockBuilder(Ticket::class)
            ->onlyMethods(['prepareInputForUpdate'])
            ->getMock();

        // Configure mock to return the input (indicating success)
        $mock_ticket_success->method('prepareInputForUpdate')
            ->willReturnArgument(0);

        $input_with_mandatory = [
            'id' => $ticket_id,
            'groups_id' => $group2_id,
            'actortype' => CommonITILActor::ASSIGN,
            '_actors' => [
                'assign' => [
                    [
                        'itemtype' => 'Group',
                        'items_id' => $group2_id,
                    ],
                ],
            ],
        ];

        $mock_ticket_success->input = $input_with_mandatory;
        $mock_ticket_success->fields = $ticket_instance->fields;

        // Test pre_item_update with all mandatory fields filled
        $result = PluginEscaladeTicket::pre_item_update($mock_ticket_success);
        $this->assertNotFalse($result, "Escalation should not be blocked when all mandatory fields are filled");
        $this->assertSame($mock_ticket_success, $result, "Function should return the ticket object on success");

        // Check if the _disablenotif flag is set
        $this->assertTrue(
            isset($result->input['_disablenotif']),
            "The _disablenotif flag should be set on successful escalation",
        );

        // Add the group to actually test the escalation
        $this->createItem('Group_Ticket', [
            'tickets_id' => $ticket_id,
            'groups_id' => $group2_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        // Verify that the group was added
        $group_ticket_finder = new Group_Ticket();
        $assigned_groups = $group_ticket_finder->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        $found_new_group = false;
        foreach ($assigned_groups as $grp) {
            if ($grp['groups_id'] == $group2_id) {
                $found_new_group = true;
                break;
            }
        }

        $this->assertTrue($found_new_group, "New group should be assigned after successful escalation");
    }

    /**
     * Test rule execution when escalating a ticket
     */
    public function testTriggerEscalationAndExecuteRuleOnTicket()
    {
        $this->initConfig();

        // Create a group
        $group_observer = $this->createItem('Group', [
            'name' => 'Group Observer',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $group_observer_id = $group_observer->getID();

        $group_tech = $this->createItem('Group', [
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $group_tech_id = $group_tech->getID();

        // Get the tech user
        $user_tech = new User();
        $user_tech->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user_tech->getID());

        // Create a rule to assign the group observer if the group tech or tech user is assigned
        $rule = $this->createItem('Rule', [
            'name' => 'Add RuleTicket',
            'sub_type' => 'RuleTicket',
            'match' => 'OR',
            'is_active' => 1,
            'condition' => 2,
            'entities_id' => 0,
        ]);
        $rule_id = $rule->getID();

        $this->createItem('RuleAction', [
            'rules_id' => $rule_id,
            'action_type' => 'assign',
            'field' => '_groups_id_observer',
            'value' => $group_observer_id,
        ]);

        $this->createItem('RuleCriteria', [
            'rules_id' => $rule_id,
            'criteria' => '_groups_id_assign',
            'condition' => 0,
            'pattern' => $group_tech_id,
        ]);

        $this->createItem('RuleCriteria', [
            'rules_id' => $rule_id,
            'criteria' => '_users_id_assign',
            'condition' => 0,
            'pattern' => $user_tech->getID(),
        ]);

        // Test the rule ticket during the escalation
        foreach ($this->escalateTicketMethods(['escalateWithTimelineButton', 'escalateWithHistoryButton', 'escalateWithAssignMySelfButton']) as $data) {
            $ticket = $this->createItem('Ticket', [
                'name' => 'Test ticket for escalation',
                'content' => 'Content for test ticket',
                'entities_id' => 0,
            ]);
            $ticket_id = $ticket->getID();

            $group_ticket = new Group_Ticket();
            $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_observer_id, 'type' => CommonITILActor::OBSERVER])));
            if ($data['itemtype'] === Group::class) {
                $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_tech_id, 'type' => CommonITILActor::ASSIGN])));
                $group_tech_obj = new Group();
                $group_tech_obj->getFromDB($group_tech_id);
                $this->{$data['method']}($ticket, $group_tech_obj);
                $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_tech_id, 'type' => CommonITILActor::ASSIGN])));
            } else {
                $user_ticket = new Ticket_User();
                $this->assertEquals(0, count($user_ticket->find(['tickets_id' => $ticket_id, 'users_id' => $user_tech->getID(), 'type' => CommonITILActor::ASSIGN])));
                $this->{$data['method']}($ticket, $user_tech);
                $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'users_id' => $user_tech->getID(), 'type' => CommonITILActor::ASSIGN])));
            }

            $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_observer_id, 'type' => CommonITILActor::OBSERVER])));
        }
    }

    public function testTicketUpdateDoesNotChangeITILCategoryAssignedGroup()
    {
        $this->initConfig([
            'reassign_group_from_cat' => 1,
            'remove_group' => 1,
        ]);

        $this->updateItem('Entity', 0, [
            'auto_assign_mode' => Entity::AUTO_ASSIGN_CATEGORY_HARDWARE,
        ]);

        $group1 = $this->createItem('Group', [
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $group1_id = $group1->getID();

        $group2 = $this->createItem('Group', [
            'name' => 'Group tech 2',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $group2_id = $group2->getID();

        $itil_cat = $this->createItem('ITILCategory', [
            'name' => 'Cat1',
            'groups_id' => $group1_id,
            'entities_id' => 0,
        ]);
        $itil_cat_id = $itil_cat->getID();

        $ticket = $this->createItem('Ticket', [
            'name' => 'Test ticket for escalation',
            'content' => 'Content for test ticket',
            'entities_id' => 0,
            'itilcategories_id' => $itil_cat_id,
        ]);
        $ticket_id = $ticket->getID();

        $group_ticket = new Group_Ticket();
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id, 'type' => CommonITILActor::ASSIGN])));

        $this->updateItem('Ticket', $ticket_id, [
            '_actors' => [
                'assign' => [
                    [
                        'itemtype' => Group::class,
                        'items_id' => $group1_id,
                    ],
                    [
                        'itemtype' => Group::class,
                        'items_id' => $group2_id,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id, 'type' => CommonITILActor::ASSIGN])));

        $this->updateItem('Ticket', $ticket_id, [
            'status' => CommonITILObject::WAITING,
        ]);

        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id, 'type' => CommonITILActor::ASSIGN])));
    }

    public function testStatusTicketOption()
    {
        $this->initConfig([
            'ticket_last_status' => CommonITILObject::INCOMING,
            'remove_tech' => 0,
        ]);

        $user1 = new User();
        $user1->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new User();
        $user2->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user2->getID());

        $group_tech = $this->createItem('Group', [
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $group_tech_id = $group_tech->getID();

        $ticket = $this->createItem('Ticket', [
            'name' => 'Test ticket',
            'content' => 'Content',
            'entities_id' => 0,
        ]);
        $ticket_id = $ticket->getID();

        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::INCOMING, $ticket->fields['status']);

        $group_ticket = new Group_Ticket();
        $user_ticket = new Ticket_User();
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(0, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));

        $this->updateItem(
            Ticket::class,
            $ticket_id,
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
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::ASSIGNED, $ticket->fields['status']);

        $this->updateItem(
            Ticket::class,
            $ticket_id,
            [
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                        [
                            'items_id' => $group_tech_id,
                            'itemtype' => 'Group',
                        ],
                    ],
                ],
            ],
        );
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN, 'groups_id' => $group_tech_id])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::INCOMING, $ticket->fields['status']);

        $this->updateItem(
            Ticket::class,
            $ticket_id,
            [
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                        [
                            'items_id' => $group_tech_id,
                            'itemtype' => 'Group',
                        ],
                        [
                            'items_id' => $user2->getID(),
                            'itemtype' => 'User',
                            'use_notification' => 1,
                        ],
                    ],
                ],
            ],
        );
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN, 'groups_id' => $group_tech_id])));
        $this->assertEquals(2, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => CommonITILActor::ASSIGN, 'users_id' => $user2->getID()])));
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::ASSIGNED, $ticket->fields['status']);
    }

    private function testAssignGroupToTicketWithCategoryProvider()
    {
        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 0,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 0,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 0,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 0,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 0,
            ],
            'expected' => [
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 0,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'             => 1,
                'remove_group'            => 1,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat'  => 1,
            ],
            'expected' => [
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 0,
                'group_2_is_assign' => 1,
            ],
        ];
    }

    public function testAssignGroupToTicketWithCategory()
    {
        $this->initConfig();

        $ticket_user = new Ticket_User();
        $ticket_group = new Group_Ticket();

        $user1 = new User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $this->updateItem(
            Entity::class,
            $this->getTestRootEntity(true),
            [
                'id' => 0,
                'auto_assign_mode' => 2,
            ],
        );

        [$group1, $group2] = $this->createItems(
            Group::class,
            [
                [
                    'name' => 'GLPI Group',
                    'entities_id' => $this->getTestRootEntity(true),
                ],
                [
                    'name' => 'TECH Group',
                    'entities_id' => $this->getTestRootEntity(true),
                ],
            ],
        );

        $group1_id = $group1->getID();
        $group2_id = $group2->getID();

        $this->createItems(
            Group_User::class,
            [
                [
                    'users_id' => $user1->getID(),
                    'groups_id' => $group1_id,
                ],
                [
                    'users_id' => $user2->getID(),
                    'groups_id' => $group2_id,
                ],
            ],
        );

        [$itil_category1, $itil_category2] = $this->createItems(
            ITILCategory::class,
            [
                [
                    'name' => 'Cat1',
                    'users_id' => $user1->getID(),
                    'groups_id' => $group1_id,
                    'entities_id' => $this->getTestRootEntity(true),
                ],
                [
                    'name' => 'Cat2',
                    'users_id' => $user2->getID(),
                    'groups_id' => $group2_id,
                    'entities_id' => $this->getTestRootEntity(true),
                ],
            ],
        );

        $itil_category1_id = $itil_category1->getID();
        $itil_category2_id = $itil_category2->getID();

        foreach ($this->testAssignGroupToTicketWithCategoryProvider() as $provider) {
            // Update escalade config
            $this->initConfig($provider['conf'] + ['use_assign_user_group' => 0]);

            $ticket = $this->createItem(
                Ticket::class,
                [
                    'name' => 'Assign Cat Escalation Test',
                    'content' => 'content',
                    'itilcategories_id' => $itil_category1_id,
                    'entities_id' => $this->getTestRootEntity(true),
                ],
            );
            $ticket_id = $ticket->getID();

            $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket_id])));
            $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user1->getID()])));
            $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user2->getID()])));
            $this->assertEquals(1, count($ticket_group->find(['tickets_id' => $ticket_id])));
            $this->assertEquals(1, count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id])));
            $this->assertEquals(0, count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id])));

            $this->updateItem(
                Ticket::class,
                $ticket_id,
                [
                    'id' => $ticket_id,
                    'itilcategories_id' => $itil_category2_id,
                ],
            );

            $this->assertEquals($provider['expected']['user_1_is_assign'], count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user1->getID()])));
            $this->assertEquals($provider['expected']['user_2_is_assign'], count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user2->getID()])));
            $this->assertEquals($provider['expected']['group_1_is_assign'], count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id])));
            $this->assertEquals($provider['expected']['group_2_is_assign'], count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id])), "Failed with config: " . print_r($provider['conf'], true) . " and expected: " . print_r($provider['expected'], true));
        }
    }

    /**
     * Test that adding a solution to a ticket with mandatory template fields works correctly
     * This test reproduces the issue where the Escalade plugin interfered with template validation
     * when adding solutions to tickets with mandatory requester fields.
     */
    public function testAddSolutionWithMandatoryTemplateFields()
    {
        $this->initConfig();

        // Create a ticket template with mandatory requester field
        $template = $this->createItem('TicketTemplate', [
            'name' => 'Template with mandatory requester',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $template_id = $template->getID();

        // Add mandatory field (requester) to the template
        $mandatory_field = $this->createItem('TicketTemplateMandatoryField', [
            'tickettemplates_id' => $template_id,
            'num' => 4, // _users_id_requester field number
        ]);
        $mandatory_field->getID();

        // Create a category linked to this template
        $category = $this->createItem('ITILCategory', [
            'name' => 'Category with mandatory template',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $category_id = $category->getID();

        // Create a user to be the requester
        $user = $this->createItem('User', [
            'name' => 'test_requester',
            'realname' => 'Test Requester',
            'firstname' => 'User',
        ]);
        $user_id = $user->getID();

        // Create a ticket with the template and mandatory requester filled
        $ticket = $this->createItem('Ticket', [
            'name' => 'Ticket for solution test',
            'content' => 'Content for solution test',
            'itilcategories_id' => $category_id,
            '_users_id_requester' => [$user_id],
            'status' => CommonITILObject::ASSIGNED,
            'entities_id' => 0,
        ]);
        $ticket_id = $ticket->getID();

        // Verify the ticket was created with the requester
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::ASSIGNED, $ticket->fields['status']);

        // Verify that the requester is properly assigned
        $ticket_user = new Ticket_User();
        $requesters = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::REQUESTER,
        ]);
        $this->assertEquals(1, count($requesters));
        $requester = reset($requesters);
        $this->assertEquals($user_id, $requester['users_id']);

        // Create a solution type
        $solution_type = $this->createItem('SolutionType', [
            'name' => 'Test solution type',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $solution_type_id = $solution_type->getID();
        $this->assertGreaterThan(0, $solution_type_id);

        // Now try to add a solution to the ticket - this should work without validation errors
        // In GLPI, we need to add the solution using ITILSolution, then update the ticket status
        $solution = $this->createItem('ITILSolution', [
            'itemtype' => 'Ticket',
            'items_id' => $ticket_id,
            'solutiontypes_id' => $solution_type_id,
            'content' => 'This is the solution to the problem.',
        ]);
        $solution_id = $solution->getID();
        $this->assertGreaterThan(0, $solution_id);

        // Update ticket status to solved - this is where the template validation could fail
        $this->updateItem(
            Ticket::class,
            $ticket_id,
            [
                'id' => $ticket_id,
                'status' => CommonITILObject::SOLVED,
            ],
        );

        // Reload the ticket and verify it's now solved
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::SOLVED, $ticket->fields['status']);

        // Verify the solution was properly added
        $solutions = $solution->find([
            'itemtype' => 'Ticket',
            'items_id' => $ticket_id,
        ]);
        $this->assertEquals(1, count($solutions));
        $solution_data = reset($solutions);
        $this->assertEquals('This is the solution to the problem.', $solution_data['content']);
        $this->assertEquals($solution_type_id, $solution_data['solutiontypes_id']);

        // Verify that the requester is still properly assigned (not lost during solution update)
        $requesters_after = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::REQUESTER,
        ]);
        $this->assertEquals(1, count($requesters_after));
        $requester_after = reset($requesters_after);
        $this->assertEquals($user_id, $requester_after['users_id']);

        // Clean up
        $ticket->delete(['id' => $ticket_id], true);
        $template->delete(['id' => $template_id], true);
        $category->delete(['id' => $category_id], true);
        $user->delete(['id' => $user_id], true);
        $solution_type->delete(['id' => $solution_type_id], true);
    }

    /**
     * Test that using "Associate myself" button works correctly with mandatory template fields
     * This test ensures the assign_me function doesn't interfere with template validation
     */
    public function testAssignMeWithMandatoryTemplateFields()
    {
        $this->initConfig();

        // Create a ticket template with mandatory requester field
        $template = $this->createItem('TicketTemplate', [
            'name' => 'Test template for assign me',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $template_id = $template->getID();
        $this->assertGreaterThan(0, $template_id);

        // Add mandatory field (requester) to the template
        $mandatory_field = $this->createItem('TicketTemplateMandatoryField', [
            'tickettemplates_id' => $template_id,
            'num' => 4, // _users_id_requester field number
        ]);
        $mandatory_field_id = $mandatory_field->getID();
        $this->assertGreaterThan(0, $mandatory_field_id);

        // Create a category linked to this template
        $category = $this->createItem('ITILCategory', [
            'name' => 'Test category for assign me',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $category_id = $category->getID();
        $this->assertGreaterThan(0, $category_id);

        // Create a requester user
        $requester = $this->createItem('User', [
            'name' => 'requester_test',
            'firstname' => 'Requester',
            'entities_id' => 0,
        ]);
        $requester_id = $requester->getID();
        $this->assertGreaterThan(0, $requester_id);

        // Create a ticket with the template and mandatory requester filled
        $ticket = $this->createItem('Ticket', [
            'name' => 'Test ticket for assign me',
            'content' => 'Content for test ticket',
            'itilcategories_id' => $category_id,
            '_users_id_requester' => [$requester_id],
            'status' => CommonITILObject::INCOMING,
            'entities_id' => 0,
        ]);
        $ticket_id = $ticket->getID();
        $this->assertGreaterThan(0, $ticket_id);

        // Verify the ticket was created with the requester
        $ticket_user = new Ticket_User();
        $requesters = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::REQUESTER,
        ]);
        $this->assertEquals(1, count($requesters));
        $requester_data = reset($requesters);
        $this->assertEquals($requester_id, $requester_data['users_id']);

        // Get current user ID for the assignment test
        $current_user_id = $_SESSION['glpiID'];

        // Verify the current user is not already assigned
        $assigned_users = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
            'users_id' => $current_user_id,
        ]);
        $this->assertEquals(0, count($assigned_users));

        // Use the "Associate myself" functionality - simulate exactly what happens in ticket.form.php
        // when addme_as_actor is called
        // This should work without template validation errors
        $this->updateItem(
            Ticket::class,
            $ticket_id,
            [
                'id' => $ticket_id,
                '_actors' => [
                    'requester' => [
                        [
                            'itemtype' => 'User',
                            'items_id' => $requester_id,
                        ],
                    ],
                    'assign' => [
                        [
                            'itemtype' => 'User',
                            'items_id' => $current_user_id,
                            'use_notification' => 1,
                        ],
                    ],
                ],
            ],
        );

        // Verify the current user was assigned successfully
        $assigned_users_after = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
            'users_id' => $current_user_id,
        ]);
        $this->assertEquals(1, count($assigned_users_after));

        // Verify the ticket status changed to ASSIGNED
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::ASSIGNED, $ticket->fields['status']);

        // Verify that the requester is still properly assigned (not lost during self-assignment)
        $requesters_after = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::REQUESTER,
        ]);
        $this->assertEquals(1, count($requesters_after));
        $requester_after = reset($requesters_after);
        $this->assertEquals($requester_id, $requester_after['users_id']);

        // Test that calling it again doesn't create duplicate assignments
        $this->updateItem(
            Ticket::class,
            $ticket_id,
            [
                'id' => $ticket_id,
                '_actors' => [
                    'requester' => [
                        [
                            'itemtype' => 'User',
                            'items_id' => $requester_id,
                        ],
                    ],
                    'assign' => [
                        [
                            'itemtype' => 'User',
                            'items_id' => $current_user_id,
                            'use_notification' => 1,
                        ],
                    ],
                ],
            ],
        );
        $assigned_users_final = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
            'users_id' => $current_user_id,
        ]);
        $this->assertEquals(1, count($assigned_users_final)); // Should still be 1, not 2

        // Clean up
        $ticket->delete(['id' => $ticket_id], true);
        $template->delete(['id' => $template_id], true);
        $category->delete(['id' => $category_id], true);
        $requester->delete(['id' => $requester_id], true);
    }

    /**
     * Test that using the History button escalation works correctly with mandatory template fields
     * This test reproduces the issue where the History button still triggers the
     * "Mandatory fields are not filled. Please correct: Requester" error.
     */
    public function testHistoryButtonEscalationWithMandatoryTemplateFields()
    {
        $this->initConfig();

        // Create a ticket template with mandatory requester field
        $template = $this->createItem('TicketTemplate', [
            'name' => 'Test template for history button',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $template_id = $template->getID();
        $this->assertGreaterThan(0, $template_id);

        // Add mandatory field (requester) to the template
        $mandatory_field = $this->createItem('TicketTemplateMandatoryField', [
            'tickettemplates_id' => $template_id,
            'num' => 4, // _users_id_requester field number
        ]);
        $mandatory_field_id = $mandatory_field->getID();
        $this->assertGreaterThan(0, $mandatory_field_id);

        // Create a category linked to this template
        $category = $this->createItem('ITILCategory', [
            'name' => 'Test category for history button',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $category_id = $category->getID();
        $this->assertGreaterThan(0, $category_id);

        // Create a requester user
        $requester = $this->createItem('User', [
            'name' => 'requester_history_test',
            'firstname' => 'Requester',
            'entities_id' => 0,
        ]);
        $requester_id = $requester->getID();
        $this->assertGreaterThan(0, $requester_id);

        // Create first escalation group
        $group1 = $this->createItem('Group', [
            'name' => 'First escalation group',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $group1_id = $group1->getID();
        $this->assertGreaterThan(0, $group1_id);

        // Create second escalation group for history
        $group2 = $this->createItem('Group', [
            'name' => 'Second escalation group',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $group2_id = $group2->getID();
        $this->assertGreaterThan(0, $group2_id);

        // Create a ticket with the template and mandatory requester filled
        $ticket = $this->createItem('Ticket', [
            'name' => 'Test ticket for history button',
            'content' => 'Content for history button test',
            'itilcategories_id' => $category_id,
            '_users_id_requester' => [$requester_id],
            'status' => CommonITILObject::INCOMING,
            'entities_id' => 0,
        ]);
        $ticket_id = $ticket->getID();
        $this->assertGreaterThan(0, $ticket_id);

        // Assign first group to the ticket
        $group_ticket = $this->createItem('Group_Ticket', [
            'tickets_id' => $ticket_id,
            'groups_id' => $group1_id,
            'type' => CommonITILActor::ASSIGN,
        ]);
        $group_ticket_id = $group_ticket->getID();
        $this->assertGreaterThan(0, $group_ticket_id);

        // Escalate to second group (simulate the history button click)
        // This should create an escalation history entry
        $group_ticket2 = $this->createItem('Group_Ticket', [
            'tickets_id' => $ticket_id,
            'groups_id' => $group2_id,
            'type' => CommonITILActor::ASSIGN,
        ]);
        $group_ticket2_id = $group_ticket2->getID();
        $this->assertGreaterThan(0, $group_ticket2_id);

        // Now test the history button escalation using climb_group (this reproduces the issue)
        // This simulates exactly what happens when the user clicks the history button
        PluginEscaladeTicket::climb_group($ticket_id, $group1_id, true);

        // Verify that no error occurred and the escalation was successful
        // The ticket should now have group1 assigned again
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        // Should have only one group assigned (the climb_group removes previous and adds new)
        $this->assertGreaterThan(0, count($assigned_groups));

        // Check that group1 is now assigned
        $group1_assigned = $group_ticket->find([
            'tickets_id' => $ticket_id,
            'groups_id' => $group1_id,
            'type' => CommonITILActor::ASSIGN,
        ]);
        $this->assertEquals(1, count($group1_assigned));

        // Verify that the requester is still properly assigned (not lost during escalation)
        $ticket_user = new Ticket_User();
        $requesters_after = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::REQUESTER,
        ]);
        $this->assertEquals(1, count($requesters_after));
        $requester_after = reset($requesters_after);
        $this->assertEquals($requester_id, $requester_after['users_id']);

        // Clean up
        $ticket->delete(['id' => $ticket_id], true);
        $template->delete(['id' => $template_id], true);
        $category->delete(['id' => $category_id], true);
        $requester->delete(['id' => $requester_id], true);
        $group1->delete(['id' => $group1_id], true);
        $group2->delete(['id' => $group2_id], true);
    }

    public function testRuleCreatesTaskWhenCategoryAssigned()
    {
        $this->initConfig();

        // Create a task template that will be added by the rule
        $task_template = $this->createItem('TaskTemplate', [
            'name' => 'Rule task template',
            'content' => '<p>Task created by rule</p>',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $task_template_id = $task_template->getID();

        // Create the rule that appends a task template when category is set on update
        $rule = $this->createItem('Rule', [
            'name' => 'Create task on category assign',
            'sub_type' => 'RuleTicket',
            'match' => 'AND',
            'is_active' => 1,
            'condition' => \RuleCommonITILObject::ONUPDATE,
            'is_recursive' => 1,
            'entities_id' => 0,
        ]);
        $rule_id = $rule->getID();

        $this->createItem('RuleAction', [
            'rules_id' => $rule_id,
            'action_type' => 'append',
            'field' => 'task_template',
            'value' => $task_template_id,
        ]);

        // Create a category that will trigger the rule when assigned
        $category = $this->createItem('ITILCategory', [
            'name' => 'Category triggering task',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $category_id = $category->getID();

        // Ensure the rule triggers only when the ticket category matches the created category
        $this->createItem('RuleCriteria', [
            'rules_id' => $rule_id,
            'criteria' => 'itilcategories_id',
            'condition' => \Rule::PATTERN_IS,
            'pattern' => $category_id,
        ]);

        // Create a ticket without category
        $ticket = $this->createItem('Ticket', [
            'name' => 'Ticket for rule test',
            'content' => 'Content for rule test',
            'entities_id' => 0,
        ]);
        $ticket_id = $ticket->getID();

        $tickettask = new TicketTask();
        $this->assertEquals(0, count($tickettask->find(['tickets_id' => $ticket_id])));

        // Assign the category (update) - rule should fire and create a single task
        $this->updateItem('Ticket', $ticket_id, [
            'id' => $ticket_id,
            'itilcategories_id' => $category_id,
        ]);

        $tasks = $tickettask->find(['tickets_id' => $ticket_id]);
        $this->assertEquals(1, count($tasks));

        // Clean up
        $ticket->delete(['id' => $ticket_id], true);
        $task_template->delete(['id' => $task_template_id], true);
        $category->delete(['id' => $category_id], true);
        $rule->delete(['id' => $rule_id], true);
    }
}

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
use CommonITILValidation;
use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use ITILCategory;
use ITILSolution;
use PluginEscaladeConfig;
use PluginEscaladeTicket;

final class TicketTest extends EscaladeTestCase
{
    public function testCloseClonedTicket()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Test 1: Test with cloneandlink_ticket = 1 and close_linkedtickets = 1
        $this->assertTrue($config->update([
            'cloneandlink_ticket' => 1,
            'close_linkedtickets' => 1,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        // Create the first ticket
        $ticket = new \Ticket();
        $this->assertEquals(0, count($ticket->find(['name' => 'Escalade Clone and Link Test 1'])));

        $t_id = $ticket->add([
            'name' => 'Escalade Clone and Link Test 1',
            'content' => 'Content of test ticket 1',
        ]);
        $this->assertGreaterThan(0, $t_id);

        // Execute cloneAndLink on this ticket
        PluginEscaladeTicket::cloneAndLink($t_id);

        // Verify that the ticket has been cloned
        $this->assertEquals(2, count($ticket->find(['name' => 'Escalade Clone and Link Test 1'])));

        // Verify that the link is of type DUPLICATE_WITH
        $ticket_ticket = new \Ticket_Ticket();
        $links = $ticket_ticket->find([
            'tickets_id_1' => $t_id,
        ]);
        $this->assertCount(1, $links);
        $link = reset($links);
        $this->assertEquals(\Ticket_Ticket::DUPLICATE_WITH, $link['link']);

        // Get the cloned ticket
        $cloned_ticket = new \Ticket();
        $ct = $cloned_ticket->getFromDBByCrit([
            'name' => 'Escalade Clone and Link Test 1',
            'NOT' => ['id' => $t_id],
        ]);
        $this->assertTrue($ct);
        $cloned_id = $cloned_ticket->fields['id'];

        // Change the parent ticket status to SOLVED
        $parent_ticket = new \Ticket();
        $parent_ticket->getFromDB($t_id);
        $parent_ticket->update([
            'id' => $t_id,
            'status' => CommonITILObject::SOLVED,
        ]);

        // Verify that the cloned ticket is also solved (automatically by GLPI core)
        $cloned_ticket->getFromDB($cloned_id);
        $this->assertEquals(CommonITILObject::SOLVED, $cloned_ticket->fields['status']);

        // Test2: Test with cloneandlink_ticket = 1 and close_linkedtickets = 0
        $this->assertTrue($config->update([
            'cloneandlink_ticket' => 1,
            'close_linkedtickets' => 0,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        // Create the second ticket
        $ticket = new \Ticket();
        $this->assertEquals(0, count($ticket->find(['name' => 'Escalade Clone and Link Test 2'])));
        $t_id = $ticket->add([
            'name' => 'Escalade Clone and Link Test 2',
            'content' => 'Content of test ticket 2',
        ]);
        $this->assertGreaterThan(0, $t_id);

        // Execute cloneAndLink on this ticket
        PluginEscaladeTicket::cloneAndLink($t_id);

        // Verify that the ticket has been cloned
        $this->assertEquals(2, count($ticket->find(['name' => 'Escalade Clone and Link Test 2'])));

        // Verify that the link is of type LINK_TO
        $ticket_ticket = new \Ticket_Ticket();
        $links = $ticket_ticket->find([
            'tickets_id_1' => $t_id,
        ]);
        $this->assertCount(1, $links);
        $link = reset($links);
        $this->assertEquals(\Ticket_Ticket::LINK_TO, $link['link']);

        // Get the cloned ticket
        $cloned_ticket = new \Ticket();
        $ct = $cloned_ticket->getFromDBByCrit([
            'name' => 'Escalade Clone and Link Test 2',
            'NOT' => ['id' => $t_id],
        ]);
        $this->assertTrue($ct);
        $cloned_id = $cloned_ticket->fields['id'];

        // Change the parent ticket status to SOLVED
        $parent_ticket = new \Ticket();
        $parent_ticket->getFromDB($t_id);
        $parent_ticket->update([
            'id' => $t_id,
            'status' => CommonITILObject::SOLVED,
        ]);

        // Verify that the cloned ticket is not solved
        $cloned_ticket->getFromDB($cloned_id);
        $this->assertNotEquals(CommonITILObject::SOLVED, $cloned_ticket->fields['status']);
    }

    public function testEscalationWithMandatoryFields()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        PluginEscaladeConfig::loadInSession();

        // Create ticket template without mandatory fields
        $template = new \TicketTemplate();
        $template_id = $template->add([
            'name' => 'Test template for escalation',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $template_id);

        // Create a category linked to this template
        $category = new \ITILCategory();
        $category_id = $category->add([
            'name' => 'Test category with template',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $category_id);

        // Create first group for assignment
        $group1 = new \Group();
        $group1_id = $group1->add([
            'name' => 'Group for escalation test 1',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $this->assertGreaterThan(0, $group1_id);

        // Create second group for escalation
        $group2 = new \Group();
        $group2_id = $group2->add([
            'name' => 'Group for escalation test 2',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $this->assertGreaterThan(0, $group2_id);

        // Create a ticket with the template and first group assigned
        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test ticket for escalation',
            'content' => 'Content for test ticket',
            'itilcategories_id' => $category_id,
            '_groups_id_assign' => [$group1_id],
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Add a mandatory field to the template
        $mandatory_field = new \TicketTemplateMandatoryField();
        $mandatory_field_id = $mandatory_field->add([
            'tickettemplates_id' => $template_id,
            'num' => 10, // impact field
        ]);
        $this->assertGreaterThan(0, $mandatory_field_id);

        // Now try to escalate the ticket without filling the mandatory field
        // First, create a properly configured ticket instance
        $ticket_instance = new \Ticket();
        $this->assertTrue($ticket_instance->getFromDB($ticket_id));

        // Prepare the input with missing mandatory field
        $input_without_mandatory = [
            'id' => $ticket_id,
            'groups_id' => $group2_id,
            'actortype' => CommonITILActor::ASSIGN,
        ];

        // Use a mock to test the behavior
        $mock_ticket = $this->getMockBuilder(\Ticket::class)
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
        $update_success = $ticket->update([
            'id' => $ticket_id,
            'impact' => 3, // Fill the mandatory field
        ]);
        $this->assertTrue($update_success);

        // Refresh the ticket data
        $ticket_instance->getFromDB($ticket_id);

        // Create a new mock that will pass the prepareInputForUpdate check
        $mock_ticket_success = $this->getMockBuilder(\Ticket::class)
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
        $group_ticket = new \Group_Ticket();
        $group_ticket->add([
            'tickets_id' => $ticket_id,
            'groups_id' => $group2_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        // Verify that the group was added
        $assigned_groups = $group_ticket->find([
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
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        PluginEscaladeConfig::loadInSession();

        // Create a group
        $group_observer = new \Group();
        $group_observer_id = $group_observer->add([
            'name' => 'Group Observer',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $group_observer_id);

        $group_tech = new \Group();
        $group_tech_id = $group_tech->add([
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $group_tech_id);

        // Get the tech user
        $user_tech = new \User();
        $user_tech->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user_tech->getID());

        // Create a rule to assign the group observer if the group tech or tech user is assigned
        $rule = new \Rule();
        $rule_id = $rule->add([
            'name' => 'Add RuleTicket',
            'sub_type' => 'RuleTicket',
            'match' => 'OR',
            'is_active' => 1,
            'condition' => 2,
        ]);
        $this->assertGreaterThan(0, $rule_id);

        $rule_action = new \RuleAction();
        $rule_action->add([
            'rules_id' => $rule_id,
            'action_type' => 'assign',
            'field' => '_groups_id_observer',
            'value' => $group_observer_id,
        ]);

        $rule_criteria = new \RuleCriteria();
        $rule_criteria->add([
            'rules_id' => $rule_id,
            'criteria' => '_groups_id_assign',
            'condition' => 0,
            'pattern' => $group_tech_id,
        ]);

        $rule_criteria2 = new \RuleCriteria();
        $rule_criteria2->add([
            'rules_id' => $rule_id,
            'criteria' => '_users_id_assign',
            'condition' => 0,
            'pattern' => $user_tech->getID(),
        ]);

        // Test the rule ticket during the escalation
        foreach ($this->escalateTicketMethods(['escalateWithTimelineButton', 'escalateWithHistoryButton', 'escalateWithAssignMySelfButton']) as $data) {
            $ticket = new \Ticket();
            $ticket_id = $ticket->add([
                'name' => 'Test ticket for escalation',
                'content' => 'Content for test ticket',
            ]);
            $this->assertGreaterThan(0, $ticket_id);
            $ticket->getFromDB($ticket_id);

            $group_ticket = new \Group_Ticket();
            $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_observer_id, 'type' => \CommonITILActor::OBSERVER])));
            if ($data['itemtype'] === \Group::class) {
                $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_tech_id, 'type' => \CommonITILActor::ASSIGN])));
                $group_tech_obj = new \Group();
                $group_tech_obj->getFromDB($group_tech_id);
                $this->{$data['method']}($ticket, $group_tech_obj);
                $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_tech_id, 'type' => \CommonITILActor::ASSIGN])));
            } else {
                $user_ticket = new \Ticket_User();
                $this->assertEquals(0, count($user_ticket->find(['tickets_id' => $ticket_id, 'users_id' => $user_tech->getID(), 'type' => \CommonITILActor::ASSIGN])));
                $this->{$data['method']}($ticket, $user_tech);
                $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'users_id' => $user_tech->getID(), 'type' => \CommonITILActor::ASSIGN])));
            }
            $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group_observer_id, 'type' => \CommonITILActor::OBSERVER])));
        }
    }

    public function testTicketUpdateDoesNotChangeITILCategoryAssignedGroup()
    {
        $this->login(TU_USER, TU_PASS);

        $entity = new \Entity();
        $entity->update([
            'id' => 0,
            'auto_assign_mode' => \Entity::AUTO_ASSIGN_CATEGORY_HARDWARE,
        ]);

        $config = new PluginEscaladeConfig();
        $config->update([
            'id' => 1,
            'reassign_group_from_cat' => 1,
            'remove_group' => 1,
        ]);

        PluginEscaladeConfig::loadInSession();

        $group1 = new \Group();
        $group1_id = $group1->add([
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $group1_id);

        $group2 = new \Group();
        $group2_id = $group2->add([
            'name' => 'Group tech 2',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $group2_id);

        $itil_cat = new \ITILCategory();
        $itil_cat_id = $itil_cat->add([
            'name' => 'Cat1',
            'groups_id' => $group1_id,
        ]);
        $this->assertGreaterThan(0, $itil_cat_id);

        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test ticket for escalation',
            'content' => 'Content for test ticket',
            'itilcategories_id' => $itil_cat_id,
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        $group_ticket = new \Group_Ticket();
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id, 'type' => \CommonITILActor::ASSIGN])));

        $ticket->update([
            'id' => $ticket_id,
            '_actors' => [
                'assign' => [
                    [
                        'itemtype' => \Group::class,
                        'items_id' => $group1_id,
                    ],
                    [
                        'itemtype' => \Group::class,
                        'items_id' => $group2_id,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id, 'type' => \CommonITILActor::ASSIGN])));

        $ticket->update([
            'id' => $ticket_id,
            'status' => \CommonITILObject::WAITING,
        ]);

        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id, 'type' => \CommonITILActor::ASSIGN])));
    }

    public function testStatusTicketOption()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);
        $this->assertTrue($config->update([
            'ticket_last_status' => \CommonITILObject::INCOMING,
            'remove_tech' => 0,
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $user1 = new \User();
        $user1->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user2->getID());

        $group_tech = new \Group();
        $group_tech_id = $group_tech->add([
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $group_tech_id);

        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test ticket',
            'content' => 'Content',
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        $ticket->getFromDB($ticket_id);
        $this->assertEquals(\CommonITILObject::INCOMING, $ticket->fields['status']);

        $group_ticket = new \Group_Ticket();
        $user_ticket = new \Ticket_User();
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(0, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));

        $this->assertTrue(
            $ticket->update([
                'id' => $ticket_id,
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User',
                        ],
                    ],
                ],
            ]),
        );
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(\CommonITILObject::ASSIGNED, $ticket->fields['status']);

        $this->assertTrue(
            $ticket->update([
                'id' => $ticket_id,
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
            ]),
        );
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN, 'groups_id' => $group_tech_id])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(\CommonITILObject::INCOMING, $ticket->fields['status']);

        $this->assertTrue(
            $ticket->update([
                'id' => $ticket_id,
                '_itil_assign' => [
                    '_type' => "user",
                    'users_id' => $user2->getID(),
                    'use_notification' => 1,
                ],
            ]),
        );
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN, 'groups_id' => $group_tech_id])));
        $this->assertEquals(2, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket_id, 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user2->getID()])));
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(\CommonITILObject::ASSIGNED, $ticket->fields['status']);
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
        $ticket_user = new \Ticket_User();
        $ticket_group = new \Group_Ticket();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $this->login(TU_USER, TU_PASS);

        $entity = new \Entity();
        $entity->update([
            'id' => 0,
            'auto_assign_mode' => 2,
        ]);

        $group1 = new \Group();
        $group1_id = $group1->add([
            'name' => 'GLPI Group',
        ]);
        $this->assertGreaterThan(0, $group1_id);

        $group2 = new \Group();
        $group2_id = $group2->add([
            'name' => 'TECH Group',
        ]);
        $this->assertGreaterThan(0, $group2_id);

        $group_user1 = new \Group_User();
        $group_user1->add([
            'users_id' => $user1->getID(),
            'groups_id' => $group1_id,
        ]);

        $group_user2 = new \Group_User();
        $group_user2->add([
            'users_id' => $user2->getID(),
            'groups_id' => $group2_id,
        ]);

        $itil_category1 = new \ITILCategory();
        $itil_category1_id = $itil_category1->add([
            'name' => 'Cat1',
            'users_id' => $user1->getID(),
            'groups_id' => $group1_id,
        ]);
        $this->assertGreaterThan(0, $itil_category1_id);

        $itil_category2 = new \ITILCategory();
        $itil_category2_id = $itil_category2->add([
            'name' => 'Cat2',
            'users_id' => $user2->getID(),
            'groups_id' => $group2_id,
        ]);
        $this->assertGreaterThan(0, $itil_category2_id);

        foreach ($this->testAssignGroupToTicketWithCategoryProvider() as $provider) {
            // Update escalade config
            $config = new PluginEscaladeConfig();
            $config->update(array_merge(['id' => 1], $provider['conf']));

            PluginEscaladeConfig::loadInSession();

            $ticket = new \Ticket();
            $ticket_id = $ticket->add([
                'name' => 'Assign Cat Escalation Test',
                'content' => 'content',
                'itilcategories_id' => $itil_category1_id,
            ]);
            $this->assertGreaterThan(0, $ticket_id);

            $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket_id])));
            $this->assertEquals(1, count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user1->getID()])));
            $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user2->getID()])));
            $this->assertEquals(1, count($ticket_group->find(['tickets_id' => $ticket_id])));
            $this->assertEquals(1, count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id])));
            $this->assertEquals(0, count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id])));

            $ticket->update([
                'id' => $ticket_id,
                'itilcategories_id' => $itil_category2_id,
            ]);

            $this->assertEquals($provider['expected']['user_1_is_assign'], count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user1->getID()])));
            $this->assertEquals($provider['expected']['user_2_is_assign'], count($ticket_user->find(['tickets_id' => $ticket_id, 'users_id' => $user2->getID()])));
            $this->assertEquals($provider['expected']['group_1_is_assign'], count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group1_id])));
            $this->assertEquals($provider['expected']['group_2_is_assign'], count($ticket_group->find(['tickets_id' => $ticket_id, 'groups_id' => $group2_id])));
        }
    }

    /**
     * Test that adding a solution to a ticket with mandatory template fields works correctly
     * This test reproduces the issue where the Escalade plugin interfered with template validation
     * when adding solutions to tickets with mandatory requester fields.
     */
    public function testAddSolutionWithMandatoryTemplateFields()
    {
        $this->login();

        // Load Escalade plugin configuration
        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);
        PluginEscaladeConfig::loadInSession();

        // Create a ticket template with mandatory requester field
        $template = new \TicketTemplate();
        $template_id = $template->add([
            'name' => 'Template with mandatory requester',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $template_id);

        // Add mandatory field (requester) to the template
        $mandatory_field = new \TicketTemplateMandatoryField();
        $mandatory_field_id = $mandatory_field->add([
            'tickettemplates_id' => $template_id,
            'num' => 4, // _users_id_requester field number
        ]);
        $this->assertGreaterThan(0, $mandatory_field_id);

        // Create a category linked to this template
        $category = new \ITILCategory();
        $category_id = $category->add([
            'name' => 'Category with mandatory template',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $category_id);

        // Create a user to be the requester
        $user = new \User();
        $user_id = $user->add([
            'name' => 'test_requester',
            'realname' => 'Test Requester',
            'firstname' => 'User',
        ]);
        $this->assertGreaterThan(0, $user_id);

        // Create a ticket with the template and mandatory requester filled
        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Ticket for solution test',
            'content' => 'Content for solution test',
            'itilcategories_id' => $category_id,
            '_users_id_requester' => [$user_id],
            'status' => CommonITILObject::ASSIGNED,
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Verify the ticket was created with the requester
        $ticket->getFromDB($ticket_id);
        $this->assertEquals(CommonITILObject::ASSIGNED, $ticket->fields['status']);

        // Verify that the requester is properly assigned
        $ticket_user = new \Ticket_User();
        $requesters = $ticket_user->find([
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::REQUESTER,
        ]);
        $this->assertEquals(1, count($requesters));
        $requester = reset($requesters);
        $this->assertEquals($user_id, $requester['users_id']);

        // Create a solution type
        $solution_type = new \SolutionType();
        $solution_type_id = $solution_type->add([
            'name' => 'Test solution type',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $solution_type_id);

        // Now try to add a solution to the ticket - this should work without validation errors
        // In GLPI, we need to add the solution using ITILSolution, then update the ticket status
        $solution = new ITILSolution();
        $solution_id = $solution->add([
            'itemtype' => 'Ticket',
            'items_id' => $ticket_id,
            'solutiontypes_id' => $solution_type_id,
            'content' => 'This is the solution to the problem.',
        ]);
        $this->assertGreaterThan(0, $solution_id);

        // Update ticket status to solved - this is where the template validation could fail
        $success = $ticket->update([
            'id' => $ticket_id,
            'status' => CommonITILObject::SOLVED,
        ]);

        // The update should succeed
        $this->assertTrue($success);

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
        $this->login();

        // Load Escalade plugin configuration
        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);
        PluginEscaladeConfig::loadInSession();

        // Create a ticket template with mandatory requester field
        $template = new \TicketTemplate();
        $template_id = $template->add([
            'name' => 'Test template for assign me',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $template_id);

        // Add mandatory field (requester) to the template
        $mandatory_field = new \TicketTemplateMandatoryField();
        $mandatory_field_id = $mandatory_field->add([
            'tickettemplates_id' => $template_id,
            'num' => 4, // _users_id_requester field number
        ]);
        $this->assertGreaterThan(0, $mandatory_field_id);

        // Create a category linked to this template
        $category = new \ITILCategory();
        $category_id = $category->add([
            'name' => 'Test category for assign me',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $category_id);

        // Create a requester user
        $requester = new \User();
        $requester_id = $requester->add([
            'name' => 'requester_test',
            'firstname' => 'Requester',
            'lastname' => 'Test',
        ]);
        $this->assertGreaterThan(0, $requester_id);

        // Create a ticket with the template and mandatory requester filled
        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test ticket for assign me',
            'content' => 'Content for test ticket',
            'itilcategories_id' => $category_id,
            '_users_id_requester' => [$requester_id],
            'status' => CommonITILObject::INCOMING,
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Verify the ticket was created with the requester
        $ticket_user = new \Ticket_User();
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
        $ticket->getFromDB($ticket_id); // Refresh the ticket data
        $input = array_merge(\Toolbox::addslashes_deep($ticket->fields), [
            'id' => $ticket_id,
            '_itil_assign' => [
                '_type' => "user",
                'users_id' => $current_user_id,
                'use_notification' => 1,
            ],
        ]);

        // This should work without template validation errors
        $result = $ticket->update($input);
        $this->assertTrue($result);

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
        $result2 = $ticket->update($input);
        $this->assertTrue($result2);
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
        $this->login();

        // Load Escalade plugin configuration
        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);
        PluginEscaladeConfig::loadInSession();

        // Create a ticket template with mandatory requester field
        $template = new \TicketTemplate();
        $template_id = $template->add([
            'name' => 'Test template for history button',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $template_id);

        // Add mandatory field (requester) to the template
        $mandatory_field = new \TicketTemplateMandatoryField();
        $mandatory_field_id = $mandatory_field->add([
            'tickettemplates_id' => $template_id,
            'num' => 4, // _users_id_requester field number
        ]);
        $this->assertGreaterThan(0, $mandatory_field_id);

        // Create a category linked to this template
        $category = new \ITILCategory();
        $category_id = $category->add([
            'name' => 'Test category for history button',
            'tickettemplates_id_incident' => $template_id,
            'is_incident' => 1,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->assertGreaterThan(0, $category_id);

        // Create a requester user
        $requester = new \User();
        $requester_id = $requester->add([
            'name' => 'requester_history_test',
            'firstname' => 'Requester',
            'lastname' => 'HistoryTest',
        ]);
        $this->assertGreaterThan(0, $requester_id);

        // Create first escalation group
        $group1 = new \Group();
        $group1_id = $group1->add([
            'name' => 'First escalation group',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $this->assertGreaterThan(0, $group1_id);

        // Create second escalation group for history
        $group2 = new \Group();
        $group2_id = $group2->add([
            'name' => 'Second escalation group',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
        $this->assertGreaterThan(0, $group2_id);

        // Create a ticket with the template and mandatory requester filled
        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Test ticket for history button',
            'content' => 'Content for history button test',
            'itilcategories_id' => $category_id,
            '_users_id_requester' => [$requester_id],
            'status' => CommonITILObject::INCOMING,
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        // Assign first group to the ticket
        $group_ticket = new \Group_Ticket();
        $group_ticket_id = $group_ticket->add([
            'tickets_id' => $ticket_id,
            'groups_id' => $group1_id,
            'type' => CommonITILActor::ASSIGN,
        ]);
        $this->assertGreaterThan(0, $group_ticket_id);

        // Escalate to second group (simulate the history button click)
        // This should create an escalation history entry
        $group_ticket2 = new \Group_Ticket();
        $group_ticket2_id = $group_ticket2->add([
            'tickets_id' => $ticket_id,
            'groups_id' => $group2_id,
            'type' => CommonITILActor::ASSIGN,
        ]);
        $this->assertGreaterThan(0, $group_ticket2_id);

        // Now test the history button escalation using climb_group (this reproduces the issue)
        // This simulates exactly what happens when the user clicks the history button
        $result = PluginEscaladeTicket::climb_group($ticket_id, $group1_id, true);

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
        $ticket_user = new \Ticket_User();
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
}

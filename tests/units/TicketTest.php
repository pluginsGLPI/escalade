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
use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use ITILCategory;
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
        $this->assertTrue($config->update([
            'close_linkedtickets' => 1
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $ticket = new \Ticket();
        $this->assertEquals(0, count($ticket->find(['name' => 'Escalade Close cloned ticket test'])));

        $t_id = $ticket->add([
            'name' => 'Escalade Close cloned ticket test',
            'content' => 'Content ticket 1 test',
        ]);

        PluginEscaladeTicket::cloneAndLink($t_id);

        $ticket = new \Ticket();

        // Check if ticket cloned
        $this->assertEquals(2, count($ticket->find(['name' => 'Escalade Close cloned ticket test'])));

        // Update ticket status
        $ticket->update([
            'id' => $t_id,
            'status' => CommonITILObject::SOLVED
        ]);

        $ticket = new \Ticket();
        $ticket_cloned = $ticket->getFromDBByCrit([
            'name' => 'Escalade Close cloned ticket test',
            'NOT' => ['id' => $t_id]
        ]);
        $this->assertTrue($ticket_cloned);

        //Check if cloned ticket is also solved
        $this->assertEquals(CommonITILObject::SOLVED, $ticket->fields['status']);

        // Disable close linked tickets option
        $this->assertTrue($config->update([
            'cloneandlink'        => 1,
            'close_linkedtickets' => 0
        ] + $conf));

        PluginEscaladeConfig::loadInSession();

        $ticket = new \Ticket();
        $this->assertEquals(0, count($ticket->find(['name' => 'Escalade Close cloned ticket 2 test'])));

        $t_id = $ticket->add([
            'name' => 'Escalade Close cloned ticket 2 test',
            'content' => 'Content ticket 2 test',
        ]);

        PluginEscaladeTicket::cloneAndLink($t_id);

        $ticket = new \Ticket();

        // Check if ticket cloned
        $this->assertEquals(2, count($ticket->find(['name' => 'Escalade Close cloned ticket 2 test'])));

        // Update ticket status
        $ticket->update([
            'id' => $t_id,
            'status' => CommonITILObject::SOLVED
        ]);

        $ticket = new \Ticket();
        $ticket_cloned = $ticket->getFromDBByCrit([
            'name' => 'Escalade Close cloned ticket 2 test',
            'NOT' => ['id' => $t_id]
        ]);
        $this->assertTrue($ticket_cloned);

        //Check if cloned ticket is NOT solved
        $this->assertNotEquals(CommonITILObject::SOLVED, $ticket->fields['status']);
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
            'actortype' => CommonITILActor::ASSIGN
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
                        'items_id' => $group2_id
                    ]
                ]
            ]
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
            "The _disablenotif flag should be set on successful escalation"
        );

        // Add the group to actually test the escalation
        $group_ticket = new \Group_Ticket();
        $group_ticket->add([
            'tickets_id' => $ticket_id,
            'groups_id' => $group2_id,
            'type' => CommonITILActor::ASSIGN
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

    public function testEscalateButtonShouldTriggerGroupEscalationAndExecuteRuleOnTicket()
    {
        $this->login();

        $config = new PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        PluginEscaladeConfig::loadInSession();

        $group_observer_id = $this->createItem(\Group::class, [
            'name' => 'Group Observer',
            'entities_id' => 0,
            'is_recursive' => 1,
        ])->getID();

        $group_tech_id = $this->createItem(\Group::class, [
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ])->getID();

        $rule_id = $this->createItem(\Rule::class, [
            'name' => 'Add RuleTicket',
            'sub_type' => 'RuleTicket',
            'match' => 'AND',
            'is_active' => 1,
            'condition' => 2,
        ])->getID();

        $this->createItem(\RuleAction::class, [
            'rules_id' => $rule_id,
            'action_type' => 'assign',
            'field' => '_groups_id_observer',
            'value' => $group_observer_id,
        ]);

        $this->createItem(\RuleCriteria::class, [
            'rules_id' => $rule_id,
            'criteria' => '_groups_id_assign',
            'condition' => 0,
            'pattern' => $group_tech_id,
        ]);

        $ticket = $this->createItem(\Ticket::class, [
            'name' => 'Test ticket for escalation',
            'content' => 'Content for test ticket',
        ]);

        $group_ticket = new \Group_Ticket();
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group_tech_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group_observer_id, 'type' => \CommonITILActor::OBSERVER])));

        $ticket_update = $ticket->update([
            'id' => $ticket->getID(),
            '_groups_id_assign' => [$group_tech_id],
        ]);
        $this->assertTrue($ticket_update);

        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group_tech_id, 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group_observer_id, 'type' => \CommonITILActor::OBSERVER])));
    }

    public function testTicketUpdateDoesNotChangeITILCategoryAssignedGroup()
    {
        $this->login(TU_USER, TU_PASS);

        $this->updateItem(
            \Entity::class,
            0,
            [
                'auto_assign_mode' => \Entity::AUTO_ASSIGN_CATEGORY_HARDWARE,
            ],
        );

        $this->updateItem(
            PluginEscaladeConfig::class,
            1,
            [
                "reassign_group_from_cat" => 1,
                'remove_group' => 1,
            ]
        );

        PluginEscaladeConfig::loadInSession();

        $group1 = $this->createItem(\Group::class, [
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);

        $group2 = $this->createItem(\Group::class, [
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);

        $itil_cat = $this->createItem(
            \ITILCategory::class,
            [
                'name' => 'Cat1',
                'groups_id' => $group1->getID(),
            ],
        );

        $ticket = $this->createItem(\Ticket::class, [
            'name' => 'Test ticket for escalation',
            'content' => 'Content for test ticket',
            'itilcategories_id' => $itil_cat->getID(),
        ]);

        $group_ticket = new \Group_Ticket();
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group1->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group2->getID(), 'type' => \CommonITILActor::ASSIGN])));

        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                '_actors' => [
                    'assign' => [
                        [
                            'itemtype' => \Group::class,
                            'items_id' => $group1->getID(),
                        ],
                        [
                            'itemtype' => \Group::class,
                            'items_id' => $group2->getID(),
                        ],
                    ],
                ],
            ],
        );

        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group1->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group2->getID(), 'type' => \CommonITILActor::ASSIGN])));


        $this->updateItem(
            \Ticket::class,
            $ticket->getID(),
            [
                'status' => \CommonITILObject::WAITING,
            ],
        );

        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group1->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'groups_id' => $group2->getID(), 'type' => \CommonITILActor::ASSIGN])));
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

        $group_tech = $this->createItem(\Group::class, [
            'name' => 'Group tech',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);

        $ticket = $this->createItem(\Ticket::class, [
            'name' => 'Test ticket',
            'content' => 'Content',
        ]);

        $this->assertEquals(\CommonITILObject::INCOMING, $ticket->fields['status']);

        $group_ticket = new \Group_Ticket();
        $user_ticket = new \Ticket_User();
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(0, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));

        $this->assertTrue(
            $ticket->update([
                'id' => $ticket->getID(),
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User'
                        ],
                    ],
                ],
            ])
        );
        $this->assertEquals(0, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $this->assertEquals(\CommonITILObject::ASSIGNED, $ticket->fields['status']);

        $this->assertTrue(
            $ticket->update([
                'id' => $ticket->getID(),
                '_actors' => [
                    'assign' => [
                        [
                            'items_id' => $user1->getID(),
                            'itemtype' => 'User'
                        ],
                        [
                            'items_id' => $group_tech->getID(),
                            'itemtype' => 'Group'
                        ],
                    ],
                ],
            ])
        );
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'groups_id' => $group_tech->getID()])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $this->assertEquals(\CommonITILObject::INCOMING, $ticket->fields['status']);

        $this->assertTrue(
            $ticket->update([
                'id' => $ticket->getID(),
                '_itil_assign' => [
                    '_type' => "user",
                    'users_id' => $user2->getID(),
                    'use_notification' => 1,
                ]
            ])
        );
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($group_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'groups_id' => $group_tech->getID()])));
        $this->assertEquals(2, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user1->getID()])));
        $this->assertEquals(1, count($user_ticket->find(['tickets_id' => $ticket->getID(), 'type' => \CommonITILActor::ASSIGN, 'users_id' => $user2->getID()])));
        $this->assertEquals(\CommonITILObject::ASSIGNED, $ticket->fields['status']);
    }

    private function testAssignGroupToTicketWithCategoryProvider()
    {
        yield [
            'conf' => [
                'remove_tech'  => 0,
                'remove_group' => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat' => 0,
            ],
            'expected' => [
                'users' => 1,
                'groups' => 1,
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 0,
                'remove_group' => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat' => 1,
            ],
            'expected' => [
                'users' => 2,
                'groups' => 1,
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 0,
                'remove_group' => 0,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat' => 0,
            ],
            'expected' => [
                'users' => 1,
                'groups' => 2,
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 0,
                'remove_group' => 1,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat' => 1,
            ],
            'expected' => [
                'users' => 2,
                'groups' => 1,
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 0,
                'remove_group' => 1,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat' => 1,
            ],
            'expected' => [
                'users' => 2,
                'groups' => 1,
                'user_1_is_assign' => 1,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 0,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 1,
                'remove_group' => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat' => 0,
            ],
            'expected' => [
                'users' => 0,
                'groups' => 1,
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 1,
                'remove_group' => 0,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat' => 1,
            ],
            'expected' => [
                'users' => 1,
                'groups' => 1,
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 1,
                'remove_group' => 0,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat' => 0,
            ],
            'expected' => [
                'users' => 0,
                'groups' => 2,
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 1,
                'remove_group' => 1,
                'reassign_group_from_cat' => 0,
                'reassign_tech_from_cat' => 1,
            ],
            'expected' => [
                'users' => 1,
                'groups' => 1,
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 1,
                'group_1_is_assign' => 1,
                'group_2_is_assign' => 0,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 1,
                'remove_group' => 1,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat' => 0,
            ],
            'expected' => [
                'users' => 0,
                'groups' => 1,
                'user_1_is_assign' => 0,
                'user_2_is_assign' => 0,
                'group_1_is_assign' => 0,
                'group_2_is_assign' => 1,
            ],
        ];

        yield [
            'conf' => [
                'remove_tech'  => 1,
                'remove_group' => 1,
                'reassign_group_from_cat' => 1,
                'reassign_tech_from_cat' => 1,
            ],
            'expected' => [
                'users' => 1,
                'groups' => 1,
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
        $ticket = new \Ticket();
        $itil_category = new \ITILCategory();

        $user1 = new \User();
        $user1->getFromDBbyName('glpi');
        $this->assertGreaterThan(0, $user1->getID());

        $group1 = new \Group();
        $group1_id = $group1->add(['name' => 'GLPI Group']);
        $this->assertGreaterThan(0, $group1_id);

        $user_group1 = new \Group_User();
        $user_group1->add([
            'users_id' => $user1->getID(),
            'groups_id' => $group1->getID()
        ]);
        $this->assertGreaterThan(0, $user_group1->getID());

        $entity = new \Entity();
        $entity->getFromDB(0);
        $entity->update([
            "id" => 0,
            "auto_assign_mode" => 2,
        ]);

        $itil_category1_id = $itil_category->add([
            'name' => 'Cat1',
            'users_id' => $user1->getID(),
            'groups_id' => $group1->getID(),
        ]);
        $this->assertGreaterThan(0, $itil_category1_id);

        $user2 = new \User();
        $user2->getFromDBbyName('tech');
        $this->assertGreaterThan(0, $user2->getID());

        $group2 = new \Group();
        $group2_id = $group2->add(['name' => 'TECH Group']);
        $this->assertGreaterThan(0, $group2_id);

        $user_group2 = new \Group_User();
        $user_group2->add([
            'users_id' => $user2->getID(),
            'groups_id' => $group2->getID()
        ]);
        $this->assertGreaterThan(0, $user_group2->getID());

        $itil_category2_id = $itil_category->add([
            'name' => 'Cat2',
            'users_id' => $user2->getID(),
            'groups_id' => $group2->getID(),
        ]);
        $this->assertGreaterThan(0, $itil_category2_id);

        foreach ($this->testAssignGroupToTicketWithCategoryProvider() as $provider) {
            $this->login();

            $config = new PluginEscaladeConfig();
            $conf = $config->find();
            $conf = reset($conf);
            $config->getFromDB($conf['id']);
            $this->assertGreaterThan(0, $conf['id']);
            // Update escalade config
            $conf = array_merge($conf, $provider['conf']);
            $this->assertTrue($config->update($conf));

            PluginEscaladeConfig::loadInSession();

            $t_id = $ticket->add([
                'name' => 'Assign Cat Escalation Test',
                'content' => 'content',
                'itilcategories_id' => $itil_category1_id,
            ]);
            $this->assertGreaterThan(0, $t_id);
            $count_user1_assign_add = $provider['conf']['remove_tech'] === 0 ? 1 : 0;
            $this->assertEquals($count_user1_assign_add, count($ticket_user->find(['tickets_id' => $t_id])));
            $this->assertEquals($count_user1_assign_add, count($ticket_user->find(['tickets_id' => $t_id, 'users_id' => $user1->getID()])));
            $this->assertEquals(0, count($ticket_user->find(['tickets_id' => $t_id, 'users_id' => $user2->getID()])));
            $this->assertEquals(1, count($ticket_group->find(['tickets_id' => $t_id])));
            $this->assertEquals(1, count($ticket_group->find(['tickets_id' => $t_id, 'groups_id' => $group1->getID()])));
            $this->assertEquals(0, count($ticket_group->find(['tickets_id' => $t_id, 'groups_id' => $group2->getID()])));

            $ticket->getFromDB($t_id);

            $success = $ticket->update([
                'id' => $t_id,
                'itilcategories_id' => $itil_category2_id,
            ]);
            $this->assertTrue($success);

            $this->assertEquals($provider['expected']['users'], count($ticket_user->find(['tickets_id' => $t_id])));
            $this->assertEquals($provider['expected']['user_1_is_assign'], count($ticket_user->find(['tickets_id' => $t_id, 'users_id' => $user1->getID()])));
            $this->assertEquals($provider['expected']['user_2_is_assign'], count($ticket_user->find(['tickets_id' => $t_id, 'users_id' => $user2->getID()])));
            $this->assertEquals($provider['expected']['groups'], count($ticket_group->find(['tickets_id' => $t_id])));
            $this->assertEquals($provider['expected']['group_1_is_assign'], count($ticket_group->find(['tickets_id' => $t_id, 'groups_id' => $group1->getID()])));
            $this->assertEquals($provider['expected']['group_2_is_assign'], count($ticket_group->find(['tickets_id' => $t_id, 'groups_id' => $group2->getID()])));
        }
    }
}

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
}

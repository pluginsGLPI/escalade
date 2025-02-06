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

        //Check if cloned ticket is also solved
        $this->assertNotEquals(CommonITILObject::SOLVED, $ticket->fields['status']);
    }
}

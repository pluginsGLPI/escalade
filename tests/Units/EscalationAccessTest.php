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
use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use Group_Ticket;
use PluginEscaladeTicket;
use ProfileRight;
use Ticket;

final class EscalationAccessTest extends EscaladeTestCase
{
    private function setTechnicianTicketRight(int $right): void
    {
        ProfileRight::updateProfileRights(
            getItemByTypeName('Profile', 'Technician', true),
            ['ticket' => $right],
        );
    }

    private function loadTicket(int $tickets_id): Ticket
    {
        $ticket = new Ticket();
        $ticket->getFromDB($tickets_id);
        return $ticket;
    }

    public function testAssignOnlyUserCanReachEscalationRoutes(): void
    {
        $this->initConfig();
        $tickets_id = $this->createItem(Ticket::class, ['name' => 'Escalation access test', 'content' => ''])->getID();

        $this->setTechnicianTicketRight(Ticket::ASSIGN);
        $this->login('tech', 'tech');

        $ticket = $this->loadTicket($tickets_id);
        $this->assertTrue(
            $ticket->canAssign() && $ticket->checkEntity(),
            'A user with only the ASSIGN right must be allowed to reach escalation routes',
        );
    }

    public function testSubmittedTicketDetailsCannotRetargetAnotherTicket(): void
    {
        $this->initConfig();
        $escalated_ticket = $this->createItem(Ticket::class, ['name' => 'Escalated ticket', 'content' => '']);
        $other_ticket = $this->createItem(Ticket::class, ['name' => 'Other ticket', 'content' => '']);
        $group = $this->createGroup('RetargetTestGroup');

        $_POST['comment'] = 'Escalation comment';
        PluginEscaladeTicket::timelineClimbAction(
            $group->getID(),
            $escalated_ticket->getID(),
            [
                'ticket_details' => [
                    'id'   => $other_ticket->getID(),
                    'name' => 'Renamed by the submitted details',
                ],
            ],
        );

        $this->assertEquals('Other ticket', $this->loadTicket($other_ticket->getID())->fields['name']);
        $this->assertEquals(
            'Renamed by the submitted details',
            $this->loadTicket($escalated_ticket->getID())->fields['name'],
        );

        $group_ticket = new Group_Ticket();
        $this->assertTrue($group_ticket->getFromDBByCrit([
            'tickets_id' => $escalated_ticket->getID(),
            'groups_id'  => $group->getID(),
            'type'       => CommonITILActor::ASSIGN,
        ]));

        unset($_POST['comment']);
    }

    public function testAssignOnlyUserCannotApplySubmittedTicketDetails(): void
    {
        $this->initConfig();
        $tickets_id = $this->createItem(Ticket::class, ['name' => 'Escalation access test', 'content' => ''])->getID();

        $this->setTechnicianTicketRight(Ticket::ASSIGN);
        $this->login('tech', 'tech');

        $this->assertFalse(
            $this->loadTicket($tickets_id)->canUpdateItem(),
            'A user with only the ASSIGN right must not have the submitted details applied to the ticket',
        );
    }

    public function testUpdateOnlyUserCannotReachEscalationRoutes(): void
    {
        $this->initConfig();
        $tickets_id = $this->createItem(Ticket::class, ['name' => 'Escalation access test', 'content' => ''])->getID();

        $this->setTechnicianTicketRight(UPDATE);
        $this->login('tech', 'tech');

        $ticket = $this->loadTicket($tickets_id);
        $this->assertFalse(
            (bool) $ticket->canAssign(),
            'A user with only the UPDATE right must not be allowed to reach escalation routes',
        );
    }
}

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

namespace GlpiPlugin\Escalade\Tests;

use Auth;
use PHPUnit\Framework\TestCase;
use Session;

abstract class EscaladeTestCase extends TestCase
{
    public function setUp(): void
    {
        /** @var \DBmysql $DB */
        global $DB;
        $DB->beginTransaction();
        parent::setUp();
    }

    public function tearDown(): void
    {
        global $DB;
        $DB->rollback();
        parent::tearDown();
    }

    protected function login(
        string $user_name = TU_USER,
        string $user_pass = TU_PASS,
        bool $noauto = true,
        bool $expected = true
    ): Auth {
        Session::destroy();
        Session::start();

        $auth = new Auth();
        $this->assertEquals($expected, $auth->login($user_name, $user_pass, $noauto));

        return $auth;
    }

    protected function logOut()
    {
        $ctime = $_SESSION['glpi_currenttime'];
        Session::destroy();
        $_SESSION['glpi_currenttime'] = $ctime;
    }

    /**
     * Get the methods for simulating different ways of escalating a ticket.
     *
     * @param array $methods Contains the names of the different methods that must be used to simulate an escalation.
     * eg. ['escalateWithTimelineButton', 'escalateWithHistoryButton'] to simulate the escalation with the timeline and history buttons.
     *
     * @return array
     */
    public static function escalateTicketMethods(array $methods = []): array
    {
        $escalate_methods = [
            [
                'method' => 'escalateWithTimelineButton',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithHistoryButton',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithSolvedTicket',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithRejectSolutionTicket',
                'itemtype' => \Group::class,
            ],
            [
                'method' => 'escalateWithAssignMySelfButton',
                'itemtype' => \User::class,
            ],
        ];

        return array_filter($escalate_methods, function ($escalate_method) use ($methods) {
            return in_array($escalate_method['method'], $methods);
        });

    }

    /**
     * Simulate the escalation of a ticket with the timeline button.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     * @param array $options
     */
    public function escalateWithTimelineButton(\Ticket $ticket, \Group $group, array $options = []): void
    {
        $options['ticket_details'] = array_merge(
            $options['ticket_details'] ?? [],
            [
                'id' => $ticket->getID(),
            ],
        );
        $_POST['comment'] = $options['comment'] ?? 'Default comment';
        \PluginEscaladeTicket::timelineClimbAction($group->getID(), $ticket->getID(), $options);
        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
        ]);
        $this->assertTrue($is_escalate);
        if (isset($options['is_observer_checkbox']) && $options['is_observer_checkbox']) {
            $ticket_user = new \Ticket_User();
            $is_observer = $ticket_user->getFromDBByCrit([
                'type'       => \CommonITILActor::OBSERVER,
                'tickets_id' => $ticket->getID(),
                'users_id'   => Session::getLoginUserID(),
            ]);
            $this->assertTrue($is_observer);
        }
    }

    /**
     * Simulate the escalation of a ticket with the history button.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     */
    public function escalateWithHistoryButton(\Ticket $ticket, \Group $group): void
    {
        \PluginEscaladeTicket::climb_group($ticket->getID(), $group->getID(), true);
        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
        ]);
        $this->assertTrue($is_escalate);
    }

    /**
     * Simulate the escalation of a ticket with a solved ticket.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     * @param array $solution_options
     */
    public function escalateWithSolvedTicket(\Ticket $ticket, \Group $group, array $solution_options = []): void
    {
        $config = new \PluginEscaladeConfig();
        $conf = $config->find();
        $conf = reset($conf);
        $config->getFromDB($conf['id']);
        $this->assertGreaterThan(0, $conf['id']);

        // Update escalade config
        $config->update([
            'id' => 1,
            'solve_return_group' => 1,
        ] + $conf);

        $solution = new \ITILSolution();
        $solution_id = $solution->add(array_merge([
            'content' => 'Test Solution',
            'itemtype' => $ticket->getType(),
            'items_id' => $ticket->getID(),
            'users_id' => Session::getLoginUserID(),
        ], $solution_options));
        $this->assertGreaterThan(0, $solution_id);

        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
        ]);
        $this->assertTrue($is_escalate);
    }

    /**
     * Simulate the escalation of a ticket with a reject solution ticket.
     *
     * @param \Ticket $ticket
     * @param \Group $group
     * @param array $followup_options
     */
    public function escalateWithRejectSolutionTicket(\Ticket $ticket, \Group $group, array $followup_options = []): void
    {
        $_POST['add_reopen'] = 1;

        $followup = new \ITILFollowup();
        $followup_id = $followup->add(array_merge([
            'itemtype'   => 'Ticket',
            'items_id'   => $ticket->getID(),
            'add_reopen'   => '1',
            'content'      => 'reopen followup',
        ], $followup_options));
        $this->assertGreaterThan(0, $followup_id);

        $ticketgroup = new \Group_Ticket();
        $is_escalate = $ticketgroup->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'groups_id'  => $group->getID(),
        ]);
        $this->assertTrue($is_escalate);
    }

    /**
     * Simulate the escalation of a ticket with the assign myself button.
     *
     * @param \Ticket $ticket
     * @param \User $user
     */
    public function escalateWithAssignMySelfButton(\Ticket $ticket, \User $user): void
    {
        $ticket->update([
            'id' => $ticket->getID(),
            '_itil_assign' => [
                '_type' => "user",
                'users_id' => $user->getID(),
                'use_notification' => 1,
            ],
        ]);
        $ticket_user = new \Ticket_User();
        $is_escalate = $ticket_user->getFromDBByCrit([
            'tickets_id' => $ticket->getID(),
            'users_id'  => $user->getID(),
        ]);
        $this->assertTrue($is_escalate);
    }
}

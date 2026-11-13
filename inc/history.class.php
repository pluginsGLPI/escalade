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

if (!defined('GLPI_ROOT')) {
    throw new Exception("Sorry. You can't access directly to this file");
}

class PluginEscaladeHistory extends CommonDBTM
{
    public const HISTORY_LIMIT = 4;

    public static function getFirstLineForTicket($tickets_id)
    {
        $found = self::getFullHistory($tickets_id);
        if (count($found) === 0) {
            return false;
        } else {
            return array_pop($found);
        }
    }

    public static function getlastLineForTicket($tickets_id)
    {
        $found = self::getFullHistory($tickets_id);
        if (count($found) === 0) {
            return false;
        } else {
            return array_shift($found);
        }
    }

    public static function getLastHistoryForTicketAndGroup($tickets_id, $groups_id, $previous_groups_id)
    {
        $history = new self();
        $history->getFromDBByRequest(['ORDER'   => 'date_mod DESC',
            'LIMIT'      => 1,
            'WHERE'
            => [
                'tickets_id' => $tickets_id,
                'groups_id' => [$groups_id, $previous_groups_id],
                'groups_id_previous' => [$groups_id, $previous_groups_id],
            ],
        ]);

        return $history;
    }

    public static function getFullHistory($tickets_id)
    {
        $history = new self();
        return $history->find(['tickets_id' => $tickets_id], "date_mod DESC");
    }

    /**
     * Get the most recently escalated group for a ticket
     * This method is more reliable than getLastLineForTicket as it uses date_mod ordering
     *
     * @param int $tickets_id The ticket ID
     * @return array|false The most recent escalation history entry or false if none found
     */
    public static function getMostRecentEscalationForTicket($tickets_id)
    {
        $history = new self();
        $found = $history->find(
            ['tickets_id' => $tickets_id],
            ['date_mod DESC', 'id DESC'],
            1,
        );

        if (empty($found)) {
            return false;
        }

        return reset($found);
    }

    /**
     * Get the most recently escalated group that is still assigned to the ticket
     * This provides an additional verification layer for notification targeting
     *
     * @param int $tickets_id The ticket ID
     * @return array|false The most recent relevant escalation entry or false if none found
     */
    public static function getMostRecentRelevantEscalationForTicket($tickets_id)
    {
        $history = new self();
        $escalations = $history->find(
            ['tickets_id' => $tickets_id],
            ['date_mod DESC', 'id DESC'],
        );

        if (empty($escalations)) {
            return false;
        }

        // Verify which escalated groups are still assigned to the ticket
        $group_ticket = new Group_Ticket();
        $assigned_groups = $group_ticket->find([
            'tickets_id' => $tickets_id,
            'type' => CommonITILActor::ASSIGN,
        ]);

        $assigned_group_ids = array_column($assigned_groups, 'groups_id');

        // Find the most recent escalation where the group is still assigned
        foreach ($escalations as $escalation) {
            if (in_array($escalation['groups_id'], $assigned_group_ids)) {
                return $escalation;
            }
        }

        return false;
    }


    public static function getHistory($tickets_id, $full_history = false)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $filter_groups_id = [];
        if ($_SESSION['glpi_plugins']['escalade']['config']['use_filter_assign_group']) {
            $groups_groups = new PluginEscaladeGroup_Group();
            $filter_groups_id = $groups_groups->getGroups($tickets_id);
            $use_filter_assign_group = true;
        } else {
            $use_filter_assign_group = false;
        }

        $plugin_dir = plugin_escalade_geturl();

        $history = new self();
        $order = ['date_mod DESC', 'id DESC'];

        if ($full_history) {
            // The popup is a complete audit trail: all lines.
            $found = $history->find(['tickets_id' => $tickets_id], $order);

            //remove first line (current assign)
            $first_group = array_shift($found);
            $has_more = false;
        } else {
            // The widget displays HISTORY_LIMIT lines: only read those, plus one to know if there are more.
            $first_group = $history->find(['tickets_id' => $tickets_id], $order, 1);
            $first_group = reset($first_group);

            $found = [];
            if ($first_group !== false) {
                // Hide still-assigned groups from the compact widget only: the full popup stays a complete audit trail.
                $group_ticket = new Group_Ticket();
                $currently_assigned_ids = array_map(
                    static fn(array $actor): int => (int) $actor['groups_id'],
                    $group_ticket->find([
                        'tickets_id' => $tickets_id,
                        'type'       => CommonITILActor::ASSIGN,
                    ]),
                );

                $criteria = [
                    'tickets_id' => $tickets_id,
                    'NOT'        => ['id' => $first_group['id']],
                ];
                if ($currently_assigned_ids !== []) {
                    $criteria[] = ['NOT' => ['groups_id' => $currently_assigned_ids]];
                }

                $found = $history->find($criteria, $order, self::HISTORY_LIMIT + 1);
            }

            $has_more = count($found) > self::HISTORY_LIMIT;
            $found = array_slice($found, 0, self::HISTORY_LIMIT);
        }

        // One Group load per distinct group, not per history line.
        $groups = [];
        $get_group = static function (int $groups_id) use (&$groups): ?Group {
            if (!array_key_exists($groups_id, $groups)) {
                $group = new Group();
                $groups[$groups_id] = $group->getFromDB($groups_id) ? $group : null;
            }

            return $groups[$groups_id];
        };

        if ($full_history && $first_group) {
            //show 1st group
            echo "<div class='escalade_active'>";
            echo "&nbsp;<i class='fas fa-users'></i>&nbsp;";
            $group = $get_group((int) $first_group['groups_id']);
            if ($group instanceof Group) {
                echo $group->getLink();
            }

            echo "</div>";
        }

        $rand = mt_rand();
        echo sprintf("<div class='escalade' id='escalade-history-%d'>", $rand);
        //parse all lines
        foreach ($found as $hline) {
            echo "<div class='escalade_history'>";

            if (! $use_filter_assign_group || isset($filter_groups_id[$hline['groups_id']])) {
                // Remplacement du lien par un formulaire
                echo sprintf("<form action='%sfront/climb_group.php' method='GET' class='escalade-history-form'>", $plugin_dir);
                echo "<input type='hidden' name='tickets_id' value='" . (int) $tickets_id . "'>";
                echo "<input type='hidden' name='groups_id' value='" . (int) $hline['groups_id'] . "'>";
                echo "<button type='submit' title='" . __s("Reassign the ticket to group", "escalade") . "' class='btn btn-icon btn-sm btn-ghost-secondary'>
                    <i class='ti ti-arrow-up'></i>
                </button>";
                echo "</form>";
            } else {
                echo "&nbsp;&nbsp;&nbsp;";
            }

            //group link
            echo "&nbsp;<i class='ti ti-users'></i>&nbsp;";
            $group = $get_group((int) $hline['groups_id']);
            if ($group instanceof Group) {
                echo self::showGroupLink($group, $full_history);
            }

            echo "</div>";
        }

        if (!$full_history && $found !== []) {
            // Copy the ticket form values into every climb form, once for the whole widget.
            // Not in the popup: it has neither jQuery nor the ticket form.
            echo "<script>
                $(function () {
                    var inputs = $('#itil-form').serializeArray();
                    $('#escalade-history-{$rand} form.escalade-history-form').each(function () {
                        var asset_form = $(this);
                        $.each(inputs, function(i, input) {
                            if (input.name != '_actors') {
                                asset_form.append($('<input>', {
                                    type: 'hidden',
                                    name: 'ticket_details[' + input.name + ']',
                                    value: input.value
                                }));
                            }
                        });
                    });
                });
            </script>";
        }

        //In case there are more than 10 group changes, a popup can display historical
        if ($has_more) {
            echo Ajax::createModalWindow(
                'full_history',
                $plugin_dir . "/front/popup_histories.php?tickets_id=" . $tickets_id,
                [
                    'title' => __s("full assignation history", "escalade"),
                ],
            );
            echo "<a href='#' onclick='full_history.show();' title='" . __s("View full history", "escalade") . "'>...</a>";
        }

        echo "</div>";
    }

    public static function showGroupLink($group, $full_history = false)
    {

        if (!$group->can($group->fields['id'], READ)) {
            return '';
        }

        $link_item = $group->getFormURL();

        $link  = $link_item;
        $link .= (strpos((string) $link, '?') ? '&amp;' : '?') . 'id=' . $group->fields['id'];
        $link .= ($group->isTemplate() ? "&amp;withtemplate=1" : "");

        echo sprintf("<a href='%s'", $link);
        if ($full_history) {
            echo sprintf(" onclick='self.opener.location.href=\"%s\"; self.close();'", $link);
        }

        echo ">" . $group->getNameID(true) . "</a>";
        return null;
    }

    public static function showCentralList()
    {
        self::showCentralSpecificList("solved");
        self::showCentralSpecificList("notold");
    }

    public static function showCentralSpecificList($type)
    {
        /** @var array $CFG_GLPI */
        /** @var DBmysql $DB */
        global $CFG_GLPI, $DB;

        if (
            ! Session::haveRight("ticket", Ticket::READALL)
            && ! Session::haveRight("ticket", Ticket::READASSIGN)
            && ! Session::haveRight("ticket", CREATE)
            && ! Session::haveRight("ticketvalidation", TicketValidation::VALIDATEREQUEST
                                                      & TicketValidation::VALIDATEINCIDENT)
        ) {
            return false;
        }

        $groups = $_SESSION['glpigroups'] ?? [];
        if ($groups === []) {
            // Both lists only show tickets related to the user groups.
            return null;
        }

        $assigned_groups_join = [
            'glpi_groups_tickets' => [
                'ON' => [
                    'glpi_groups_tickets' => 'tickets_id',
                    'glpi_tickets'        => 'id',
                    [
                        'AND' => ['glpi_groups_tickets.type' => CommonITILActor::ASSIGN],
                    ],
                ],
            ],
        ];

        if ($type == "notold") {
            $title = __s("Tickets to follow (escalated)", "escalade");
            $joins = [
                'glpi_plugin_escalade_histories' => [
                    'ON' => [
                        'glpi_plugin_escalade_histories' => 'tickets_id',
                        'glpi_tickets'                   => 'id',
                    ],
                ],
            ] + $assigned_groups_join;
            $where = [
                'glpi_plugin_escalade_histories.groups_id' => $groups,
                [
                    'OR' => [
                        ['NOT' => ['glpi_groups_tickets.groups_id' => $groups]],
                        ['glpi_groups_tickets.groups_id' => null],
                    ],
                ],
                'glpi_tickets.status' => [
                    CommonITILObject::INCOMING,
                    CommonITILObject::PLANNED,
                    CommonITILObject::ASSIGNED,
                    CommonITILObject::WAITING,
                ],
            ];
        } else {
            $title = __s("Tickets to close (escalated)", "escalade");
            $joins = $assigned_groups_join;
            $where = [
                'glpi_groups_tickets.groups_id' => $groups,
                'glpi_tickets.status'           => CommonITILObject::SOLVED,
            ];
        }

        $criteria = [
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => $joins,
            'WHERE'     => [
                'glpi_tickets.is_deleted' => 0,
            ] + $where,
        ];
        // Added as its own element: merging it could collide with the numeric keys of $where.
        $criteria['WHERE'][] = getEntitiesRestrictCriteria('glpi_tickets');

        // Count the tickets, then fetch only the displayed ones.
        $numrows = (int) $DB->request($criteria + [
            'SELECT' => ['COUNT DISTINCT' => 'glpi_tickets.id AS cpt'],
        ])->current()['cpt'];
        if (!$numrows) {
            return null;
        }

        // date_mod is selected because DISTINCT requires the ORDER BY columns in the select list (MySQL 8).
        $tickets = $DB->request($criteria + [
            'SELECT'   => ['glpi_tickets.id', 'glpi_tickets.date_mod'],
            'DISTINCT' => true,
            'ORDER'    => 'glpi_tickets.date_mod DESC',
            'LIMIT'    => 5,
        ]);
        $number = count($tickets);

        //show central list
        if ($numrows > 0) {
            //construct link to ticket list
            $options['reset'] = 'reset';

            $options['criteria'][0]['field']      = 12; // status
            $options['criteria'][0]['searchtype'] = 'equals';
            if ($type == 'notold') {
                $options['criteria'][0]['value']   = 'notold';
            } elseif ($type == 'solved') {
                $options['criteria'][0]['value']   = 5;
            }

            $options['criteria'][0]['link']       = 'AND';

            if ($type == 'notold') {
                $options['criteria'][1]['field']      = 1881; // groups_id_assign for escalade history
                $options['criteria'][1]['searchtype'] = 'equals';
                $options['criteria'][1]['value']      = 'mygroups';
                $options['criteria'][1]['link']       = 'AND';
            }

            $options['criteria'][2]['field']      = 8;
            // groups_id_assign
            $options['criteria'][2]['searchtype'] = $type == 'notold' ? 'notequals' : 'equals';

            $options['criteria'][2]['value']      = 'mygroups';
            $options['criteria'][2]['link']       = 'AND';

            echo "<div class='grid-item col-xl-6 escalade-appended'><div class='card'>";
            echo "<div class='card-body p-0'>";
            echo "<div class='lazy-widget' data-itemtype='Ticket' data-widget='central_list'>";
            echo "<div class='table-responsive card-table'>";
            echo "<table class='table table-borderless table-striped table-hover card-table'>";
            echo "<thead>";
            echo "<tr><th colspan='4'>";
            echo '<a href="' . $CFG_GLPI["root_doc"] . "/front/ticket.php?"
                         . Toolbox::append_params($options, '&amp;') . '">'
                         . Html::makeTitle($title, $number, $numrows) . "</a>";
            echo "</th></tr>";

            if ($number) {
                echo "<tr>";
                echo "<th></th>";
                echo "<th>" . __s('Requester') . "</th>";
                echo "<th>" . __s('Associated element') . "</th>";
                echo "<th>" . __s('Description') . "</th></tr></thead>";
                foreach ($tickets as $ticket) {
                    Ticket::showVeryShort($ticket['id'], 'Ticket$2');
                }
            }

            echo "</table>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }

        return null;
    }
}

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
    die("Sorry. You can't access directly to this file");
}

class PluginEscaladeHistory extends CommonDBTM
{
    const HISTORY_LIMIT = 4;

    public static function getFirstLineForTicket($tickets_id)
    {
        $found = self::getFullHistory($tickets_id);
        if (count($found) == 0) {
            return false;
        } else {
            return array_pop($found);
        }
    }

    public static function getlastLineForTicket($tickets_id)
    {
        $found = self::getFullHistory($tickets_id);
        if (count($found) == 0) {
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
            'WHERE' =>
                                                 [
                                                     'tickets_id' => $tickets_id,
                                                     'groups_id' => [$groups_id, $previous_groups_id],
                                                     'groups_id_previous' => [$groups_id, $previous_groups_id]
                                                 ]
        ]);

        return $history;
    }

    public static function getFullHistory($tickets_id)
    {
        $history = new self();
        return $history->find(['tickets_id' => $tickets_id], "date_mod DESC");
    }


    public static function getHistory($tickets_id, $full_history = false)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $filter_groups_id = [];
        if ($_SESSION['plugins']['escalade']['config']['use_filter_assign_group']) {
            $groups_groups = new PluginEscaladeGroup_Group();
             $filter_groups_id = $groups_groups->getGroups($tickets_id);
            $use_filter_assign_group = true;
        } else {
            $use_filter_assign_group = false;
        }

        $plugin_dir = Plugin::getWebDir('escalade');

       //get all line for this ticket
        $group = new Group();

        $history = new self();
        $found = $history->find(['tickets_id' => $tickets_id], "date_mod DESC");
        $nb_histories = count($found);

       //remove first line (current assign)
        $first_group = array_shift($found);

        if ($full_history) {
           //show 1st group
            echo "<div class='escalade_active'>";
            echo "&nbsp;<i class='fas fa-users'></i>&nbsp;";
            if ($group->getFromDB($first_group['groups_id'])) {
                echo $group->getLink();
            }
            echo "</div>";
        }

        echo "<div class='escalade'>";
       //parse all lines
        $i = 0;
        foreach ($found as $key => $hline) {
            echo "<div class='escalade_history'>";

            if (! $use_filter_assign_group || isset($filter_groups_id[$hline['groups_id']])) {
                $rand = mt_rand();
                // Remplacement du lien par un formulaire
                echo "<form action='$plugin_dir/front/climb_group.php' method='GET' id='history-form-$rand'>";
                echo "<input type='hidden' name='tickets_id' value='" . $tickets_id . "'>";
                echo "<input type='hidden' name='groups_id' value='" . $hline['groups_id'] . "'>";
                echo "<button type='submit' title='" . __("Reassign the ticket to group", "escalade") . "' class='btn btn-icon btn-sm btn-ghost-secondary'>
                    <i class='ti ti-arrow-up'></i>
                </button>";
                echo "</form>";

                echo "<script>
                    $(document).ready(function () {
                        var form = $('#itil-form');
                        var inputs = form.serializeArray();
                        var asset_form = $('#history-form-$rand');

                        if (asset_form.length > 0) {
                            $.each(inputs, function(i, input) {
                                if (input.name != '_actors') {
                                    asset_form.append('<input type=\"hidden\" name=\"ticket_details[' + input.name + ']\" value=\"' + input.value + '\">');
                                }
                            });
                        }
                    });
                </script>";
            } else {
                echo "&nbsp;&nbsp;&nbsp;";
            }

            //group link
            echo "&nbsp;<i class='ti ti-users'></i>&nbsp;";
            if ($group->getFromDB($hline['groups_id'])) {
                echo self::showGroupLink($group, $full_history);
            }

            echo "</div>";

            $i++;
            if ($i == self::HISTORY_LIMIT && !$full_history) {
                break;
            }
        }

       //In case there are more than 10 group changes, a popup can display historical
        if ($nb_histories - 1 > self::HISTORY_LIMIT && !$full_history) {
            echo Ajax::createModalWindow(
                'full_history',
                $plugin_dir . "/front/popup_histories.php?tickets_id=" . $tickets_id,
                [
                    'title' => __("full assignation history", "escalade")
                ]
            );
            echo "<a href='#' onclick='full_history.show();' title='" . __("View full history", "escalade") . "'>...</a>";
        }

        echo "</div>";
    }

    public static function showGroupLink($group, $full_history = false)
    {

        if (!$group->can($group->fields['id'], READ)) {
            return $group->getNameID(true);
        }

        $link_item = $group->getFormURL();

        $link  = $link_item;
        $link .= (strpos($link, '?') ? '&amp;' : '?') . 'id=' . $group->fields['id'];
        $link .= ($group->isTemplate() ? "&amp;withtemplate=1" : "");

        echo "<a href='$link'";
        if ($full_history) {
            echo " onclick='self.opener.location.href=\"$link\"; self.close();'";
        }
        echo ">" . $group->getNameID(true) . "</a>";
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

        $groups     = implode("','", $_SESSION['glpigroups']);
        $numrows    = 0;
        $is_deleted = " `glpi_tickets`.`is_deleted` = 0 ";

        if ($type == "notold") {
            $title = __("Tickets to follow (escalated)", "escalade");
            $status = CommonITILObject::INCOMING . ", " . CommonITILObject::PLANNED . ", " .
                   CommonITILObject::ASSIGNED . ", " . CommonITILObject::WAITING;

            $search_assign = " `glpi_plugin_escalade_histories`.`groups_id` IN ('$groups')
            AND (`glpi_groups_tickets`.`groups_id` NOT IN ('$groups')
            OR `glpi_groups_tickets`.`groups_id` IS NULL)";

            $query_join = "LEFT JOIN `glpi_plugin_escalade_histories`
            ON (`glpi_tickets`.`id` = `glpi_plugin_escalade_histories`.`tickets_id`)
         LEFT JOIN `glpi_groups_tickets`
            ON (`glpi_tickets`.`id` = `glpi_groups_tickets`.`tickets_id`
               AND `glpi_groups_tickets`.`type`=2)";
        } else {
            $title = __("Tickets to close (escalated)", "escalade");
            $status = CommonITILObject::SOLVED;

            $search_assign = " (`glpi_groups_tickets`.`groups_id` IN ('$groups'))";

            $query_join = "LEFT JOIN `glpi_groups_tickets`
            ON (`glpi_tickets`.`id` = `glpi_groups_tickets`.`tickets_id`
               AND `glpi_groups_tickets`.`type`=2)";
        }

        $query = "SELECT DISTINCT `glpi_tickets`.`id`
                FROM `glpi_tickets`
                LEFT JOIN `glpi_tickets_users`
                  ON (`glpi_tickets`.`id` = `glpi_tickets_users`.`tickets_id`)";

        $query .= $query_join;

        $query .= "WHERE $is_deleted AND ( $search_assign )
                  AND (`status` IN ($status))" .
                  getEntitiesRestrictRequest("AND", "glpi_tickets");

        $query  .= " ORDER BY glpi_tickets.date_mod DESC";

        $result  = $DB->doQuery($query);
        $numrows = $DB->numrows($result);
        if (!$numrows) {
            return;
        }

        $query .= " LIMIT 0, 5";
        $result = $DB->doQuery($query);
        $number = $DB->numrows($result);

       //show central list
        if ($numrows > 0) {
           //construct link to ticket list
            $options['reset'] = 'reset';

            $options['criteria'][0]['field']      = 12; // status
            $options['criteria'][0]['searchtype'] = 'equals';
            if ($type == 'notold') {
                $options['criteria'][0]['value']   = 'notold';
            } else if ($type == 'solved') {
                $options['criteria'][0]['value']   = 5;
            }
            $options['criteria'][0]['link']       = 'AND';

            if ($type == 'notold') {
                $options['criteria'][1]['field']      = 1881; // groups_id_assign for escalade history
                $options['criteria'][1]['searchtype'] = 'equals';
                $options['criteria'][1]['value']      = 'mygroups';
                $options['criteria'][1]['link']       = 'AND';
            }

            $options['criteria'][2]['field']      = 8; // groups_id_assign
            if ($type == 'notold') {
                $options['criteria'][2]['searchtype'] = 'notequals';
            } else {
                $options['criteria'][2]['searchtype'] = 'equals';
            }
            $options['criteria'][2]['value']      = 'mygroups';
            $options['criteria'][2]['link']       = 'AND';

            echo "<div class='grid-item col-xl-6 escalade-appended'><div class='card'>";
            echo "<div class='card-body p-0'>";
            echo "<div class='lazy-widget' data-itemtype='Ticket' data-widget='central_list'>";
            echo "<div class='table-responsive card-table'>";
            echo "<table class='table table-borderless table-striped table-hover card-table'>";
            echo "<thead>";
            echo "<tr><th colspan='4'>";
            echo "<a href=\"" . $CFG_GLPI["root_doc"] . "/front/ticket.php?" .
                         Toolbox::append_params($options, '&amp;') . "\">" .
                         Html::makeTitle($title, $number, $numrows) . "</a>";
            echo "</th></tr>";

            if ($number) {
                echo "<tr>";
                echo "<th></th>";
                echo "<th>" . __('Requester') . "</th>";
                echo "<th>" . __('Associated element') . "</th>";
                echo "<th>" . __('Description') . "</th></tr></thead>";
                for ($i = 0; $i < $number; $i++) {
                    $ID = $DB->result($result, $i, "id");
                    Ticket::showVeryShort($ID, 'Ticket$2');
                }
            }
            echo "</table>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }
    }
}

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

use Glpi\Application\View\TemplateRenderer;

class PluginEscaladeConfig extends CommonDBTM
{
    public static $rightname  = 'config';

    public static function getMenuName()
    {
        return __('Escalade', 'escalade');
    }

    public static function getTypeName($nb = 0)
    {
        return __("Configuration Escalade plugin", "escalade");
    }

    public static function getSearchURL($full = true)
    {
        return '/plugins/escalade/front/config.form.php';
    }

    public static function getIcon()
    {
        return "ti ti-escalator-up";
    }

    public static function getMenuContent()
    {
        $links = [];

        $menu = [
            'title'   => self::getMenuName(),
            'page'    => self::getSearchURL(false),
            'icon'    => self::getIcon(),
            'options' => [],
            'links'   => $links,
        ];

        return $menu;
    }

    /**
     * Summary of showForm
     * @param mixed $ID
     * @param mixed $options
     * @return bool
     */
    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->check($ID, READ);

        echo "<div class='escalade_config'>";
        $this->showFormHeader($options);

        $rand = mt_rand();
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='dropdown_remove_group$rand'>";
        echo __("Remove old assign group on new group assign", "escalade") . "</label></td>";
        echo "<td>";
        Dropdown::showYesNo("remove_group", $this->fields["remove_group"], -1, [
            'on_change' => 'hide_show_history(this.value)',
            'width' => '25%',
            'rand' => $rand,
        ]);
        echo Html::scriptBlock("
         function hide_show_history(val) {
            var display = (val == 0) ? 'none' : '';
            document.getElementById('show_history_td1').style.display = display;
            document.getElementById('show_history_td2').style.display = display;
            document.getElementById('show_solve_return_group_td1').style.display = display;
            document.getElementById('show_solve_return_group_td2').style.display = display;
         }
      ");
        echo "</td>";

        $style = ($this->fields["remove_group"]) ? "" : "style='display: none !important;'";

        $rand = mt_rand();
        echo "<td id='show_history_td1' $style><label for='dropdown_show_history$rand'>";
        echo __("show group assign history visually", "escalade");
        echo "</label></td>";
        echo "<td id='show_history_td2' $style>";
        Dropdown::showYesNo("show_history", $this->fields["show_history"], -1, [
            'width' => '100%',
            'rand' => $rand,
        ]);
        echo "</td>";
        echo "</tr>";

        $rand = mt_rand();
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='dropdown_task_history$rand'>" . __("Escalation history in tasks", "escalade") . "</label></td>";
        echo "<td>";
        Dropdown::showYesNo("task_history", $this->fields["task_history"], -1, [
            'width' => '25%',
            'rand' => $rand,
        ]);
        echo "</td>";

        $rand = mt_rand();
        echo "<td><label for='dropdown_remove_tech$rand'>" . __("Remove technician(s) on escalation", "escalade") . "</label></td>";
        echo "<td>";
        Dropdown::showYesNo("remove_tech", $this->fields["remove_tech"], -1, [
            'width' => '100%',
            'rand' => $rand,
        ]);
        echo "</td>";
        echo "</tr>";

        $rand = mt_rand();
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='dropdown_ticket_last_status$rand'>";
        echo __("Ticket status after an escalation", "escalade") . "</label></td>";
        echo "<td>";
        self::dropdownGenericStatus(
            "Ticket",
            "ticket_last_status",
            $rand,
            $this->fields["ticket_last_status"],
        );
        echo "</td>";

        $rand = mt_rand();
        echo "<td id='show_solve_return_group_td1' $style><label for='dropdown_solve_return_group$rand'>";
        echo __("Assign ticket to initial group on solve ticket", "escalade");
        echo "</td>";
        echo "<td id='show_solve_return_group_td2' $style>";
        Dropdown::showYesNo("solve_return_group", $this->fields["solve_return_group"], -1, [
            'width' => '100%',
            'rand' => $rand,
        ]);
        echo "</td>";
        echo "</tr>";

        $rand = mt_rand();
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='dropdown_reassign_tech_from_cat$rand'>";
        echo __("Assign the technical manager on ticket category change", "escalade");
        echo "</label></td>";
        echo "<td>";
        Dropdown::showYesNo("reassign_tech_from_cat", $this->fields["reassign_tech_from_cat"], -1, [
            'width' => '25%',
            'rand' => $rand,
        ]);
        echo "</td>";

        $rand = mt_rand();
        echo "<td><label for='dropdown_reassign_group_from_cat$rand'>";
        echo __("Assign the technical group on ticket category change", "escalade");
        echo "</td>";
        echo "<td>";
        Dropdown::showYesNo("reassign_group_from_cat", $this->fields["reassign_group_from_cat"], -1, [
            'width' => '100%',
            'rand' => $rand,
        ]);
        echo "</td>";
        echo "</tr>";

        $rand = mt_rand();
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='dropdown_cloneandlink_ticket$rand'>" . __("Clone and link tickets", "escalade") . "</label></td>";
        echo "<td>";
        Dropdown::showYesNo("cloneandlink_ticket", $this->fields["cloneandlink_ticket"], -1, [
            'width' => '25%',
            'rand' => $rand,
        ]);
        echo "</td>";

        $rand = mt_rand();
        echo "<td><label for='dropdown_close_linkedtickets$rand'>";
        echo __("Close linked tickets at the same time", "escalade");
        echo "</label></td>";
        echo "<td>";
        Dropdown::showYesNo("close_linkedtickets", $this->fields["close_linkedtickets"], -1, [
            'width' => '100%',
            'rand' => $rand,
        ]);
        echo "</td>";
        echo "</tr>";

        $yesnoall = [
            0 => __("No"),
            1 => __('First'),
            2 => __('Last'),
        ];

        $rand = mt_rand();
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='dropdown_assign_me_as_observer$rand'>";
        echo __("Assign me as observer by default", "escalade") . "</label></td>";
        echo "<td>";
        Dropdown::showYesNo("assign_me_as_observer", $this->fields["assign_me_as_observer"], -1, [
            'width' => '25%',
            'rand' => $rand,
        ]);

        $rand = mt_rand();
        echo "<td><label for='dropdown_use_assign_user_group$rand'>" . __("Use the technician's group", "escalade") . "</label></td>";
        echo "<td>";
        Dropdown::showFromArray('use_assign_user_group', $yesnoall, [
            'value'     => $this->fields['use_assign_user_group'],
            'width'     => '120px',
            'rand'      => $rand,
            'on_change' => 'hide_technician_group(this.value)',
        ]);
        echo "</td>";
        echo "<td colspan='2'>";
        $style = "width: 100%;";
        $style .= $this->fields["use_assign_user_group"]
                  ? ""
                  : "display: none !important;";
        echo "<table style='$style' id='use_technican_group_details'>";
        echo "<tr>";
        echo "<td></td>";
        echo "<td><label for='dropdown_use_assign_user_group_creation$rand'>";
        echo __("at creation time", "escalade") . "</label></td>";
        echo "<td><label for='dropdown_use_assign_user_group_modification$rand'>";
        echo __("at modification time", "escalade") . "</label></td>";
        echo "</tr>";
        echo "<tr><td>";
        echo Html::scriptBlock("
         function hide_technician_group(val) {
            var display = (val == 0) ? 'none' : '';
            document.getElementById('use_technican_group_details').style.display = display;
         }
      ");
        echo "</td>";

        $rand = mt_rand();
        echo "<td>";
        Dropdown::showYesNo(
            "use_assign_user_group_creation",
            $this->fields["use_assign_user_group_creation"],
            -1,
            ['rand' => $rand],
        );
        echo "</td>";

        $rand = mt_rand();
        echo "<td style='padding:0px'>";
        Dropdown::showYesNo(
            "use_assign_user_group_modification",
            $this->fields["use_assign_user_group_modification"],
            -1,
            ['rand' => $rand],
        );
        echo "</td>";
        echo "</tr></table>";
        if (Plugin::isPluginActive('behaviors')) {
            $behaviorlink = $CFG_GLPI["root_doc"] . "/front/config.form.php?forcetab=PluginBehaviorsConfig%241";
        }

        TemplateRenderer::getInstance()->display(
            '@escalade/config.html.twig',
            [
                'id'                => $ID,
                'item'              => $this,
                'config'            => $this->fields,
                'action'            => plugin_escalade_geturl() . 'front/config.form.php',
                'generic_status'    => self::getGenericStatus("Ticket"),
                'behaviorlink'      => $behaviorlink ?? '',
            ],
        );
        return true;
    }

    public static function loadInSession()
    {
        $config = new self();
        $config->getFromDB(1);
        unset($config->fields['id']);

        if (
            isset($_SESSION['glpiID'])
            && isset($config->fields['use_filter_assign_group'])
            && $config->fields['use_filter_assign_group']
        ) {
            $user = new PluginEscaladeUser();
            if ($user->getFromDBByCrit(['users_id' => $_SESSION['glpiID']])) {
                //if a bypass is defined for user
                if ($user->fields['bypass_filter_assign_group']) {
                    $config->fields['use_filter_assign_group'] = 0;
                }
            }
        }

        $_SESSION['glpi_plugins']['escalade']['config'] = $config->fields;
    }

    public static function getGenericStatus($itemtype)
    {
        switch ($itemtype) {
            case 'Ticket':
                $item = new Ticket();
                break;
            case 'Change':
                $item = new Change();
                break;
            case 'Problem':
                $item = new Problem();
                break;
            default:
                return [];
        }

        $tab[PluginEscaladeTicket::MANAGED_BY_CORE] = __("Default (not managed by plugin)", "escalade");

        $i = 1;
        foreach ($item->getAllStatusArray(false) as $status) {
            $tab[$i] = $status;
            $i++;
        }

        return $tab;
    }
}

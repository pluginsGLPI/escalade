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

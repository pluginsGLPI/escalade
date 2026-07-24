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

use Glpi\Exception\Http\AccessDeniedHttpException;

Session::checkLoginUser();

Html::header("escalade", $_SERVER["PHP_SELF"], "plugins", "escalade", "group_group");

if (Session::haveRight('group', UPDATE)) {
    if (isset($_POST['addgroup'])) {
        $group = new Group();
        if (
            !$group->getFromDB((int) $_POST['groups_id_source'])
            || !Session::haveAccessToEntity($group->getEntityID())
            || !$group->getFromDB((int) $_POST['groups_id_destination'])
            || !Session::haveAccessToEntity($group->getEntityID())
        ) {
            throw new AccessDeniedHttpException();
        }

        $PluginEscaladeGroup_Group = new PluginEscaladeGroup_Group();
        $PluginEscaladeGroup_Group->add($_POST);
    }

    if (isset($_POST['deleteitem'])) {
        $PluginEscaladeGroup_Group = new PluginEscaladeGroup_Group();
        $group = new Group();
        foreach ($_POST['delgroup'] as $id) {
            if (
                $PluginEscaladeGroup_Group->getFromDB((int) $id)
                && $group->getFromDB((int) $PluginEscaladeGroup_Group->fields['groups_id_source'])
                && Session::haveAccessToEntity($group->getEntityID())
            ) {
                $PluginEscaladeGroup_Group->delete(['id' => $id]);
            }
        }
    }
}

Html::back();

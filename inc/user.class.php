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

class PluginEscaladeUser extends CommonDBTM
{
    /**
     * @since version 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
    **/
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case "bypass_filter_assign_group":
                Dropdown::showYesNo("bypass_filter_assign_group", 0, -1, [
                    'width' => '100%',
                ]);
                echo "<br><br><input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"" .
                 _sx('button', 'Post') . "\" >";
                break;
        }
        return true;
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     **/
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case "bypass_filter_assign_group":
                $escalade_user = new self();
                $input = $ma->getInput();

                foreach ($ids as $id) {
                    if ($escalade_user->getFromDBByCrit(['users_id' => $id])) {
                        $escalade_user->fields['bypass_filter_assign_group'] = $input['bypass_filter_assign_group'];
                        if ($escalade_user->update($escalade_user->fields)) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    }
                }
        }
    }

    public static function getUserGroup($entity, $userid, $filter = '', $first = true)
    {
        /** @var DBmysql $DB */
        global $DB;

        $query = [
            'SELECT'     => 'glpi_groups.id',
            'FROM'       => 'glpi_groups_users',
            'INNER JOIN' => [
                'glpi_groups' => [
                    'FKEY' => [
                        'glpi_groups'     => 'id',
                        'glpi_groups_users'   => 'groups_id',
                    ],
                ],
            ],
            'WHERE'  => [
                'glpi_groups_users.users_id' => $userid,
                'glpi_groups.entities_id'    => $entity,
            ] + getEntitiesRestrictCriteria('glpi_groups', '', $entity, true, true),
            'ORDER' => "glpi_groups_users.id",
        ];

        if ($filter) {
            $query['WHERE'][$filter] = "1";
        }

        $rep = [];
        foreach ($DB->request($query) as $data) {
            if ($first) {
                return $data['id'];
            }
            $rep[] = $data['id'];
        }
        return ($first ? 0 : array_pop($rep));
    }

    public static function getRequesterGroup($entity, $userid, $first = true)
    {

        return self::getUserGroup($entity, $userid, '`is_requester`', $first);
    }

    public static function getTechnicianGroup($entity, $userid, $first = true)
    {

        return self::getUserGroup($entity, $userid, '`is_assign`', $first);
    }


    /**
     * @param int $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, array $options = [])
    {

        $is_exist = $this->getFromDBByCrit(['users_id' => $ID]);

        if (! $is_exist) { //"Security"
            $this->fields["bypass_filter_assign_group"] = 0;
        }

        TemplateRenderer::getInstance()->display('@escalade/user.html.twig', [
            'formurl'   => $this->getFormURL(),
            'rand'      => mt_rand(),
            'users_id'  => $ID,
            'this'      => $this->fields,
            'is_exist'  => $is_exist,
        ]);

        return true;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof User) {
            $user = new self();
            $ID   = $item->getField('id');
            $user->showForm($ID);
        }
        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof User) {
            $ong[] = self::createTabEntry(
                __("Escalation", "escalade"),
                0,
                $item::class,
                self::getIcon(),
            );
            return $ong;
        }
        return '';
    }

    public static function getIcon()
    {
        return "ti ti-escalator-up";
    }
}

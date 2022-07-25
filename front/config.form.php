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
 * @copyright Copyright (C) 2015-2022 by Escalade plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/escalade
 * -------------------------------------------------------------------------
 */

include ("../../../inc/includes.php");

if (! isset($_GET["id"])) {
   $_GET["id"] = 0;
}

if (!Plugin::isPluginActive('escalade')) {
   echo "Plugin not installed or activated";
   return;
}

$config = new PluginEscaladeConfig();

if (isset($_POST["add"])) {

   Session::checkRight("config", CREATE);
   $newID=$config->add($_POST);
   Html::back();

} else if (isset($_POST["update"])) {

   Session::checkRight("config", UPDATE);
   $config->update($_POST);
   Html::back();

} else if (isset($_POST["delete"])) {

   Session::checkRight("config", DELETE);
   $config->delete($_POST, 1);
   Html::redirect("./config.form.php");

} else {

   Html::header(__("Escalation", "escalade"), '', "plugins", "escalade", "config");
   $config->showForm(1);
   Html::footer();

}

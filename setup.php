<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Fields plugin for GLPI
 Copyright (C) 2016 by the fields Development Team.

 https://forge.indepnet.net/projects/mreporting
 -------------------------------------------------------------------------

 LICENSE

 This file is part of fields.

 fields is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 fields is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with fields. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

define ('PLUGIN_ESCALADE_VERSION', '2.1.0');

// Init the hooks of the plugins -Needed
function plugin_init_escalade() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['escalade'] = true;

   $plugin = new Plugin();
   if (isset($_SESSION['glpiID'])
      && $plugin->isInstalled('escalade')
      && $plugin->isActivated('escalade')) {

      //load config in session
      if (TableExists("glpi_plugin_escalade_configs")) {
         PluginEscaladeConfig::loadInSession();

         // == Load js scripts ==
         if (isset($_SESSION['plugins']['escalade']['config'])) {
            $escalade_config = $_SESSION['plugins']['escalade']['config'];

            $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/function.js';

            // on central page
            if (strpos($_SERVER['REQUEST_URI'], "central.php") !== false) {
               //history and climb feature
               if ($escalade_config['show_history']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/central.js.php';
               }
            }

            // on ticket page (in edition)
            if (strpos($_SERVER['REQUEST_URI'], "ticket.form.php") !== false
                && isset($_GET['id'])) {

               //history and climb feature
               if ($escalade_config['show_history']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/escalade.js.php';
               }

               //remove btn feature
               if (!$escalade_config['remove_delete_group_btn']
                  || !$escalade_config['remove_delete_user_btn']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/remove_btn.js.php';
               }

               //clone ticket feature
               if ($escalade_config['cloneandlink_ticket']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/cloneandlink_ticket.js.php';
               }

               //filter group feature
               if ($escalade_config['use_filter_assign_group']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/filtergroup.js.php';
               }
            }

            Plugin::registerClass('PluginEscaladeGroup_Group', array('addtabon' => 'Group'));
            Plugin::registerClass('PluginEscaladeUser', array('addtabon' => array('User')));
         }
      }

      $PLUGIN_HOOKS['add_css']['escalade'][]= 'escalade.css';

      // == Ticket modifications
      $PLUGIN_HOOKS['item_update']['escalade']= array(
         'Ticket'       => 'plugin_escalade_item_update',
      );
      $PLUGIN_HOOKS['item_add']['escalade'] = array(
         'Group_Ticket' => 'plugin_escalade_item_add_group_ticket',
         'Ticket_User'  => 'plugin_escalade_item_add_user',
         'Ticket'       => 'plugin_escalade_item_add_ticket',
      );
      $PLUGIN_HOOKS['pre_item_add']['escalade'] = array(
         'Group_Ticket' => 'plugin_escalade_pre_item_add_group_ticket',
         'Ticket'       => 'plugin_escalade_pre_item_add_ticket',
      );
      $PLUGIN_HOOKS['post_prepareadd']['escalade'] = array(
         'Ticket'       => 'plugin_escalade_post_prepareadd_ticket',
      );

      $PLUGIN_HOOKS['item_purge']['escalade']= array(
         'User'         => 'plugin_escalade_item_purge',
      );
      $PLUGIN_HOOKS['item_add']['escalade']['User'] = 'plugin_escalade_item_add_user';

      // == Interface links ==
      if (Session::haveRight('config', UPDATE)) {
         $PLUGIN_HOOKS['config_page']['escalade'] = 'front/config.form.php';
      }

      $PLUGIN_HOOKS['use_massive_action']['escalade'] = 1;
   }
}

// Get the name and the version of the plugin - Needed
function plugin_version_escalade() {
   return array(
         'name'           => __("Escalation", "escalade"),
         'version'        => PLUGIN_ESCALADE_VERSION,
         'author'         => "<a href='http://www.teclib.com'>Teclib'</a>",
         'homepage'       => "https://github.com/pluginsGLPI/escalade",
         'license'        => 'GPLv2+',
         'minGlpiVersion' => "0.85",
   );
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_escalade_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '0.85', 'lt')) {
      echo "This plugin requires GLPI >= 0.85";
      return false;
   }
   return true;
}

// Check configuration process for plugin : need to return true if succeeded
// Can display a message only if failure and $verbose is true
function plugin_escalade_check_config($verbose=false) {
   if (true) { // Your configuration check
      return true;
   }
   if ($verbose) {
      __('Installed / not configured');
   }
   return false;
}

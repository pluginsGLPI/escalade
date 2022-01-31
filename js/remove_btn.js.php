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

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central") {

   $locale_actor = __('Actor');
   $esc_config = $_SESSION['plugins']['escalade']['config'];

   $remove_delete_requester_user_btn = "true";
   if (isset($esc_config['remove_delete_requester_user_btn'])
       && $esc_config['remove_delete_requester_user_btn']) {
      $remove_delete_requester_user_btn = "false";
   }

   $remove_delete_requester_group_btn = "true";
   if (isset($esc_config['remove_delete_requester_group_btn'])
       && $esc_config['remove_delete_requester_group_btn']) {
      $remove_delete_requester_group_btn = "false";
   }

   $remove_delete_watcher_user_btn = "true";
   if (isset($esc_config['remove_delete_watcher_user_btn'])
       && $esc_config['remove_delete_watcher_user_btn']) {
      $remove_delete_watcher_user_btn = "false";
   }

   $remove_delete_watcher_group_btn = "true";
   if (isset($esc_config['remove_delete_watcher_group_btn'])
       && $esc_config['remove_delete_watcher_group_btn']) {
      $remove_delete_watcher_group_btn = "false";
   }

   $remove_delete_assign_user_btn = "true";
   if (isset($esc_config['remove_delete_assign_user_btn'])
       && $esc_config['remove_delete_assign_user_btn']) {
      $remove_delete_assign_user_btn = "false";
   }

   $remove_delete_assign_group_btn = "true";
   if (isset($esc_config['remove_delete_assign_group_btn'])
       && $esc_config['remove_delete_assign_group_btn']) {
      $remove_delete_assign_group_btn = "false";
   }

   $remove_delete_assign_supplier_btn = "true";
   if (isset($esc_config['remove_delete_assign_supplier_btn'])
       && $esc_config['remove_delete_assign_supplier_btn']) {
      $remove_delete_assign_supplier_btn = "false";
   }

   $JS = <<<JAVASCRIPT
   var removeDeleteButtons = function(str, num) {
      $("table:contains('$locale_actor') td:last-child a[onclick*="+str+"], \
         .tab_actors .actor-bloc:eq("+num+") a[onclick*="+str+"]")
            .remove();
   }

   var removeAllDeleteButtons = function() {

      // Get type from path to handle multiple ticket type
      var type = 'ticket';
      if (location.pathname.indexOf('problem.form.php') > 0) {
         type = 'problem';
      }
      if (location.pathname.indexOf('change.form.php') > 0) {
         type = 'change';
      }

      // ## REQUESTER
      //remove "delete" group buttons
      if ({$remove_delete_requester_group_btn}) {
         removeDeleteButtons("group_"+type, 0);
      }
      //remove "delete" user buttons
      if ({$remove_delete_requester_user_btn}) {
         removeDeleteButtons(type+"_user", 0);
      }

      // ## WATCHER
      //remove "delete" group buttons
      if ({$remove_delete_watcher_group_btn}) {
         removeDeleteButtons("group_"+type, 1);
      }
      //remove "delete" user buttons
      if ({$remove_delete_watcher_user_btn}) {
         removeDeleteButtons(type+"_user", 1);
      }

      // ## ASSIGN
      //remove "delete" group buttons
      if ({$remove_delete_assign_group_btn}) {
         removeDeleteButtons("group_"+type, 2);
      }
      //remove "delete" user buttons
      if ({$remove_delete_assign_user_btn}) {
         removeDeleteButtons(type+"_user", 2);
      }
      //remove "delete" supplier buttons
      if ({$remove_delete_assign_supplier_btn}) {
         removeDeleteButtons("supplier_"+type, 2);
      }
   }

   $(document).ready(function() {
      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0
         || location.pathname.indexOf('problem.form.php') > 0
         || location.pathname.indexOf('change.form.php') > 0) {
         $(".ui-tabs-panel:visible").ready(function() {
            removeAllDeleteButtons();
         })

         $("#tabspanel + div.ui-tabs").on("tabsload", function() {
            setTimeout(function() {
               removeAllDeleteButtons();
            }, 300);
         });
      }
   });
JAVASCRIPT;
   echo $JS;
}
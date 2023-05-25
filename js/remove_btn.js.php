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
   var removeDeleteButtons = function(itemtype, actortype,) {
      if (!$("#actors .actor_entry").length) {
         // Not yet loaded
         return false;
      }

      const target = $("#actors .actor_entry[data-itemtype="+itemtype+"][data-actortype="+actortype+"]");

      // Remove "x" from select2 tag
      target.siblings('.select2-selection__choice__remove').remove();

      // Set the input as required to prevent empty submit if at least one value exist
      if (target.length) {
         target.closest(".field-container").find('select').prop("required", true);
      }

      // Data is loaded
      return true;
   }

   var removeAllDeleteButtons = function(buttons_to_delete) {
      // Iterate on all itemtype + actortype combinations
      for (const [itemtype, actortypes] of Object.entries(buttons_to_delete)) {
         for (const [actortype, to_delete] of Object.entries(actortypes)) {
            if (to_delete) {
               // Keep enabled in buttons_to_delete until success
               buttons_to_delete[itemtype][actortype] = !(removeDeleteButtons(itemtype, actortype));
            }
         }
      }

      return buttons_to_delete;
   }

   $(document).ready(function() {
      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0
         || location.pathname.indexOf('problem.form.php') > 0
         || location.pathname.indexOf('change.form.php') > 0) {
         $(document).on('glpi.tab.loaded', function() {
            let buttons_to_delete = {
               Group: {
                  requester: {$remove_delete_requester_group_btn},
                  observer: {$remove_delete_watcher_group_btn},
                  assign: {$remove_delete_assign_group_btn},
               },
               User: {
                  requester: {$remove_delete_requester_user_btn},
                  observer: {$remove_delete_watcher_user_btn},
                  assign: {$remove_delete_assign_user_btn},
               },
               Supplier: {
                  assign: {$remove_delete_assign_supplier_btn},
               }
            };
            buttons_to_delete = removeAllDeleteButtons(buttons_to_delete);

            // as the ticket loading may be long, try to remove until 10s pass
            var tt = setInterval(function() {
               buttons_to_delete = removeAllDeleteButtons(buttons_to_delete);
            }, 500);
            setTimeout(function() { clearInterval(tt); }, 10000);
         });
      }
   });
JAVASCRIPT;
   echo $JS;
}

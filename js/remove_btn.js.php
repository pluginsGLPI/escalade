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

include("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central") {
    $locale_actor = __('Actor');
    $esc_config = $_SESSION['plugins']['escalade']['config'];

    $remove_delete_requester_user_btn = "true";
    if (
        isset($esc_config['remove_delete_requester_user_btn'])
        && $esc_config['remove_delete_requester_user_btn']
    ) {
        $remove_delete_requester_user_btn = "false";
    }

    $remove_delete_requester_group_btn = "true";
    if (
        isset($esc_config['remove_delete_requester_group_btn'])
        && $esc_config['remove_delete_requester_group_btn']
    ) {
        $remove_delete_requester_group_btn = "false";
    }

    $remove_delete_watcher_user_btn = "true";
    if (
        isset($esc_config['remove_delete_watcher_user_btn'])
        && $esc_config['remove_delete_watcher_user_btn']
    ) {
        $remove_delete_watcher_user_btn = "false";
    }

    $remove_delete_watcher_group_btn = "true";
    if (
        isset($esc_config['remove_delete_watcher_group_btn'])
        && $esc_config['remove_delete_watcher_group_btn']
    ) {
        $remove_delete_watcher_group_btn = "false";
    }

    $remove_delete_assign_user_btn = "true";
    if (
        isset($esc_config['remove_delete_assign_user_btn'])
        && $esc_config['remove_delete_assign_user_btn']
    ) {
        $remove_delete_assign_user_btn = "false";
    }

    $remove_delete_assign_group_btn = "true";
    if (
        isset($esc_config['remove_delete_assign_group_btn'])
        && $esc_config['remove_delete_assign_group_btn']
    ) {
        $remove_delete_assign_group_btn = "false";
    }

    $remove_delete_assign_supplier_btn = "true";
    if (
        isset($esc_config['remove_delete_assign_supplier_btn'])
        && $esc_config['remove_delete_assign_supplier_btn']
    ) {
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

   let getActorsAlreadyPresent = function(buttons_to_delete) {
      let actors = [];
      for (const [itemtype, actortypes] of Object.entries(buttons_to_delete)) {
         for (const [actortype, to_delete] of Object.entries(actortypes)) {
            if (to_delete) {
               let requester_form = $('.form-select.select2-hidden-accessible[data-actor-type='+actortype+']');
               let select2_input = requester_form.next('.select2-container').find('.select2-selection.select2-selection--multiple.actor-field');
               let select2_choices = select2_input.find('span.actor_entry');
               if (!actors[actortype]) {
                  actors[actortype] = [];
               }
               select2_choices.each(function() {
                  let item_id = $(this).data('items-id');
                  let itemtype = $(this).data('itemtype');
                  if (buttons_to_delete[itemtype][actortype]){
                     let exists = actors[actortype].some(el => el.item_id === item_id && el.itemtype === itemtype);

                     if (!exists) {
                           actors[actortype].push({
                              item_id: item_id,
                              itemtype: itemtype
                           });
                     }
                  }
               });
            }
         }
      }
      return actors;
   }

   var removeDeleteButtonForPreviousActors = function(actorslist) {
      for (const actortype of actorslist) {
         var requester_form = $(".form-select.select2-hidden-accessible[data-actor-type="+actortype+"]");
         requester_form.on('select2:selecting', handleEvent);
         requester_form.on('select2:open', handleEvent);
         function handleEvent() {
            for(const [item_id, itemtype] of actortype) {
               setTimeout(function() {
                  removeDeleteButtonForOne(itemtype, item_id, actortype, requester_form);
               }, 50);
            }
         }
      }
   }

   var removeDeleteButtonForOne = function(itemtype, item_id, actortype, form) {
      var select2_container = form.next('.select2-container');
      var select2_choice = select2_container.find(".select2-selection.select2-selection--multiple.actor-field span.actor_entry[data-itemtype="+itemtype+"][data-items-id="+item_id+"][data-actortype="+actortype+"]").first();
      // Remove "x" from select2 tag
      select2_choice.prev('.select2-selection__choice__remove').remove();

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

   var actorCanBeRemoved = function(actortype, data, actors) {
      if (actors === undefined || actors === null || data.id === undefined || data.id === null) {
         return false;
      }
      var parts = data.id.split("_");
      var item_id = parts[1];
      var itemtype = parts[0];
      var actorValues = Object.values(actors);
      var exists = actorValues.some(function(el) {
         return el.item_id == item_id && el.itemtype == itemtype;
      });
      return exists;
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

            var actors = getActorsAlreadyPresent(buttons_to_delete);
            function handleRemoveDeletebuttonForOne(actortype_key, form) {
               return function() {
                  if (actors[actortype_key] === null || actors[actortype_key] === undefined ) {
                     return false;
                  }
                  for(const [item_key, item_value] of Object.entries(actors[actortype_key])) {
                     setTimeout(function() {
                        removeDeleteButtonForOne(item_value['itemtype'], item_value['item_id'], actortype_key, form);
                     }, 50);
                  }
               }
            }

            var actortypes = ['assign', 'requester', 'observer'];
            for (var i = 0; i < actortypes.length; i++) {
               let form = $(".form-select.select2-hidden-accessible[data-actor-type='" + actortypes[i] + "']");

               form.on('select2:select', handleRemoveDeletebuttonForOne(actortypes[i], form));
               form.on('select2:open', handleRemoveDeletebuttonForOne(actortypes[i], form));
               form.on('select2:unselecting', function(e) {
                  let data = e.params.args.data;
                  let actortype = form.data('actor-type');
                  if (actorCanBeRemoved(actortype, data, actors[actortype])) {
                     e.preventDefault();
                  }
               });
               form.on('select2:unselect', handleRemoveDeletebuttonForOne(actortypes[i], form));
            }
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

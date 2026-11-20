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

/* global __ */

   // "Group View" is translated server-side (glpi:escalade:i18n meta tag): JS locales load asynchronously.
   var escaladeGroupViewIsActive = function() {
      var locales = $('meta[name="glpi:escalade:i18n"]').attr('content');
      var label = (locales ? JSON.parse(locales).group_view : null) || __('Group View');
      return $('.nav-link.active').filter(function() {
         return $(this).text().indexOf(label) !== -1;
      }).length > 0;
   };

   // Append the lists once the masonry grid of the tab is ready (it may not be when the response arrives).
   var escaladeAppendWhenGridReady = function(text, attempts) {
      // The call is asynchronous: the user may have left the Group View tab meanwhile.
      if (!escaladeGroupViewIsActive()) {
         return;
      }
      var target_masnry = $(".masonry_grid:visible").first();
      var msnry = window['msnry_' + target_masnry.prop('id')];
      if (target_masnry.length === 0 || msnry === undefined) {
         if (attempts > 0) {
            setTimeout(function() {
               escaladeAppendWhenGridReady(text, attempts - 1);
            }, 25);
         }
         return;
      }
      if (target_masnry.find('.escalade-appended').length > 0) {
         return;
      }
      target_masnry.append(text);
      msnry.appended(target_masnry.find('.escalade-appended'));
   };

   $(document).ready(function() {
      // intercept tabs changes: send the request at once, only the append waits for the grid
      $(document).on('glpi.tab.loaded', function(event) {
         if (!escaladeGroupViewIsActive()) {
            return;
         }
         $.ajax({ type: "GET",
            url: CFG_GLPI.root_doc + '/plugins/escalade/ajax/central.php',
            success : function(text)
            {
               if (text !== "") {
                  // up to 3 s
                  escaladeAppendWhenGridReady(text, 120);
               }
            }
         });
      });
   });

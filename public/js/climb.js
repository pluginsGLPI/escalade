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

(function () {
   'use strict';

   // Elements are marked once initialised: this file is loaded after each render of the markup.
   function initClimbForms() {
      $('form[data-escalade-climb-form]:not([data-escalade-ready])').each(function () {
         var form = $(this).attr('data-escalade-ready', '1');
         var inputs = $('#itil-form').serializeArray();
         $.each(inputs, function (i, input) {
            if (input.name != '_actors') {
               $('<input>', {
                  type: 'hidden',
                  name: 'ticket_details[' + input.name + ']',
                  value: input.value,
               }).appendTo(form);
            }
         });
      });
   }

   function initFullHistoryLinks() {
      $('a[data-escalade-full-history]:not([data-escalade-ready])')
         .attr('data-escalade-ready', '1')
         .on('click', function () {
            full_history.show();
         });
   }

   function initOpenerLinks() {
      $('a[data-escalade-opener-link]:not([data-escalade-ready])')
         .attr('data-escalade-ready', '1')
         .on('click', function () {
            self.opener.location.href = this.getAttribute('href');
            self.close();
         });
   }

   function init() {
      initClimbForms();
      initFullHistoryLinks();
      initOpenerLinks();
   }

   $(init);
})();

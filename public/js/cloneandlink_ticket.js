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

/* global __, getUrlParameter */
   var plugin_url = CFG_GLPI.root_doc + '/plugins/escalade';

   addCloneLink = function() {

      //only in edit form
      if (getUrlParameter('id') == undefined) {
         return;
      }

      //delay the execution (ajax requestcomplete event fired before dom loading)
      setTimeout( function () {
         if ($("#cloneandlink_ticket").length > 0) { return; }
         // Translated server-side (glpi:escalade:i18n meta tag): JS locales load asynchronously.
         var locales = $('meta[name="glpi:escalade:i18n"]').attr('content');
         var locale_cloneandlink = (locales ? JSON.parse(locales).clone_and_link : null) || __('Clone and link', 'escalade');
         var duplicate_html = $('<button>', {
            id: 'cloneandlink_ticket',
            'class': 'btn btn-sm btn-ghost-secondary ms-auto',
            title: locale_cloneandlink
         })
            .append($('<i>', {'class': 'ti ti-copy me-1'}))
            .append(document.createTextNode(locale_cloneandlink));

         $("#linked_tickets-heading .accordion-button")
            .append(duplicate_html);
         addOnclick();

      }, 100);
   }

   addOnclick = function() {
      //onclick event on new buttons
      $('#cloneandlink_ticket').on('click', function() {

         var tickets_id = getUrlParameter('id');

         $.ajax({
            url:     plugin_url+'/ajax/cloneandlink_ticket.php',
            data:    { 'tickets_id': tickets_id },
            success: function(response, opts) {
               var res = JSON.parse(response);

               if (res.success == false) {
                  return false;
               }
               var url_newticket = 'ticket.form.php?id='+res.newID;

               //change to on new ticket created
               window.location.href = url_newticket;
            }
         });
      });
   }

   $(document).on('glpi.tab.loaded', function() {
      addCloneLink();
   });

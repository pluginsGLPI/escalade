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
if ($_SESSION['glpiactiveprofile']['interface'] == "central"
   && (Session::haveRight("ticket", CREATE)
      || Session::haveRight("ticket", UPDATE))) {

   $locale_group_view = __('Group View');

   $JS = <<<JAVASCRIPT

   var plugin_url = CFG_GLPI.root_doc+"/"+GLPI_PLUGINS_PATH.escalade;

   var doOnTabChange = function() {
      //intercept ajax load of group tab
      $(document).ajaxComplete(function(event, jqxhr, option) {
         if (option.url == plugin_url+'/ajax/central.php') {
            return;
         }

         if (option.url.indexOf('common.tabs.php') > 0) {
            //delay the execution (ajax requestcomplete event fired before dom loading)
            setTimeout(function () {
               insertEscaladeBlock();
            }, 300);
         }
      });
   }

   var insertEscaladeBlock = function() {
      var selector = ".ui-tabs-panel .tab_cadre_central .top:last" +
         ", .alltab:contains('$locale_group_view') + .tab_cadre_central .top:last";

      // get central list for plugin and insert in group tab
      $(selector).each(function(){
         if (this.innerHTML.indexOf('escalade_block') < 0) {

            //prepare a span element to load new elements
            $(this).prepend("<span id='escalade_block'></span>");

            //ajax request
            $("#escalade_block").load(plugin_url+'/ajax/central.php');
         }
      });
   };

   $(document).ready(function() {
      //try to insert directly (if we are on central group page)
      insertEscaladeBlock();

      // try to intercept tabs changes
      $(".ui-tabs-panel:visible").ready(function() {
         doOnTabChange();
      });
      $("#tabspanel + div.ui-tabs").on("tabsload", function() {
         setTimeout(function() {
            doOnTabChange();
         }, 300);
      });
   });

JAVASCRIPT;
   echo $JS;
}

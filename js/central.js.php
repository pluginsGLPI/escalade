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

   $(document).ready(function() {
      // intercept tabs changes
      $(document).on('glpi.tab.loaded', function(event) {
          setTimeout(() => {
            if ($('.nav-link.active:contains($locale_group_view)').length == 0) {
               return;
            }
            $.ajax({ type: "GET",
               url: CFG_GLPI.root_doc+"/"+GLPI_PLUGINS_PATH.escalade+'/ajax/central.php',
               async: false,
               success : function(text)
               {
                  if (text !== "") {
                     var target_masnry = $(".masonry_grid:visible");
                     target_masnry.append(text);
                     window['msnry_' + target_masnry.prop('id')].appended($(".escalade-appended"));
                  }
               }
            });
          }, 100);
      });
   });

JAVASCRIPT;
   echo $JS;
}

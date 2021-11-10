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

    $locale_actor = __('Actor');

    $JS = <<<JAVASCRIPT

    var plugin_url = CFG_GLPI.root_doc+"/"+GLPI_PLUGINS_PATH.escalade;

    var ticketEscalation = function() {
        var tickets_id = getUrlParameter('id');

        //only in edit form
        if (tickets_id == undefined) {
            return;
        }

        // if escalade block already inserted
        if ($(".escalade_active").get(0)) {
            return;
        }

        $("#actors .form-field:last")
            .addClass('escalade_active')
            .append(
                $('<div></div>').load(
                    plugin_url+'/ajax/history.php',
                    {'tickets_id': tickets_id}
                )
            );
    }

    $(document).ready(function() {
        // only in ticket form
        if (location.pathname.indexOf('ticket.form.php') != 0) {
            $(document).on('glpi.tab.loaded', function() {
                ticketEscalation();
            });
        }
    });

JAVASCRIPT;
    echo $JS;
}
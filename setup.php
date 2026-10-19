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

define('PLUGIN_ESCALADE_VERSION', '3.0.0');
// Minimal GLPI version, inclusive
define("PLUGIN_ESCALADE_MIN_GLPI", "12.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_ESCALADE_MAX_GLPI", "12.0.99");

if (!defined("PLUGIN_ESCALADE_DIR")) {
    define("PLUGIN_ESCALADE_DIR", Plugin::getPhpDir("escalade"));
}

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_escalade()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;


    // Config page
    if (Plugin::isPluginActive('escalade') && isset($_SESSION['glpiactiveprofile']) && Session::haveRight(Config::$rightname, READ)) {
        $PLUGIN_HOOKS['config_page']['escalade'] = 'front/eol_info.php';
    }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_escalade()
{
    return [
        'name'           => __s('Escalade (End Of Life)', 'escalade'),
        'version'        => PLUGIN_ESCALADE_VERSION,
        'author'         => "<a href='http://www.teclib.com'>Teclib'</a>",
        'homepage'       => "https://github.com/pluginsGLPI/escalade",
        'license'        => 'GPLv2+',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_ESCALADE_MIN_GLPI,
                'max' => PLUGIN_ESCALADE_MAX_GLPI,
            ],
        ],
    ];
}

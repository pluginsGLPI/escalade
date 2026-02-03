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

global $CFG_GLPI, $PLUGIN_HOOKS;

define('GLPI_ROOT', __DIR__ . '/../../../');
define('GLPI_LOG_DIR', __DIR__ . '/files/_logs');

define('TU_USER', 'glpi');
define('TU_PASS', 'glpi');
define('GLPI_LOG_LVL', 'DEBUG');

require GLPI_ROOT . '/inc/includes.php';
include_once GLPI_ROOT . '/phpunit/GLPITestCase.php';
include_once GLPI_ROOT . '/phpunit/DbTestCase.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../setup.php';

if (!Plugin::isPluginActive("escalade")) {
    throw new RuntimeException("Plugin escalade is not active in the test database");
}

plugin_init_escalade();

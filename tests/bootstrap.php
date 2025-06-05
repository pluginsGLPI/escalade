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

use Glpi\Cache\CacheManager;
use Glpi\Cache\SimpleCache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

global $CFG_GLPI, $PLUGIN_HOOKS;

ini_set('display_errors', 'On');
error_reporting(E_ALL);

define('GLPI_ROOT', dirname(__DIR__, 3));
define('GLPI_LOG_DIR', GLPI_ROOT . '/files/_logs');

define('TU_USER', 'glpi');
define('TU_PASS', 'glpi');
define('GLPI_LOG_LVL', 'DEBUG');

include(GLPI_ROOT . "/inc/based_config.php");

if (!file_exists(GLPI_CONFIG_DIR . '/config_db.php')) {
    die("\nConfiguration file for tests not found\n\nrun: bin/console glpi:database:install --config-dir=tests/config ...\n\n");
}

// Create subdirectories of GLPI_VAR_DIR based on defined constants
foreach (get_defined_constants() as $constant_name => $constant_value) {
    if (
        preg_match('/^GLPI_[\w]+_DIR$/', $constant_name)
        && preg_match('/^' . preg_quote(GLPI_VAR_DIR, '/') . '\//', $constant_value)
    ) {
        is_dir($constant_value) or mkdir($constant_value, 0755, true);
    }
}

//init cache
if (file_exists(GLPI_CONFIG_DIR . DIRECTORY_SEPARATOR . CacheManager::CONFIG_FILENAME)) {
    // Use configured cache for cache tests
    $cache_manager = new CacheManager();
    $GLPI_CACHE = $cache_manager->getCoreCacheInstance();
} else {
    // Use "in-memory" cache for other tests
    $GLPI_CACHE = new SimpleCache(new ArrayAdapter());
}

require GLPI_ROOT . '/inc/includes.php';
include_once GLPI_ROOT . '/phpunit/GLPITestCase.php';
include_once GLPI_ROOT . '/phpunit/DbTestCase.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../setup.php';
plugin_init_escalade();

if (!file_exists(GLPI_LOG_DIR . '/php-errors.log')) {
    file_put_contents(GLPI_LOG_DIR . '/php-errors.log', '');
}

if (!file_exists(GLPI_LOG_DIR . '/sql-errors.log')) {
    file_put_contents(GLPI_LOG_DIR . '/sql-errors.log', '');
}

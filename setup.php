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

use Glpi\Plugin\Hooks;

define('PLUGIN_ESCALADE_VERSION', '2.10.9');
// Minimal GLPI version, inclusive
define("PLUGIN_ESCALADE_MIN_GLPI", "11.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_ESCALADE_MAX_GLPI", "11.0.99");

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
    /** @var DBmysql $DB */
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS, $DB;

    $PLUGIN_HOOKS['csrf_compliant']['escalade'] = true;
    // Plugin::isPluginActive() queries the database while plugins are being initialized:
    // use the list of activated plugins built by core at boot instead.
    if (in_array('escalade', Plugin::getPlugins(), true)) {
        if ($DB->tableExists("glpi_plugin_escalade_configs")) {
            //load config in session
            PluginEscaladeConfig::loadInSession();
            // == Load js scripts ==
            if ((isset($_SESSION['glpiID']) || isCommandLine()) && isset($_SESSION['glpi_plugins']['escalade']['config'])) {
                $escalade_config = $_SESSION['glpi_plugins']['escalade']['config'];
                $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'js/function.js';

                // Scripts below are static files: rights and configuration are checked here, once.
                $needs_js_locales = false;
                // Only core pages need these scripts: the plugin own endpoints (ajax/central.php,
                // front/ticket.form.php) also contain the page names but return no HTML page.
                $request_uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
                if (str_contains($request_uri, '/plugins/') || str_contains($request_uri, '/marketplace/')) {
                    $request_uri = '';
                }
                $is_central = Session::getCurrentInterface() === 'central';
                $can_create_ticket = Session::haveRight('ticket', CREATE);
                $can_update_ticket = Session::haveRight('ticket', UPDATE);

                // on central page
                //history and climb feature
                if (
                    str_contains($request_uri, "central.php")
                    && $escalade_config['show_history']
                    && $is_central
                    && ($can_create_ticket || $can_update_ticket)
                ) {
                    $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'js/central.js';
                    $needs_js_locales = true;
                }

                // on ticket page (in edition)
                $remove_btn_options = [
                    'remove_delete_requester_user_btn',
                    'remove_delete_watcher_user_btn',
                    'remove_delete_assign_user_btn',
                    'remove_delete_requester_group_btn',
                    'remove_delete_watcher_group_btn',
                    'remove_delete_assign_group_btn',
                    'remove_delete_assign_supplier_btn',
                ];
                $remove_btn_config = [];
                foreach ($remove_btn_options as $option) {
                    $remove_btn_config[$option] = (bool) ($escalade_config[$option] ?? false);
                }

                if (
                    (str_contains($request_uri, "ticket.form.php") || str_contains($request_uri, "problem.form.php") || str_contains($request_uri, "change.form.php"))
                    && isset($_GET['id'])
                    && $is_central
                    && in_array(false, $remove_btn_config, true)
                ) {
                    //remove btn feature
                    $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'js/remove_btn.js';
                    $PLUGIN_HOOKS[Hooks::ADD_HEADER_TAG]['escalade'][] = [
                        'tag'        => 'meta',
                        'properties' => [
                            'name'    => 'glpi:escalade:remove_btn',
                            'content' => json_encode($remove_btn_config),
                        ],
                    ];
                }

                // on ticket page (in edition)
                if (
                    str_contains($request_uri, "ticket.form.php")
                    && isset($_GET['id'])
                    && $is_central
                ) {
                    //history and climb feature
                    if ($escalade_config['show_history'] && ($can_create_ticket || $can_update_ticket)) {
                        $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'js/escalade.js';
                    }

                    //clone ticket feature
                    if ($escalade_config['cloneandlink_ticket'] && $can_create_ticket && $can_update_ticket) {
                        $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'js/cloneandlink_ticket.js';
                        $needs_js_locales = true;
                    }
                }

                if ($needs_js_locales) {
                    plugin_escalade_add_js_locales();
                }

                Plugin::registerClass('PluginEscaladeGroup_Group', ['addtabon' => 'Group']);
                Plugin::registerClass('PluginEscaladeUser', ['addtabon' => ['User']]);
            }
        }

        // Add css file
        $PLUGIN_HOOKS['add_css']['escalade'][] = 'css/escalade.css';

        // == Ticket modifications
        $PLUGIN_HOOKS['pre_item_update']['escalade'] = [
            'Ticket'       => 'plugin_escalade_pre_item_update',
        ];
        $PLUGIN_HOOKS['item_update']['escalade'] = [
            'Ticket'       => 'plugin_escalade_item_update',
        ];
        $PLUGIN_HOOKS['item_add']['escalade'] = [
            'Group_Ticket' => 'plugin_escalade_item_add_group_ticket',
            'Ticket_User'  => 'plugin_escalade_item_add_user',
            'Ticket'       => 'plugin_escalade_item_add_ticket',
        ];
        $PLUGIN_HOOKS['pre_item_add']['escalade'] = [
            'Group_Ticket' => 'plugin_escalade_pre_item_add_group_ticket',
            'Ticket'       => 'plugin_escalade_pre_item_add_ticket',
            'Ticket_User'  => 'plugin_escalade_pre_item_add_ticket_user',
        ];
        $PLUGIN_HOOKS['post_prepareadd']['escalade'] = [
            'Ticket'       => 'plugin_escalade_post_prepareadd_ticket',
        ];

        $PLUGIN_HOOKS['item_purge']['escalade'] = [
            'User'         => 'plugin_escalade_item_purge',
            'Ticket'       => 'plugin_escalade_item_purge',
        ];
        $PLUGIN_HOOKS['item_add']['escalade']['User'] = 'plugin_escalade_item_add_user';

        //filter group feature
        if ($escalade_config['use_filter_assign_group'] ?? false) {
            $PLUGIN_HOOKS[Hooks::FILTER_ACTORS]['escalade'] = [
                'PluginEscaladeTicket', 'filter_actors',
            ];
        }

        // == Interface links ==
        if (Session::haveRight('config', UPDATE)) {
            $PLUGIN_HOOKS['config_page']['escalade'] = 'front/config.form.php';

            $PLUGIN_HOOKS['menu_toadd']['escalade'] = [
                'admin' => PluginEscaladeConfig::class,
            ];
        }

        $PLUGIN_HOOKS['use_massive_action']['escalade'] = 1;

        // add more target to some notifications
        $PLUGIN_HOOKS['item_add_targets']['escalade']['NotificationTargetPlanningRecall']
         = ['PluginEscaladeNotification', 'addTargets'];
        $PLUGIN_HOOKS['item_action_targets']['escalade']['NotificationTargetPlanningRecall']
         = ['PluginEscaladeNotification', 'getActionTargets'];

        $PLUGIN_HOOKS['item_add_targets']['escalade']['NotificationTargetTicket']
         = ['PluginEscaladeNotification', 'addTargets'];
        $PLUGIN_HOOKS['item_action_targets']['escalade']['NotificationTargetTicket']
         = ['PluginEscaladeNotification', 'getActionTargets'];

        // Add additional events for Ticket notifications
        $PLUGIN_HOOKS['item_get_events']['escalade'] = [
            'NotificationTargetTicket' =>  ['PluginEscaladeNotification', 'getEvents'],
        ];
        $PLUGIN_HOOKS['timeline_answer_actions']['escalade'] = ['PluginEscaladeTicket', 'addToTimeline'];
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
        'name'           => __s("Escalation", "escalade"),
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

/**
 * Expose the labels used by the static scripts, translated server-side:
 * core loads the JS locales asynchronously, so the scripts may run before they are available.
 *
 * Called from plugin_init_escalade(), where core has loaded its own locale but not yet the plugin one
 * (Plugin::load() loads it right after the init function). A post_init hook would avoid loading it here,
 * but core checks the plugin state in database for each post_init hook (2 queries per request).
 *
 * @return void
 */
function plugin_escalade_add_js_locales()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    Plugin::loadLang('escalade');

    $PLUGIN_HOOKS[Hooks::ADD_HEADER_TAG]['escalade'][] = [
        'tag'        => 'meta',
        'properties' => [
            'name'    => 'glpi:escalade:i18n',
            'content' => json_encode([
                'group_view'     => __('Group View'),
                'clone_and_link' => __('Clone and link', 'escalade'),
            ]),
        ],
    ];
}

function plugin_escalade_geturl(): string
{
    /** @var array $CFG_GLPI */
    global $CFG_GLPI;
    return sprintf('%s/plugins/escalade/', $CFG_GLPI['url_base']);
}

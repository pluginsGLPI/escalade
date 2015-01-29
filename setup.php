<?php

// Init the hooks of the plugins -Needed
function plugin_init_escalade() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['escalade'] = true;

   $plugin = new Plugin();
   if (isset($_SESSION['glpiID']) 
      && $plugin->isInstalled('escalade') && $plugin->isActivated('escalade')) {
      //load config in session
      if (TableExists("glpi_plugin_escalade_configs")) {
         PluginEscaladeConfig::loadInSession();

         // == Load js scripts ==
         if (isset($_SESSION['plugins']['escalade']['config'])) {

            if (strpos($_SERVER['REQUEST_URI'], "ticket.form.php") !== false
               || strpos($_SERVER['REQUEST_URI'], "central.php") !== false) {

               //history and climb feature
               if ($_SESSION['plugins']['escalade']['config']['show_history'] 
                  && $_SESSION['plugins']['escalade']['config']['remove_group']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/escalade.js.php';
               }
            }

            if (strpos($_SERVER['REQUEST_URI'], "ticket.form.php") !== false) {
               //remove btn feature
               $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/remove_btn.js.php';

               //clone ticket feature
               if ($_SESSION['plugins']['escalade']['config']['cloneandlink_ticket']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/cloneandlink_ticket.js.php';
               }
               
               //filter group feature
               if ($_SESSION['plugins']['escalade']['config']['use_filter_assign_group']) {
                  $PLUGIN_HOOKS['add_javascript']['escalade'][] = 'scripts/filtergroup.js.php';
               }

            }

            Plugin::registerClass('PluginEscaladeGroup_Group', array('addtabon' => 'Group'));
         }
      }

      $PLUGIN_HOOKS['add_css']            ['escalade'][]          = 'style.css';


      // == Ticket modifications
      $PLUGIN_HOOKS['item_update']        ['escalade']            = array(
         'Ticket'       => 'plugin_escalade_item_update'
      );
      $PLUGIN_HOOKS['item_add']           ['escalade']            = array(
         'Group_Ticket' => 'plugin_escalade_item_add_group_ticket',
         'Ticket_User'  => 'plugin_escalade_item_add_user',
         'Ticket'       => 'plugin_escalade_item_add_ticket'
      );
      $PLUGIN_HOOKS['pre_item_add']       ['escalade']            = array(
         'Group_Ticket' => 'plugin_escalade_pre_item_add_group_ticket',
         'Ticket'       => 'plugin_escalade_pre_item_add_ticket'
      );
      $PLUGIN_HOOKS['post_prepareadd']['escalade'] = array(
         'Ticket'    => 'plugin_escalade_post_prepareadd_ticket'
      );

      // == Interface links ==
      if (Session::haveRight('config', 'w')) {
         //TODO : Es ce que cette partie peut être porté en 0.85 ?
         $config_page = 'front/config.form.php';

         $PLUGIN_HOOKS['config_page']     ['escalade']            = $config_page;
         $PLUGIN_HOOKS['submenu_entry']   ['escalade']['config']  = $config_page;
         $PLUGIN_HOOKS['submenu_entry']   ['escalade']['options']['config']['links']['config']
                  = '/plugins/escalade/'.$config_page;
      }


   }
}

// Get the name and the version of the plugin - Needed
function plugin_version_escalade() {
   return array(
         'name'           => __("Escalation", "escalade"),
         'version'        => "2.0.3",
         'author'         => "<a href='http://www.teclib.com'>Teclib'</a> &
                              <a href='http://www.lefigaro.fr/'>LE FIGARO</a>",
         'homepage'       => "https://forge.indepnet.net/projects/escalade",
         'license'        => 'GPLv2+',
         'minGlpiVersion' => "0.84",
   );
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_escalade_check_prerequisites() {

   if (version_compare(GLPI_VERSION,'0.85','lt') || version_compare(GLPI_VERSION,'0.86','ge')) {
      echo "This plugin requires GLPI >= 0.85 and GLPI < 0.86";
   } else {
      return true;
   }
   return false;
}

// Check configuration process for plugin : need to return true if succeeded
// Can display a message only if failure and $verbose is true
function plugin_escalade_check_config($verbose=false) {
   if (true) { // Your configuration check
      return true;
   }
   if ($verbose) {
      __('Installed / not configured');
   }
   return false;
}

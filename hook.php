<?php
function plugin_escalade_install() {
   global $DB;

   //get version
   $plugin = new Plugin();
   $found = $plugin->find("name = 'escalade'");
   $plugin_escalade = array_shift($found);

   //init migration
   $migration = new Migration($plugin_escalade['version']);

   // == Tables creation (initial installation) ==
   if (! TableExists('glpi_plugin_escalade_histories')) {
      $query = "CREATE TABLE `glpi_plugin_escalade_histories` (
         `id`              INT(11) NOT NULL AUTO_INCREMENT,
         `tickets_id`      INT(11) NOT NULL,
         `groups_id`       INT(11) NOT NULL,
         `date_mod`        DATETIME NOT NULL,
         PRIMARY KEY (`id`),
         KEY `tickets_id` (`tickets_id`),
         KEY `groups_id` (`groups_id`)
      ) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->query($query);
   }

   if (! TableExists('glpi_plugin_escalade_configs')) {
      $query = "CREATE TABLE `glpi_plugin_escalade_configs` (
         `id`                                      INT(11) NOT NULL AUTO_INCREMENT,
         `remove_group`                            INT(11) NOT NULL,
         `remove_delete_group_btn`                 INT(11) NOT NULL,
         `remove_delete_user_btn`                  INT(11) NOT NULL,
         `remove_delete_supplier_btn`              INT(11) NOT NULL,
         `show_history`                            INT(11) NOT NULL,
         `task_history`                            INT(11) NOT NULL,
         `remove_tech`                             INT(11) NOT NULL,
         `solve_return_group`                      INT(11) NOT NULL,
         `reassign_group_from_cat`                 INT(11) NOT NULL,
         `reassign_tech_from_cat`                  INT(11) NOT NULL,
         `cloneandlink_ticket`                     INT(11) NOT NULL,
         `close_linkedtickets`                     INT(11) NOT NULL,
         `use_assign_user_group`                   INT(11) NOT NULL,
         `use_assign_user_group_creation`          INT(11) NOT NULL,
         `use_assign_user_group_modification`      INT(11) NOT NULL,
         `use_filter_assign_group`                 INT(11) NOT NULL,
         `ticket_last_status`                      INT(11) NOT NULL,
         PRIMARY KEY (`id`)
      ) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->query($query);

      $query = "INSERT INTO glpi_plugin_escalade_configs
      VALUES (NULL, 1, 0, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 0, 0, 0, 0, '".Ticket::WAITING."')";
      $DB->query($query);
   }

   // == Update to 1.2 ==
   if (! FieldExists('glpi_plugin_escalade_configs', 'cloneandlink_ticket')) {
      $migration->addField('glpi_plugin_escalade_configs', 'cloneandlink_ticket',
                           'INT(11) NOT NULL',
                           array('after' => 'reassign_tech_from_cat'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }
   if (! FieldExists('glpi_plugin_escalade_configs', 'close_linkedtickets')) {
      $migration->addField('glpi_plugin_escalade_configs', 'close_linkedtickets',
                           'INT(11) NOT NULL',
                           array('after' => 'cloneandlink_ticket'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }

   if (! FieldExists('glpi_plugin_escalade_configs', 'use_assign_user_group')) {
      $migration->addField('glpi_plugin_escalade_configs', 'use_assign_user_group',
                           'INT(11) NOT NULL',
                           array('after' => 'close_linkedtickets'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }
   if (! FieldExists('glpi_plugin_escalade_configs', 'use_assign_user_group_creation')) {
      $migration->addField('glpi_plugin_escalade_configs', 'use_assign_user_group_creation',
                           'INT(11) NOT NULL',
                           array('after' => 'use_assign_user_group'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }
   if (! FieldExists('glpi_plugin_escalade_configs', 'use_assign_user_group_modification')) {
      $migration->addField('glpi_plugin_escalade_configs', 'use_assign_user_group_modification',
                           'INT(11) NOT NULL',
                           array('after' => 'use_assign_user_group_creation'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }
   if (! FieldExists('glpi_plugin_escalade_configs', 'remove_delete_group_btn')) {
      $migration->addField('glpi_plugin_escalade_configs', 'remove_delete_group_btn',
                           'INT(11) NOT NULL',
                           array('after' => 'remove_group'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }
   if (! isIndex("glpi_plugin_escalade_histories", 'tickets_id')
      || ! isIndex("glpi_plugin_escalade_histories", 'groups_id')) {
      $migration->addKey("glpi_plugin_escalade_histories", 'tickets_id', 'tickets_id');
      $migration->addKey("glpi_plugin_escalade_histories", 'groups_id', 'groups_id');
      $migration->migrationOneTable('glpi_plugin_escalade_histories');
   }


   // == Update to 1.3 ==
   if (! FieldExists('glpi_plugin_escalade_configs', 'remove_delete_user_btn')) {
      $migration->addField('glpi_plugin_escalade_configs', 'remove_delete_user_btn',
                           'INT(11) NOT NULL DEFAULT 1',
                           array('after' => 'remove_delete_group_btn'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }
   if (! FieldExists('glpi_plugin_escalade_configs', 'use_filter_assign_group')) {
      $migration->addField('glpi_plugin_escalade_configs', 'use_filter_assign_group',
                           'INT(11) NOT NULL',
                           array('after' => 'use_assign_user_group_modification'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }
   if (! TableExists('glpi_plugin_escalade_groups_groups')) {
      $query = "CREATE TABLE `glpi_plugin_escalade_groups_groups` (
         `id`                                      INT(11) NOT NULL AUTO_INCREMENT,
         `groups_id_source` int(11) NOT NULL DEFAULT '0',
         `groups_id_destination` int(11) NOT NULL DEFAULT '0',
         PRIMARY KEY (`id`)
      ) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      $DB->query($query);
   }

   // Update for 0.84 status
   if (TableExists('glpi_plugin_escalade_configs')) {
      foreach ($DB->request("glpi_plugin_escalade_configs") as $data) {
         switch ($data['ticket_last_status']) {
            case 'solved':
               $status = Ticket::SOLVED;
               break;
            case 'waiting':
               $status = Ticket::WAITING;
               break;
            case 'closed':
               $status = Ticket::CLOSED;
               break;
            case 'assign':
               $status = Ticket::ASSIGNED;
               break;
            case 'new':
               $status = Ticket::INCOMING;
               break;
            case 'plan':
               $status = Ticket::PLANNED;
               break;
            default :
               $status = -1;
               break;
         }
         $query = "UPDATE `glpi_plugin_escalade_configs`
                   SET `ticket_last_status` = '".$status."'
                   WHERE `id` = '".$data['id']."'";
         $DB->query($query);
      }

      $query = "ALTER TABLE `glpi_plugin_escalade_configs` MODIFY `ticket_last_status` INT(11);";
      $DB->query($query);
   }

   // update to 0.85-1.0
   if (FieldExists("glpi_plugin_escalade_configs", "assign_me_ticket")) {

      // assign me ticket feature native in glpi 0.85
      $migration->dropField("glpi_plugin_escalade_configs", "assign_me_ticket");
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }

   // update to 0.90-1.1
   if (! TableExists('glpi_plugin_escalade_users')) {
      $query = "CREATE TABLE `glpi_plugin_escalade_users` (
                  `id` INT(11) NOT NULL AUTO_INCREMENT,
                  `users_id` INT(11) NOT NULL,
                  `use_filter_assign_group` TINYINT(1) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  INDEX `users_id` (`users_id`)
               )
               ENGINE=InnoDB;";
      $DB->query($query);

      include_once(GLPI_ROOT."/plugins/escalade/inc/config.class.php");

      $config = new PluginEscaladeConfig();
      $config->getFromDB(1);
      $default_value = $config->fields["use_filter_assign_group"];

      $user = new User();
      foreach ($user->find() as $data) {
         $query = "INSERT INTO glpi_plugin_escalade_users (`users_id`, `use_filter_assign_group`)
                     VALUES (".$data['id'].", $default_value)";
         $DB->query($query);
      }
   }

   // update to 0.90-1.3
   if (! FieldExists('glpi_plugin_escalade_configs', 'remove_delete_supplier_btn')) {
      $migration->addField('glpi_plugin_escalade_configs', 'remove_delete_supplier_btn',
                           'INT(11) NOT NULL DEFAULT 1',
                           array('after' => 'remove_delete_group_btn'));
      $migration->migrationOneTable('glpi_plugin_escalade_configs');
   }

   return true;
}

function plugin_escalade_uninstall() {
   global $DB;

   //Delete plugin's table
   $tables = array(
      'glpi_plugin_escalade_histories',
      'glpi_plugin_escalade_configs',
      'glpi_plugin_escalade_groups_groups',
      'glpi_plugin_escalade_users',
   );
   foreach ($tables as $table) {
      $DB->query("DROP TABLE IF EXISTS `$table`");
   }

   return true;
}

function plugin_escalade_item_purge($item) {
   global $DB;

   if ($item instanceof User) {
      $DB->query("DELETE FROM glpi_plugin_escalade_users WHERE users_id = ".$item->getID());
   }
   return true;
}

function plugin_escalade_item_update($item) {

   if ($item instanceof Ticket) return PluginEscaladeTicket::item_update($item);
   return true;
}

function plugin_escalade_item_add_user($item) {
   global $DB;

   if ($item instanceof User) {
      $config = new PluginEscaladeConfig();
      $config->getFromDB(1);
      $default_value = $config->fields["use_filter_assign_group"];

      $query = "INSERT INTO glpi_plugin_escalade_users (`users_id`, `use_filter_assign_group`)
                  VALUES (".$item->getID().", $default_value)";
      $DB->query($query);
   }

   if ($item instanceof Ticket_User) {
      //prevent escalade hook to trigger on ticket creation
      if (isset($_SESSION['plugin_escalade']['skip_hook_add_user'])) {
         //unset($_SESSION['plugin_escalade']['skip_hook_add_user']);
         return true;
      }

      //this hook is only for assign
      if ($item->fields['type'] == CommonITILActor::ASSIGN) {
         return PluginEscaladeTicket::item_add_user($item);
      }
   }
   return true;
}

function plugin_escalade_pre_item_add_ticket($item) {
   if ($item instanceof Ticket) {
      $_SESSION['plugin_escalade']['skip_hook_add_user'] = true;
   }
}

function plugin_escalade_item_add_ticket($item) {
   //clean escalade session var after ticket creation
   if ($item instanceof Ticket) {
      unset($_SESSION['plugin_escalade']['skip_hook_add_user']);
      unset($_SESSION['plugin_escalade']['keep_users']);
   }
}

function plugin_escalade_pre_item_add_group_ticket($item) {
   if ($item instanceof Group_Ticket
      && $item->input['type'] == CommonITILActor::ASSIGN) {
      return PluginEscaladeTicket::addHistoryOnAddGroup($item);
   }
   return $item;
}

function plugin_escalade_item_add_group_ticket($item) {
   if ($item instanceof Group_Ticket
      && $item->fields['type'] == CommonITILActor::ASSIGN) {
      return PluginEscaladeTicket::processAfterAddGroup($item);
   }
   return $item;
}


function plugin_escalade_post_prepareadd_ticket ($item) {
   if ($item instanceof Ticket) {
      return PluginEscaladeTicket::assignUserGroup($item);
   }
   return $item;
}

function plugin_escalade_getAddSearchOptions($itemtype) {
   $sopt = array();

   if ($itemtype == 'Ticket') {
         $sopt[1881]['table']         = 'glpi_groups';
         $sopt[1881]['field']         = 'completename';
         $sopt[1881]['datatype']      = 'dropdown';
         $sopt[1881]['name']          = __("Group concerned by the escalation", "escalade");
         $sopt[1881]['forcegroupby']  = true;
         $sopt[1881]['massiveaction'] = false;
         $sopt[1881]['condition']     = 'is_requester';
         $sopt[1881]['joinparams']    = array('beforejoin' =>
                                           array('table' => 'glpi_plugin_escalade_histories',
                                                 'joinparams'
                                                         => array('jointype'  => 'child',
                                                                  'condition' => '')));
   }
   if ($itemtype == 'User') {
      $sopt[2150]['table']         = 'glpi_plugin_escalade_users';
      $sopt[2150]['field']         = 'use_filter_assign_group';
      $sopt[2150]['linkfield']     = 'id';
      $sopt[2150]['datatype']      = 'bool';
      $sopt[2150]['searchtype']    = array('equals');
      $sopt[2150]['name']          = __("Enable filtering on the groups assignment", 'escalade');
      $sopt[2150]['joinparams']    = array('beforejoin'
                                       => array('table' => 'glpi_plugin_escalade_users',
                                                'joinparams' => array('jointype' => 'child',
                                                                     'condition' => '')));
   }
   return $sopt;
}

function plugin_escalade_MassiveActions($itemtype) {

   switch ($itemtype) {
      case 'User':
         return array('PluginEscaladeUser' . MassiveAction::CLASS_ACTION_SEPARATOR . 'use_filter_assign_group' =>
            __("Enable filtering on the groups assignment", 'escalade'));
   }
   return array();
}
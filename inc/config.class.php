<?php

class PluginEscaladeConfig extends CommonDBTM {
   static function canCreate() {
      return true;
   }

   static function canView() {
      return true;
   }

   static function getTypeName($nb = 0) {
      return __("Configuration Escalade plugin", "escalade");
   }

   function showForm($ID, $options = array()) {
      global $CFG_GLPI;
      
      if (!$this->canView()) return false;

      $yesnoall = array(
         0 => __("No"),
         1 => __('First'),
         2 => __('Last')
      );


      $this->check($ID,'r');
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Remove old assign group on new group assign", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo(
         "remove_group", 
         $this->fields["remove_group"], 
         -1, 
         array(
            'on_change' => 'hide_show_history(this.value)'
      ));
      echo "<script type='text/javascript'>
         function hide_show_history(val) {
            if (val == 0) {
               document.getElementById('show_history_td1').style.display = 'none';
               document.getElementById('show_history_td2').style.display = 'none';
               document.getElementById('show_solve_return_group_td1').style.display = 'none';
               document.getElementById('show_solve_return_group_td2').style.display = 'none';
            } else {
               document.getElementById('show_history_td1').style.display = '';
               document.getElementById('show_history_td2').style.display = '';
               document.getElementById('show_solve_return_group_td1').style.display = '';
               document.getElementById('show_solve_return_group_td2').style.display = '';
            }
         }
      </script>";
      echo "</td>";
      $style = "";
      if ($this->fields["remove_group"] == false) $style = "style='display: none !important;'";
      echo "<td id='show_history_td1' $style>";
      echo __("show group assign history visually", "escalade");
      echo "</td>";
      echo "<td id='show_history_td2' $style>";
      Dropdown::showYesNo("show_history", $this->fields["show_history"]);
      echo "</td>";
      echo "</tr>";


      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Escalation history in tasks", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("task_history", $this->fields["task_history"]);
      echo "</td>";
      echo "<td>";
      echo __("Remove technician(s) on escalation", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("remove_tech", $this->fields["remove_tech"]);
      echo "</td>";
      echo "</tr>";


      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Ticket status after an escalation", "escalade");
      echo "</td>";
      echo "<td>";
      self::dropdownGenericStatus(
         "Ticket", "ticket_last_status", $this->fields["ticket_last_status"]);
      echo "</td>";
      echo "<td id='show_solve_return_group_td1' $style>";
      echo __("Assign ticket to intial group on solve ticket", "escalade");
      echo "</td>";
      echo "<td id='show_solve_return_group_td2' $style>";
      Dropdown::showYesNo("solve_return_group", $this->fields["solve_return_group"]);
      echo "</td>";
      echo "</tr>";


      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Assign the technical manager on ticket category change", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("reassign_tech_from_cat", $this->fields["reassign_tech_from_cat"]);
      echo "</td>";
      echo "<td>";
      echo __("Assign the technical groupe on ticket category change", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("reassign_group_from_cat", $this->fields["reassign_group_from_cat"]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Clone tickets", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("cloneandlink_ticket", $this->fields["cloneandlink_ticket"]);
      echo "</td>";
      echo "<td>";
      echo __("Close cloned tickets at the same time", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("close_linkedtickets", $this->fields["close_linkedtickets"]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Use the technician's group", "escalade");
      echo "</td>";
      echo "<td>";
      echo "<table>";
      echo "<tr><td>";
      Dropdown::showFromArray('use_assign_user_group', $yesnoall,
                              array('value' => $this->fields['use_assign_user_group']));
      echo "</td>";
      echo "<td><label for=''>".__("a time of creation", "escalade")."&nbsp;</label>";
      Dropdown::showYesNo("use_assign_user_group_creation", 
                          $this->fields["use_assign_user_group_creation"]);
      echo "</td>";
      echo "<td><label for=''>".__("a time of modification", "escalade")."&nbsp;</label>";
      Dropdown::showYesNo("use_assign_user_group_modification", 
                          $this->fields["use_assign_user_group_modification"]);
      echo "</td>";
      echo "</tr></table>";
      $plugin = new Plugin();
      if ($plugin->isInstalled('behaviors') && $plugin->isActivated('behaviors')) {
         echo "<i>".str_replace('##link##', 
            $CFG_GLPI["root_doc"]."/front/config.form.php?forcetab=PluginBehaviorsConfig%241", 
            __("Nota: This feature (creation part) is duplicate with the <a href='##link##'>Behavior</a>plugin. This last has priority.", 
            "escalade"))."</i>";
      }
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Display delete button of assigned groups", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("remove_delete_group_btn", $this->fields["remove_delete_group_btn"]);
      echo "</td>";
      echo "<td>";
      echo __("Display delete button of assigned users", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("remove_delete_user_btn", $this->fields["remove_delete_user_btn"]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __("Rapid ticket assignment button", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("assign_me_ticket", $this->fields["assign_me_ticket"]);
      echo "</td>";

      echo "<td>";
      echo __("Enable filtering on the groups assignment", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("use_filter_assign_group", $this->fields["use_filter_assign_group"]);
      echo "</td>";
      echo "</tr>";

      $options['candel'] = false;
      $this->showFormButtons($options);
   }

   static function loadInSession() {
      $config = new self;
      $config->getFromDB(1);
      unset($config->fields['id']);
      $_SESSION['plugins']['escalade']['config'] = $config->fields;
   }


   static function dropdownGenericStatus($itemtype, $name, $value = CommonITILObject::INCOMING) {
      $item = new $itemtype();
      $tab = $item->getAllStatusArray(false);

      echo "<select name='$name'>";
      echo "<option value='-1' ".($value == -1 ? " selected " : "").">".
         __("Don't change", "escalade")."</option>";
      foreach ($tab as $key => $val) {
         echo "<option value='$key' ".($value==$key?" selected ":"").">$val</option>";
      }
      echo "</select>";
   }

}
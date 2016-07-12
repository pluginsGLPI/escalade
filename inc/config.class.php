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

      if (! $this->canView()) {
         return false;
      }


      $this->getFromDB($ID);
      $this->check($ID, READ);

      $this->showFormHeader($options);

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_remove_group$rand'>";
      echo __("Remove old assign group on new group assign", "escalade") . "</label></td>";
      echo "<td>";
      Dropdown::showYesNo("remove_group", $this->fields["remove_group"], -1, array(
            'on_change' => 'hide_show_history(this.value)',
            'width' => '25%', //specific width needed (default 80%)
            'rand' => $rand,
      ));
      echo "<script type='text/javascript'>
         function hide_show_history(val) {
            var display = (val == 0) ? 'none' : '';
            document.getElementById('show_history_td1').style.display = display;
            document.getElementById('show_history_td2').style.display = display;
            document.getElementById('show_solve_return_group_td1').style.display = display;
            document.getElementById('show_solve_return_group_td2').style.display = display;
         }
      </script>";
      echo "</td>";

      $style = ($this->fields["remove_group"]) ? "" : "style='display: none !important;'";

      $rand = mt_rand();
      echo "<td id='show_history_td1' $style><label for='dropdown_show_history$rand'>";
      echo __("show group assign history visually", "escalade");
      echo "</label></td>";
      echo "<td id='show_history_td2' $style>";
      Dropdown::showYesNo("show_history", $this->fields["show_history"], -1, array(
         'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";
      echo "</tr>";

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_task_history$rand'>" . __("Escalation history in tasks", "escalade") . "</label></td>";
      echo "<td>";
      Dropdown::showYesNo("task_history", $this->fields["task_history"], -1, array(
         'width' => '25%',
         'rand' => $rand,
      ));
      echo "</td>";

      $rand = mt_rand();
      echo "<td><label for='dropdown_remove_tech$rand'>" . __("Remove technician(s) on escalation", "escalade") .  "</label></td>";
      echo "<td>";
      Dropdown::showYesNo("remove_tech", $this->fields["remove_tech"], -1, array(
         'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";
      echo "</tr>";

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_ticket_last_status$rand'>";
      echo __("Ticket status after an escalation", "escalade") . "</label></td>";
      echo "<td>";
      self::dropdownGenericStatus(
         "Ticket", "ticket_last_status", $rand, $this->fields["ticket_last_status"]);
      echo "</td>";

      $rand = mt_rand();
      echo "<td id='show_solve_return_group_td1' $style><label for='dropdown_solve_return_group$rand'>";
      echo __("Assign ticket to initial group on solve ticket", "escalade");
      echo "</td>";
      echo "<td id='show_solve_return_group_td2' $style>";
      Dropdown::showYesNo("solve_return_group", $this->fields["solve_return_group"], -1, array(
         'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";
      echo "</tr>";

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_reassign_tech_from_cat$rand'>";
      echo __("Assign the technical manager on ticket category change", "escalade");
      echo "</label></td>";
      echo "<td>";
      Dropdown::showYesNo("reassign_tech_from_cat", $this->fields["reassign_tech_from_cat"], -1, array(
         'width' => '25%',
         'rand' => $rand,
      ));
      echo "</td>";

      $rand = mt_rand();
      echo "<td><label for='dropdown_reassign_group_from_cat$rand'>";
      echo __("Assign the technical group on ticket category change", "escalade");
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("reassign_group_from_cat", $this->fields["reassign_group_from_cat"], -1, array(
         'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";
      echo "</tr>";

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_cloneandlink_ticket$rand'>" . __("Clone tickets", "escalade") . "</label></td>";
      echo "<td>";
      Dropdown::showYesNo("cloneandlink_ticket", $this->fields["cloneandlink_ticket"], -1, array(
         'width' => '25%',
         'rand' => $rand,
      ));
      echo "</td>";

      $rand = mt_rand();
      echo "<td><label for='dropdown_close_linkedtickets$rand'>";
      echo __("Close cloned tickets at the same time", "escalade");
      echo "</label></td>";
      echo "<td>";
      Dropdown::showYesNo("close_linkedtickets", $this->fields["close_linkedtickets"], -1, array(
         'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";
      echo "</tr>";

      $yesnoall = array(
            0 => __("No"),
            1 => __('First'),
            2 => __('Last'),
      );

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_use_assign_user_group$rand'>" . __("Use the technician's group", "escalade") . "</label></td>";
      echo "<td>";
      echo "<table>";
      echo "<tr><td>";
      Dropdown::showFromArray('use_assign_user_group', $yesnoall, array(
         'value' => $this->fields['use_assign_user_group'],
         'width' => '74px',
         'rand' => $rand,
      ));
      echo "</td>";

      $rand = mt_rand();
      echo "<td><label for='dropdown_use_assign_user_group_creation$rand'>";
      echo __("a time of creation", "escalade")."</label>";
      Dropdown::showYesNo("use_assign_user_group_creation",
                          $this->fields["use_assign_user_group_creation"], -1, array(
         //'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";

      $rand = mt_rand();
      echo "<td style='padding:0px'><label for='dropdown_use_assign_user_group_modification$rand'>";
      echo __("a time of modification", "escalade")."</label>";
      Dropdown::showYesNo("use_assign_user_group_modification",
                          $this->fields["use_assign_user_group_modification"], -1, array(
         //'width' => '25%',
         'rand' => $rand,
      ));
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

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_remove_delete_group_btn$rand'>";
      echo __("Display delete button of assigned groups", "escalade") . "</td>";
      echo "<td>";
      Dropdown::showYesNo("remove_delete_group_btn", $this->fields["remove_delete_group_btn"], -1, array(
         'width' => '25%',
         'rand' => $rand,
      ));
      echo "</td>";

      $rand = mt_rand();
      echo "<td><label for='dropdown_remove_delete_user_btn$rand'>";
      echo __("Display delete button of assigned users", "escalade") . "</td>";
      echo "<td>";
      Dropdown::showYesNo("remove_delete_user_btn", $this->fields["remove_delete_user_btn"], -1, array(
         'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";
      echo "</tr>";

      $rand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='dropdown_remove_delete_supplier_btn$rand'>";
      echo __("Display delete button of assigned suppliers", "escalade") . "</td>";
      echo "<td>";
      Dropdown::showYesNo("remove_delete_supplier_btn", $this->fields["remove_delete_supplier_btn"], -1, array(
         'width' => '25%',
         'rand' => $rand,
      ));
      echo "</td>";

      $rand = mt_rand();
      echo "<td><label for='dropdown_use_filter_assign_group$rand'>";
      echo __("Enable filtering on the groups assignment", "escalade") . "</td>";
      echo "<td>";
      Dropdown::showYesNo("use_filter_assign_group", $this->fields["use_filter_assign_group"], -1, array(
         'width' => '100%',
         'rand' => $rand,
      ));
      echo "</td>";
      echo "</tr>";

      $options['candel'] = false;
      $options['withtemplate'] = 1;
      $this->showFormButtons($options);
   }

   static function loadInSession() {
      $config = new self();
      $config->getFromDB(1);
      unset($config->fields['id']);

      if (isset($config->fields['use_filter_assign_group']) && $config->fields['use_filter_assign_group']) {
         $user = new PluginEscaladeUser();
         if ($user->getFromDBByQuery("WHERE users_id = '".$_SESSION['glpiID']."'")) {
            $config->fields['use_filter_assign_group'] = $user->fields['use_filter_assign_group'];
         }
      }

      $_SESSION['plugins']['escalade']['config'] = $config->fields;

   }

   static function dropdownGenericStatus($itemtype, $name, $rand, $value = CommonITILObject::INCOMING) {
      $item = new $itemtype();

      $tab[-1] = __("Don't change", "escalade");

      $i = 1;
      foreach ($item->getAllStatusArray(false) as $status) {
         $tab[$i] = $status;
         $i++;
      }

      Dropdown::showFromArray($name, $tab, array(
         'value' => $value,
         'width' => '50%',
         'rand' => $rand,
      ));
   }

}

<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginEscaladeHistory extends CommonDBTM {
   const history_limit = 4;

   static function getFirstLineForTicket($tickets_id) {
      $found = self::getFullHistory($tickets_id);
      if (count($found) == 0) return false;
      else {
         return array_pop($found);
      }
   }

   static function getlastLineForTicket($tickets_id) {
      $found = self::getFullHistory($tickets_id);
      if (count($found) == 0) return false;
      else {
         return array_shift($found);
      }
   }

   static function getFullHistory($tickets_id) {
      $history = new self;
      return $history->find("tickets_id = $tickets_id", "date_mod DESC");
   }


   static function getHistory($tickets_id, $full_history = false) {
      if ($full_history) $plugin_dir = "..";
      else $plugin_dir = "../plugins/escalade";

      $filter_groups_id = array();
      if ($_SESSION['plugins']['escalade']['config']['use_filter_assign_group'] == true) {
      	$groups_groups = new PluginEscaladeGroup_Group;
 			$filter_groups_id = $groups_groups->getGroups($tickets_id);
         $use_filter_assign_group = true;
      } else {
         $use_filter_assign_group = false;
      }

      //get all line for this ticket
      $group = new Group;
      $history = new self;
      $found = $history->find("tickets_id = $tickets_id", "date_mod DESC");
      $nb_histories = count($found);

      //remove first line (current assign)
      $first_group = array_shift($found);

      if ($full_history) {
         //show 1st group
         echo "<div class='escalade_active'>";
         echo "<img src='$plugin_dir/pics/groupes.png' width='20' />";
         if ($group->getFromDB($first_group['groups_id'])) {
            echo $group->getLink(true);
         }
         echo "</div>";
      }

      echo "<div class='escalade'>";
      //parse all lines
      $i = 0;
      foreach ($found as $key => $hline) {
         echo "<div class='escalade_history'>";
         if (!$use_filter_assign_group || isset($filter_groups_id[$hline['groups_id']])) {
	         //up link and image
	         echo "<a href='$plugin_dir/front/climb_group.php?tickets_id="
	            .$tickets_id."&groups_id=".$hline['groups_id'];
	         if ($full_history) {
	            echo "&full_history=true";
	         }
	         echo "' title='".__("Reassign the ticket to group", "escalade")."' class='up_a'>";
	         echo "</a>";
	      } else echo "&nbsp;&nbsp;&nbsp;";
         

         //group link
         echo "<img src='$plugin_dir/pics/groupes.png' width='20' />";
         if ($group->getFromDB($hline['groups_id'])) {
            echo self::showGroupLink($group, $full_history);
         }
         
         echo "</div>";

         $i++;
         if ($i == self::history_limit && !$full_history) break;
      }

      //In case there are more than 10 group changes, a popup can display historical
      if ($nb_histories-1 > self::history_limit && !$full_history) {
         echo "<a href='#' onclick='var w=window.open(\""
            .$plugin_dir."/front/popup_histories.php?tickets_id=".$tickets_id
            ."\" ,\"\", \"height=500, width=250, top=100, left=100, scrollbars=yes\" ); "
            ."w.focus();' title='".__("View full history", "escalade")."'>...</a>";
      }

      echo "</div>";
      
   }

   static function showGroupLink($group, $full_history = false) {
      $link_item = $group->getFormURL();

      if (!$group->can($group->fields['id'],'r')) {
         return $group->getNameID(true);
      }

      $link  = $link_item;
      $link .= (strpos($link,'?') ? '&amp;':'?').'id=' . $group->fields['id'];
      $link .= ($group->isTemplate() ? "&amp;withtemplate=1" : "");

      echo "<a href='$link'";
      if ($full_history) echo " onclick='self.opener.location.href=\"$link\"; self.close();'";
      echo ">".$group->getNameID(true)."</a>";
   }

   static function showCentralList() {
      self::showCentralSpecificList("solved");
      self::showCentralSpecificList("notold");
   }

   static function showCentralSpecificList($type) {
      global $CFG_GLPI, $DB;

      if (!Session::haveRight("show_all_ticket","1")
          && !Session::haveRight("show_assign_ticket","1")
          && !Session::haveRight("create_ticket","1")
          && !Session::haveRight("validate_ticket","1")) {
         return false;
      }

      $groups        = implode("','",$_SESSION['glpigroups']);
      $numrows = 0;
      $is_deleted      = " `glpi_tickets`.`is_deleted` = 0 ";
      

      if ($type == "notold") {
         $title = __("Tickets to follow (climbed)", "escalade");
         $status = CommonITILObject::INCOMING.", ".CommonITILObject::PLANNED.", ".
                   CommonITILObject::ASSIGNED.", ".CommonITILObject::WAITING;

         $search_assign = " `glpi_plugin_escalade_histories`.`groups_id` IN ('$groups')
            AND (`glpi_groups_tickets`.`groups_id` NOT IN ('$groups')
            OR `glpi_groups_tickets`.`groups_id` IS NULL)";

         $query_join = "LEFT JOIN `glpi_plugin_escalade_histories`
            ON (`glpi_tickets`.`id` = `glpi_plugin_escalade_histories`.`tickets_id`)
         LEFT JOIN `glpi_groups_tickets`
            ON (`glpi_tickets`.`id` = `glpi_groups_tickets`.`tickets_id` 
               AND `glpi_groups_tickets`.`type`=2)";
      } else {
         $title = __("Tickets to close (climbed)", "escalade");
         $status = CommonITILObject::SOLVED;
         
         $search_assign = " (`glpi_groups_tickets`.`groups_id` IN ('$groups'))";
         
         $query_join = "LEFT JOIN `glpi_groups_tickets`
            ON (`glpi_tickets`.`id` = `glpi_groups_tickets`.`tickets_id` 
               AND `glpi_groups_tickets`.`type`=2)";
      }


      $query = "SELECT DISTINCT `glpi_tickets`.`id`
      FROM `glpi_tickets`
      LEFT JOIN `glpi_tickets_users`
         ON (`glpi_tickets`.`id` = `glpi_tickets_users`.`tickets_id`)";

      $query .= $query_join;
      


      $query .= "WHERE $is_deleted AND ( $search_assign )
                            AND (`status` IN ($status))".
                            getEntitiesRestrictRequest("AND","glpi_tickets");

      $query  .= " ORDER BY glpi_tickets.date_mod DESC";
      $result  = $DB->query($query);
      $numrows = $DB->numrows($result);
      if (!$numrows) return;

      $query .= " LIMIT 0, 5";
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      //show central list
      if ($numrows > 0) {
         //construct link to ticket list
         $options['reset'] = 'reset';
         $num = 0;
         foreach ($_SESSION['glpigroups'] as $gID) {
            $options['field'][$num]      = 8;

            if ($type == "notold") {
               $options['searchtype'][$num] = 'notequals';
               $options['contains'][$num]   = $gID;
               $options['link'][$num]       = ($num==0?'AND':'OR');
               $num++;

               $options['field'][$num]      = 1881; //see hook.php, function ..._getAddSearchOptions
            }
            $options['searchtype'][$num] = 'equals';
            $options['contains'][$num]   = $gID;
            if ($type == "notold") {
               $options['link'][$num]       = 'AND';
            } else {
               $options['link'][$num]       = ($num==0?'AND':'OR');
            }
            $num++;
            $options['field'][$num]      = 12; // status
            $options['searchtype'][$num] = 'equals';
            $options['contains'][$num]   = $type;
            $options['link'][$num]       = 'AND';
            $num++;
         }

         echo "<table><tr><td class='top' width='450px'>";

         echo "<table class='tab_cadrehov' id='pluginEscaladeCentralList' style='width:420px'>";
         echo "<tr><th colspan='5'>";
         echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".
                         Toolbox::append_params($options,'&amp;')."\">".
                         Html::makeTitle($title, $number, $numrows)."</a>";
         echo "</a>";
         echo "</th></tr>";

         if ($number) {
            echo "<tr><th></th>";
            echo "<th>".__('Requester')."</th>";
            echo "<th>".__('Associated element')."</th>";
            echo "<th>".__('Description')."</th></tr>";
            for ($i = 0 ; $i < $number ; $i++) {
               $ID = $DB->result($result, $i, "id");
               Ticket::showVeryShort($ID, 'Ticket$2');
            }
         }
         echo "</table>";

         echo "</td></tr></table>";
      }

   }
   
}

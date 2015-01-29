<?php 

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginEscaladeGroup_Group extends CommonDBRelation {

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      if ($item->getType()=='Group') {
            return __("Escalation", "escalade");
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      if ($item->getType()=='Group') {
         $PluginEscaladeGroup_Group = new PluginEscaladeGroup_Group();
   		$PluginEscaladeGroup_Group->manageGroup($item->getID());
      }
      return true;
   }   
   

   function manageGroup($groups_id) {
      global $CFG_GLPI;

      $group = new Group;

      if (Session::haveRight('group', 'w')) {
         echo "<form method='post' name='' id='manageGroup' action=\"".$CFG_GLPI['root_doc'] .
            "/plugins/escalade/front/group_group.form.php\">";
      }
      echo "<table width='950' class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='2'>";
      echo "Escalade";
      echo "</th>";
      echo "</tr>";

      $gg_found = $this->find("groups_id_source='$groups_id'");
      if (Session::haveRight('group', 'w')) {
         $groups_id_used = array();
         foreach ($gg_found as $gg) {
            $groups_id_used[] = $gg['groups_id_destination'];
         }

         echo "<tr>";
         echo "<td colspan='2' align='center'>";
         Dropdown::show('Group', array('name' => 'groups_id_destination', 'condition' => "is_assign=1",
                                       'used' => $groups_id_used));

         echo "<input type='hidden' name='groups_id_source' value='".$groups_id."' />";
         echo "&nbsp;<input type='submit' class='submit' name='addgroup' value='"._sx('button','Add')."'/>";

         echo "</td>";
         echo "</tr>";
      }
      
      foreach ($gg_found as $gg_id => $gg) {
         $group->getFromDB($gg['groups_id_destination']);
         echo "<tr class='tab_bg_1'>";
         echo "<td width='30'>";
         if (Session::haveRight('group', 'w')) {
            echo "<input type='checkbox' name='delgroup[]' value='$gg_id' />";
         }
         echo "</td>";
         echo "<td>";
         echo $group->getLink(true);
         echo "</td>";
         echo "</tr>";
      }
      
      echo "</table>";
      if (Session::haveRight('group', 'w')) {
         Html::openArrowMassives("manageGroup", true);
         Html::closeArrowMassives(array('deleteitem' => _sx('button','Delete permanently')));
      }
      Html::closeForm();
   }

   function getGroups($ticket_id) {
      $groups = $user_groups = $ticket_groups = array();

      // get groups for user connected
      $tmp_user_groups  = Group_User::getUserGroups($_SESSION['glpiID']);
      foreach ($tmp_user_groups as $current_group) {
         $user_groups[$current_group['id']] = $current_group['id'];
         $groups[$current_group['id']] = $current_group['id'];
      }

      // get groups already assigned in the ticket
      if($ticket_id > 0) {
         $ticket = new Ticket();
         $ticket->getFromDB($ticket_id);
         foreach($ticket->getGroups(CommonITILActor::ASSIGN) as $current_group) {
            $ticket_groups[$current_group['groups_id']] = $current_group['groups_id'];
         }
      } 

      // To do an escalation, the user must be in a group currently assigned to the ticket
      // or no group is assigned to the ticket
      // TODO : matching with "view all tickets (yes/no) option in profile user"
      if(!empty($ticket_groups) && count(array_intersect($ticket_groups, $user_groups)) == 0) {
         return array();
      }

      //get all group which we can climb
      $group_group = $this->find("groups_id_source IN (".implode(", ", $groups).")");
      foreach ($group_group as $current_group) {
         $groups[$current_group['groups_id_destination']] = $current_group['groups_id_destination'];
      }

      //remove already assigned groups
      if (!empty($ticket_groups)) {
         $groups = array_diff_assoc($groups, $ticket_groups);
      }

      //add name to returned groups and remove non assignable groups
      $group_obj = new Group;
      foreach ($groups as $groups_id => &$groupname) {
         $group_obj->getFromDB($groups_id);

         //check if we can assign this group
         if ($group_obj->fields['is_assign'] == 0) {
            unset($groups[$groups_id]);
            continue;
         }

         //add name
         $groupname = $group_obj->fields['name'];
      }

      //sort by group name (and keep associative index)
      asort($groups);

      return $groups;
   }

}

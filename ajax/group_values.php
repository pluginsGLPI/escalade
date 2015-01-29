<?php 
$AJAX_INCLUDE = 1;
include ("../../../inc/includes.php");
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();
Session::checkLoginUser();

$ticket_id = (isset($_REQUEST['ticket_id'])) ? $_REQUEST['ticket_id'] : 0;

echo "<option value='0'>".Dropdown::EMPTY_VALUE."</option>";

$PluginEscaladeGroup_Group = new PluginEscaladeGroup_Group();
foreach ($PluginEscaladeGroup_Group->getGroups($ticket_id) as $groups_id => $groups_name) {
   echo "<option value='$groups_id'>$groups_name</option>";
}


?>
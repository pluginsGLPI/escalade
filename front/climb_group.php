<?php
include ("../../../inc/includes.php");

if (!isset($_REQUEST['tickets_id'])
   || !isset($_REQUEST['groups_id'])) {
   Html::displayErrorAndDie(__("missing parameters", "escalade"));
}

$full_history = false;
if (isset($_REQUEST['full_history'])) $full_history = true;

PluginEscaladeTicket::climb_group($_REQUEST['tickets_id'], $_REQUEST['groups_id'], $full_history);


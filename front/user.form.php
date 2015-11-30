<?php

include ("../../../inc/includes.php");

$user = new PluginEscaladeUser();

//Note : no Log is show in User

if (isset($_POST["add"])) {
   $user->add($_POST);
} elseif (isset($_POST["update"])) {
	$user->update($_POST);
}

Html::back();
<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central") {
   
   $locale_actor = __('Actor');
   $esc_config = $_SESSION['plugins']['escalade']['config'];
   
   $remove_delete_group_btn = "true";
   if (isset($esc_config['remove_delete_group_btn'])
       && $esc_config['remove_delete_group_btn']) {
      $remove_delete_group_btn = "false";
   }
   
   $remove_delete_user_btn = "true";
   if (isset($esc_config['remove_delete_user_btn'])
       && $esc_config['remove_delete_user_btn']) {
      $remove_delete_user_btn = "false";
   }

	$JS = <<<JAVASCRIPT
	var removeOnButtons = function(str) {
   	$("table:contains('$locale_actor') td:last-child a[onclick*="+str+"]").remove();
	}
	
	// only in ticket form
   if (location.pathname.indexOf('ticket.form.php') > 0) {
      $(document).ready(function() {
         //delay the execution (ajax requestcomplete event fired before dom loading)
         setTimeout( function () {
            //remove "delete" group buttons
            if ({$remove_delete_group_btn}) {
               removeOnButtons("group_ticket");
            }
   
            //remove "delete" user buttons
            if ({$remove_delete_user_btn}) {
               removeOnButtons("ticket_user");
            }
         }, 300);

      });
   }
JAVASCRIPT;
   echo $JS;
}
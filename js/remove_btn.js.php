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

   $remove_delete_supplier_btn = "true";
   if (isset($esc_config['remove_delete_supplier_btn'])
       && $esc_config['remove_delete_supplier_btn']) {
      $remove_delete_supplier_btn = "false";
   }

   $JS = <<<JAVASCRIPT
   var removeDeleteButtons = function(str) {
      $("table:contains('$locale_actor') td:last-child a[onclick*="+str+"], \
         .tab_actors .actor-bloc:last a[onclick*="+str+"]")
            .remove();
   }

   var removeAllDeleteButtons = function() {
      //remove "delete" group buttons
      if ({$remove_delete_group_btn}) {
         removeDeleteButtons("group_ticket");
      }

      //remove "delete" user buttons
      if ({$remove_delete_user_btn}) {
         removeDeleteButtons("ticket_user");
      }

      //remove "delete" supplier buttons
      if ({$remove_delete_supplier_btn}) {
         removeDeleteButtons("supplier_ticket");
      }
   }

   $(document).ready(function() {
      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0) {
         $(".ui-tabs-panel:visible").ready(function() {
            removeAllDeleteButtons();
         })

         $("#tabspanel + div.ui-tabs").on("tabsload", function() {
            setTimeout(function() {
               removeAllDeleteButtons();
            }, 300);
         });
      }
   });
JAVASCRIPT;
   echo $JS;
}
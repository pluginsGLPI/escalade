<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central" 
   && (Session::haveRight("ticket", CREATE) 
      || Session::haveRight("ticket", UPDATE))) {
   
   $locale_actor = __('Actor');

   $JS = <<<JAVASCRIPT

   var ticketEscalation = function() {
      var tickets_id = getUrlParameter('id');

      //only in edit form
      if (tickets_id == undefined) {
         return;
      }

      // if escalade block already inserted
      if ($(".escalade_active").length > 0) {
         return;
      }

      // prepare jquery selector to find assign block
      var assign_selector = "table:contains('$locale_actor') td:last, \
                           .tab_actors .actor-bloc:last";
      
      //set active group in red
      $(assign_selector).find("a[href*=group]")
         .addClass('escalade_active');

      //add new histories in assign actor
      $.ajax({
         type:    "POST",
         url:     '../plugins/escalade/ajax/history.php',
         data:    {'tickets_id': tickets_id},
         success: function(response, opts) {
            if ($(".escalade_active:last").length > 0) {
               $(assign_selector).find("a[href*=group]")
                  .last()
                  .after(response);
            } else {
               $(assign_selector)
                  .append(response);
            }
            
         }
      });
   }
   
   $(document).ready(function() {
      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') != 0) {
         $(".ui-tabs-panel:visible").ready(function() {
            ticketEscalation();
         })

         $("#tabspanel + div.ui-tabs").on("tabsload", function() {
            setTimeout(function() {
               ticketEscalation();
            }, 300);
         });
      }
   });

JAVASCRIPT;
      echo $JS;
}
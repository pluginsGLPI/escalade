<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if (isset($_SESSION['glpiactiveprofile'])
   && $_SESSION['glpiactiveprofile']['interface'] == "central"
   && Session::haveRight("ticket", CREATE)
   && Session::haveRight("ticket", UPDATE)
   ) {
   
   $locale_assignme = __("Assign me this ticket", "escalade");
   $locale_assignto = __("Assigned to");

   $JS = <<<JAVASCRIPT
   function getUrlParameter(sParam) {
       var sPageURL = window.location.search.substring(1);
       var sURLVariables = sPageURL.split('&');
       for (var i = 0; i < sURLVariables.length; i++) {
           var sParameterName = sURLVariables[i].split('=');
           if (sParameterName[0] == sParam) {
               return sParameterName[1];
           }
       }
   }
   
   $(document).ready(function() {

      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0) {

         var tickets_id = getUrlParameter('id');

         //only in edit form
         if (tickets_id == undefined) return;

         var assign_me_html = "&nbsp;<img src='../plugins/escalade/pics/assign_me.png' "+
         "alt='$locale_assignme' width='20'"+
         "title='$locale_assignme' class='pointer' id='assign_me_ticket'>";
         Ext.select("th:contains('$locale_assignto') > img").insertHtml('afterEnd', assign_me_html);

         //onclick event on new buttons
         Ext.get('assign_me_ticket').on('click', function() {
            Ext.Ajax.request({
               url:'../plugins/escalade/ajax/assign_me.php?tickets_id='+tickets_id,
               success: function(response, opts) {
                  Ext.select('form[name=form_ticket]').insertHtml('afterBegin', "<input type='hidden' name='update' />");
                  Ext.select('form[name=form_ticket]').insertHtml('beforeEnd', "<input type='hidden' name='status' value='2' />");
                  document.form_ticket.submit();
               }
            });
         });
      }
   });
JAVASCRIPT;
   echo $JS;
}
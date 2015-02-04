<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central"
   && Session::haveRight("ticket", CREATE) 
      && Session::haveRight("ticket", UPDATE) 
   ) {
   
   $locale_cloneandlink  = __("Clone and link", "escalade");
   $locale_linkedtickets = _n('Linked ticket', 'Linked tickets', 2);
   
   //TODO : Add this to plugins /locales/
   $locale_pleasewait    = __("Please wait..."); //Not defined.

   $JS = <<<JAVASCRIPT
   function addCloneLink() {
      if ($("#cloneandlink_ticket").length > 0) { return; }
      //delay the execution (ajax requestcomplete event fired before dom loading)
      setTimeout( function () {
         var duplicate_html = "&nbsp;<img src='../plugins/escalade/pics/cloneandlink_ticket.png' "+
            "alt='$locale_cloneandlink' " + 
            "title='$locale_cloneandlink' class='pointer' id='cloneandlink_ticket'>";
         $("th:contains('$locale_linkedtickets')>img").after(duplicate_html);
         addOnclick();
      }, 100);
   }
   
   function addOnclick() {
         //onclick event on new buttons
         $('#cloneandlink_ticket').on('click', function() { //Possibilité d'utiliser .live(
     
            var tickets_id = getUrlParameter('id');
            
            // show a wait message during all Ajax requests
            /*
            Ext.Ajax.on('beforerequest', function() {
               Ext.getBody().mask('$locale_pleasewait', 'loading')
               Ext.getBody().showSpinner;
            }, Ext.getBody());
            Ext.Ajax.on('requestcomplete', Ext.getBody().unmask, Ext.getBody());
            Ext.Ajax.on('requestexception', Ext.getBody().unmask, Ext.getBody());
            */

            //call PluginVillejuifTicket::duplicate (AJAX)
            $.ajax({
               url:'../plugins/escalade/ajax/cloneandlink_ticket.php',
               data: { 'tickets_id': tickets_id },
               success: function(response, opts) {
                  var res = JSON.parse(response);
                  
                  if (res.success == false) {
                     //console.log(res);
                     return false;
                  }
                  var url_newticket = 'ticket.form.php?id='+res.newID;

                  //change to on new ticket created
                  window.location.href = url_newticket;
               }
            });
         });
   }
   
   $(document).ajaxStop(function( event,request, settings ) {
      
      var tickets_id = getUrlParameter('id');

      //only in edit form
      if (tickets_id == undefined) return;
      
      addCloneLink();
   });
   
   $(document).ready(function() {
   
      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0) {

         var tickets_id = getUrlParameter('id');

         //only in edit form
         if (tickets_id == undefined) return;
         

         // == TICKET DUPLICATE ==
         
         //TODO : Add test if no exist
         //addCloneLink();
      }
   });
JAVASCRIPT;
   echo $JS;
}
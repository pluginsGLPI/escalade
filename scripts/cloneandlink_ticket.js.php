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
   $locale_pleasewait    = __("Please wait...");

   $JS = <<<JAVASCRIPT
   $(document).ready(function() {

      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0) {

         var tickets_id = getUrlParameter('id');

         //only in edit form
         if (tickets_id == undefined) return;

         // == TICKET DUPLICATE ==
         var duplicate_html = "&nbsp;<img src='../plugins/escalade/pics/cloneandlink_ticket.png' "+
         "alt='$locale_cloneandlink ' " + 
         "title='$locale_cloneandlink ' class='pointer' id='cloneandlink_ticket'>";
         //Ext.select("th:contains('$locale_linkedtickets') > img").insertHtml('afterEnd', duplicate_html);
         console.log("afterEnd -> append()");
         $("th:contains('$locale_linkedtickets') > img").append(duplicate_html);

         //onclick event on new buttons
         var el = $('cloneandlink_ticket');
         el.on('click', function() {

            // show a wait message during all Ajax requests
            Ext.Ajax.on('beforerequest', function() { 
               Ext.getBody().mask('$locale_pleasewait', 'loading')
               Ext.getBody().showSpinner;
            }, Ext.getBody());
            Ext.Ajax.on('requestcomplete', Ext.getBody().unmask, Ext.getBody());
            Ext.Ajax.on('requestexception', Ext.getBody().unmask, Ext.getBody());

            //call PluginVillejuifTicket::duplicate (AJAX)
            $.ajax({
               url:'../plugins/escalade/ajax/cloneandlink_ticket.php',
               data: { 'tickets_id': tickets_id },
               success: function(response, opts) {
                  var res = JSON.parse(response.responseText);
                  //var res = Ext.decode(response.responseText);
                  
                  if (res.success == false) {
                     //console.log(res);
                     return false;
                  }
                  var url_newticket = 'ticket.form.php?id='+res.newID;

                  //open popup on new ticket created
                  window.location.href = url_newticket;
               }
            });
         })
      }
   });
JAVASCRIPT;
   echo $JS;
}
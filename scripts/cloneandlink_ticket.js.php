<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

$locale_cloneandlink  =  __("Clone and link", "escalade");
$locale_linkedtickets =  _n('Linked ticket', 'Linked tickets', 2);
$locale_pleasewait    =  __("Please wait...");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central"
   && $_SESSION['glpiactiveprofile']['create_ticket'] == true
   && $_SESSION['glpiactiveprofile']['update_ticket'] == true
   ) {

   $JS = <<<JAVASCRIPT
   Ext.onReady(function() {

      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0) {

         // separating the GET parameters from the current URL
         var getParams = document.URL.split("?");
         // transforming the GET parameters into a dictionnary
         var url_params = Ext.urlDecode(getParams[getParams.length - 1]);
         // get tickets_id
         var tickets_id = url_params['id'];

         //only in edit form
         if(tickets_id == undefined) return;

         //remove #
         tickets_id = parseInt(tickets_id);
         

         // == TICKET DUPLICATE ==
         var duplicate_html = "&nbsp;<img src='../plugins/escalade/pics/cloneandlink_ticket.png' "+
         "alt='$locale_cloneandlink ' "+
         "title='$locale_cloneandlink ' class='pointer' id='cloneandlink_ticket'>";
         Ext.select('th:contains($locale_linkedtickets) > img').insertHtml('afterEnd', duplicate_html);

         //onclick event on new buttons
         Ext.get('cloneandlink_ticket').on('click', function() {

            // show a wait message during all Ajax requests
            Ext.Ajax.on('beforerequest', function() { 
               Ext.getBody().mask('$locale_pleasewait', 'loading')
               Ext.getBody().showSpinner;
            }, Ext.getBody());
            Ext.Ajax.on('requestcomplete', Ext.getBody().unmask, Ext.getBody());
            Ext.Ajax.on('requestexception', Ext.getBody().unmask, Ext.getBody());

            //call PluginVillejuifTicket::duplicate (ajax)
            Ext.Ajax.request({
               url:'../plugins/escalade/ajax/cloneandlink_ticket.php',
               params: { 'tickets_id': tickets_id },
               success: function(response, opts) {
                  var res = Ext.decode(response.responseText);
                  if (res.success == false) {
                     //console.log(res);
                     return false;
                  }
                  var url_newticket = 'ticket.form.php?id='+res.newID;

                  //open popup on new ticket created
                  window.location.href=url_newticket;
               }
            });
         })
      }
   });
JAVASCRIPT;
   echo $JS;
}
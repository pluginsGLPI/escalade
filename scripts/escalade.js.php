<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

$locale_actor = __('Actor');
$locale_pending = __('Tickets on pending status');
$locale_yourticketstoclose = __('Your tickets to close');

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central") {
   if ($_SESSION['glpiactiveprofile']['create_ticket'] == true
      || $_SESSION['glpiactiveprofile']['update_ticket'] == true
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


         // == TICKET ESCALATION ==

         //set active group in red
         Ext.select("table:contains($locale_actor) tr:last-child td:last-child a[href*=group]")
         .addClass('escalade_active');


         //add new histories in assign actor
         Ext.Ajax.request({
            url: '../plugins/escalade/ajax/history.php',
            params: {
               'tickets_id': tickets_id
            },
            success: function(response, opts) {
               var history = response.responseText;

               //$locale_actor = Actors
               var g_assign_bloc = Ext.select(
                  // "table:contains($locale_actor) tr:last-child td:last-child a[href*=group.form.php]"
                  ".escalade_active:last-child"
               );
               var assign_bloc = Ext.select(
                  "table:contains($locale_actor) tr:last-child td:last-child"
               );

               if (g_assign_bloc.elements.length == 0) {
                  assign_bloc.insertHtml("beforeEnd", history);
               } else {
                  g_assign_bloc.insertHtml("afterEnd", history);
               }
               
            }
         });
      }



      // only on central page
      if (location.pathname.indexOf('central.php') > 0) {
         Ext.Ajax.on('requestcomplete', function(conn, response, option) {
            
            //intercept ajax load of group tab 
            if (option.url.indexOf('common.tabs.php') > 0 
               && (
                  option.params.indexOf("Central$2") > 0
                  || option.params.indexOf("-1") > 0
               )) {

               //delay the execution (ajax requestcomplete event fired before dom loading)
               setTimeout( function () {
                  //if loading of new element already done, return;
                  //if (Ext.select("#escalade_block").elements.length > 0) return;

                  suffix = "";

                  //get central list for plugin and insert in group tab
                  var selector = "div[id^=Central][id$=2] .tab_cadre_central > tbody > tr > td:nth(2)";
                  selector += ", div.alltab:contains(groupe) + .tab_cadre_central > tbody > tr > td:nth(2)";
                  Ext.select(selector).each(function(el){
                     if (el.dom.innerHTML.indexOf('escalade_block') < 0) {

                        if (option.params.indexOf("-1") > 0) {
                           suffix = "_all";
                        }

                        //prepare a span element to load new elements
                        el.insertHtml('afterBegin', "<span id='escalade_block"+suffix+"'>test</span>");

                        //load html in this span (with execution on javascript)
                        Ext.get("escalade_block"+suffix).load({
                           url:'../plugins/escalade/ajax/central.php',scripts:true
                        });
                     }
                  });
               }, 300);               
            }
         }, this);
      }




   });
JAVASCRIPT;
      echo $JS;
   }
}
<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central") {
   if (Session::haveRight("ticket", CREATE) 
      || Session::haveRight("ticket", UPDATE) 
   ) {
   
   $locale_actor = __('Actor');
   $locale_pending = __('Tickets on pending status');
   $locale_yourticketstoclose = __('Your tickets to close');

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
   
   //get central list for plugin and insert in group tab
   function getSelectorCentralList() {
      var selector = "div[id^=Central][id$=2] .tab_cadre_central > tbody > tr > td:nth(2)";
      selector += ", div.alltab:contains(groupe) + .tab_cadre_central > tbody > tr > td:nth(2)";
      return selector;
   }
   
   function doInTicketForm() {
      
      var tickets_id = getUrlParameter('id');

      //only in edit form
      if (tickets_id == undefined) return;

      // == TICKET ESCALATION ==
      
      //set active group in red
      $("table:contains('$locale_actor') tr:last-child td:last-child a[href*=group]").addClass('escalade_active');


      //add new histories in assign actor
      Ext.Ajax.request({
         url: '../plugins/escalade/ajax/history.php',
         params: {
            'tickets_id': tickets_id
         },
         success: function(response, opts) {
            var history = response.responseText;

            var g_assign_bloc = $(
               // "table:contains($locale_actor) tr:last-child td:last-child a[href*=group.form.php]"
               ".escalade_active:last-child"
            );
            var assign_bloc = $("table:contains($locale_actor) tr:last-child td:last-child");

            if (g_assign_bloc.elements.length == 0) {
               assign_bloc.insertHtml("beforeEnd", history);
            } else {
               g_assign_bloc.insertHtml("afterEnd", history);
            }
            
         }
      });
   }
   
   $(document).ready(function() {

      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0) {
         doInTicketForm();
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
                  
                  var selector = getSelectorCentralList();
                  
                  Ext.select(selector).each(function(el) {
                     if (el.dom.innerHTML.indexOf('escalade_block') < 0) {

                        if (option.params.indexOf("-1") > 0) {
                           suffix = "_all";
                        }

                        //prepare a span element to load new elements
                        el.insertHtml('afterBegin', "<span id='escalade_block"+suffix+"'>test</span>");

                        //load html in this span (with execution on javascript)
                        Ext.get("escalade_block"+suffix).load({
                           url:'../plugins/escalade/ajax/central.php',
                           scripts:true
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
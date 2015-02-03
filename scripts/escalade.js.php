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

   $JS = <<<JAVASCRIPT
   // get central list for plugin and insert in group tab
   function getSelectorCentralList() {
      var selector = "div[id^=Central][id$=2] .tab_cadre_central > tbody > tr > td:nth(2)";
      selector += ", div.alltab:contains('groupe') + .tab_cadre_central > tbody > tr > td:nth(2)";
      return selector;
   }
   
   function ticketEscalation() {
      var url = '../plugins/escalade/ajax/history.php';
      
      //set active group in red
      $("table:contains('$locale_actor') td:last-child a[href*=group]").addClass('escalade_active');

      //add new histories in assign actor
      $.ajax({
         type: "POST",
         url: url,
         data: {'tickets_id': tickets_id},
         success: function(response, opts) {
            if ($(".escalade_active:last").length > 0) {
               $(".escalade_active:last").after(response);
            } else {
               //OLD : assign_bloc.insertHtml("beforeEnd", response.responseText);
               $("table:contains('$locale_actor') td:last-child").append(response);
            }
            
         }
      });
   }
   
   $(document).ready(function() {

      // only in ticket form
      if (location.pathname.indexOf('ticket.form.php') > 0) {
      
         var tickets_id = getUrlParameter('id');
         
         //only in edit form
         if (tickets_id == undefined) return;
         
         setTimeout(function() {
            ticketEscalation();
         }, 300);
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

                        //load HTML in this Span (with execution on Javascript)
                        $("#escalade_block"+suffix).load('../plugins/escalade/ajax/central.php');
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
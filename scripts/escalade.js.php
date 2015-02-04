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
   $locale_group_view = __('Group View');

   $JS = <<<JAVASCRIPT
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
   
   function doOnCentralPage() {
      //intercept ajax load of group tab
      $(document).ajaxComplete(function(event, jqxhr, option) {
         
         if (option.url == "../plugins/escalade/ajax/central.php") return;

         if (option.url.indexOf('common.tabs.php') > 0 
            /* && (
               option.url.indexOf("Central$2") > 0 //TODO : option.params
               || option.url.indexOf("-1") > 0 //option.params
            )*/) {
            //$( document ).ajaxStop(function() {
            //delay the execution (ajax requestcomplete event fired before dom loading)
            setTimeout(function () {
               console.log("in setTimeout");
               
               var suffix = "";
               var selector = "#ui-tabs-2 .tab_cadre_central tr td:nth-child(2)" +
                  ", .alltab:contains('$locale_group_view') + .tab_cadre_central > tbody > tr > td:nth-child(2)";
               
               console.log($(selector)); //arf !
               console.log(selector);
               // get central list for plugin and insert in group tab
               $(selector).each(function(){
                  console.log("in each");
                  
                  if (this.innerHTML.indexOf('escalade_block') < 0) {
                     console.log("in if");
                     
                     if (option.url.indexOf("-1") > 0) { //option.params
                        suffix = "_all";
                     }
                     
                     //prepare a span element to load new elements
                     $(this).after("<span id='escalade_block"+suffix+"'>test</span>");
                     
                     //ajax request
                     selectorbis = "#escalade_block"+suffix;
                     $(selectorbis).load('../plugins/escalade/ajax/central.php');
                  }
               });
            }, 300);
            //return false;
            //});
         }
      });
   }
   
   // only on central page
   if (location.pathname.indexOf('central.php') > 0) {
      doOnCentralPage();
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


   });
JAVASCRIPT;
      echo $JS;
   }
}